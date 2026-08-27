<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class VsphereService
{
    protected const SESSION_CACHE_KEY = 'vsphere.session_id';

    protected const SOAP_SESSION_CACHE_KEY = 'vsphere.soap_session_cookie';

    protected const SESSION_TTL_SECONDS = 1800;

    protected ?string $baseUrl;

    protected ?string $username;

    protected ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.vsphere.url'), '/');
        $this->username = config('services.vsphere.username');
        $this->password = config('services.vsphere.password');
    }

    public function getVms(): array
    {
        return $this->get('/api/vcenter/vm');
    }

    /**
     * Same as getVms(), with each VM annotated with the name of the host
     * it runs on (via getVmHostMap(), since the vCenter VM list endpoint
     * doesn't include host assignment on its own).
     */
    public function getVmsWithHost(): array
    {
        $hostMap = $this->getVmHostMap();

        return array_map(
            fn (array $vm) => $vm + ['host' => $hostMap[$vm['vm']] ?? null],
            $this->getVms(),
        );
    }

    public function getHosts(): array
    {
        return $this->get('/api/vcenter/host');
    }

    public function getClusters(): array
    {
        return $this->get('/api/vcenter/cluster');
    }

    public function getDatastores(): array
    {
        return $this->get('/api/vcenter/datastore');
    }

    /**
     * Health status of each vCenter Server Appliance subsystem (its own
     * OS/VM-level CPU, memory, storage, database, etc. — not the ESXi hosts
     * or workload VMs it manages). Each value is one of vCenter's health
     * colors: green, yellow, orange, red, or gray (unknown/unsupported on
     * this vCenter version).
     *
     * @return array<string, string> component => health color
     */
    public function getApplianceHealth(): array
    {
        $components = [
            'system' => '/api/appliance/health/system',
            'cpu' => '/api/appliance/health/cpu',
            'mem' => '/api/appliance/health/mem',
            'swap' => '/api/appliance/health/swap',
            'storage' => '/api/appliance/health/storage',
            'database_storage' => '/api/appliance/health/database-storage',
            'load' => '/api/appliance/health/load',
            'applmgmt' => '/api/appliance/health/applmgmt',
            'software_packages' => '/api/appliance/health/software-packages',
        ];

        $health = [];

        foreach ($components as $key => $path) {
            try {
                $health[$key] = trim((string) $this->request($path)->body(), '"');
            } catch (Throwable) {
                $health[$key] = 'gray';
            }
        }

        return $health;
    }

    /**
     * Metadata for every metric the appliance monitoring API can report
     * (id, name, description, units, instance) — not live values. Use
     * getApplianceMonitoringLatest() to read current readings.
     */
    public function getApplianceMonitoringItems(): array
    {
        return $this->get('/api/appliance/monitoring');
    }

    /**
     * Most recent value of each given monitoring item id, averaged over
     * the last 30 minutes in 5-minute buckets (the query endpoint always
     * returns aggregated buckets — there's no "raw sample" function).
     *
     * Quirks of this endpoint discovered by probing it directly (its
     * behavior isn't fully covered by VMware's public docs): the item list
     * must be passed as a repeated `names` query param (NOT `item_ids`,
     * which the API rejects outright with "Unsupported property"), the
     * `function` must be one of AVG/MAX/MIN/COUNT (NOT the docs' "NONE" —
     * that 500s with "Invalid function type"), and the response is a bare
     * JSON array (not wrapped in a `{"data": [...]}` envelope).
     *
     * @param  array<int, string>  $itemIds
     * @return array<string, float|null> item id => latest value
     */
    public function getApplianceMonitoringLatest(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $end = now();
        $start = $end->clone()->subMinutes(30);

        $query = http_build_query([
            'start_time' => $start->toIso8601ZuluString(),
            'end_time' => $end->toIso8601ZuluString(),
            'interval' => 'MINUTES5',
            'function' => 'AVG',
        ]);

        foreach ($itemIds as $itemId) {
            $query .= '&names='.urlencode($itemId);
        }

        $series = $this->request('/api/appliance/monitoring/query?'.$query)->json() ?? [];
        $latest = [];

        foreach ($series as $entry) {
            $values = array_values(array_filter(
                $entry['data'] ?? [],
                fn ($value) => $value !== null && $value !== '',
            ));

            $latest[$entry['name']] = $values === [] ? null : (float) end($values);
        }

        return $latest;
    }

    /**
     * Combined appliance health + resource utilization view: overall
     * subsystem health colors, plus the latest CPU/memory/swap/storage
     * readings with their display metadata (units, instance). Note: the
     * `name`/`description` fields on monitoring items are untranslated
     * localization keys (e.g. "com.vmware.applmgmt.mon.name.cpu.util"),
     * not human-readable text, so they're deliberately left out here —
     * the frontend derives a display label from `id` instead.
     */
    public function getApplianceOverview(): array
    {
        $items = collect($this->getApplianceMonitoringItems());

        $metricItems = $items->filter(fn (array $item) => str_starts_with($item['id'] ?? '', 'cpu.')
            || str_starts_with($item['id'] ?? '', 'mem.')
            || str_starts_with($item['id'] ?? '', 'swap.')
            || str_starts_with($item['id'] ?? '', 'storage.'));

        $latest = $this->getApplianceMonitoringLatest($metricItems->pluck('id')->values()->all());

        $metrics = $metricItems->map(fn (array $item) => [
            'id' => $item['id'],
            'units' => $item['units'] ?? null,
            'instance' => ($item['instance'] ?? '') === '' ? null : $item['instance'],
            'value' => $latest[$item['id']] ?? null,
        ])->values()->all();

        return [
            'health' => $this->getApplianceHealth(),
            'metrics' => $metrics,
        ];
    }

    /**
     * Maps each VM id to the name of the host it runs on. The basic VM list
     * endpoint doesn't include host assignment, and this vCenter's `/api`
     * surface rejects all `filter.*` query params (HTTP 400 on every filter
     * tried, including documented ones like filter.power_states) — so this
     * falls back to the legacy `/rest/vcenter/vm` surface, which still
     * supports `filter.hosts` and wraps results in a `{"value": [...]}`
     * envelope, queried once per host.
     *
     * @return array<string, string> vm id => host name
     */
    public function getVmHostMap(): array
    {
        $map = [];

        foreach ($this->getHosts() as $host) {
            $vms = $this->getLegacyFiltered('/rest/vcenter/vm?filter.hosts='.urlencode($host['host']));

            foreach ($vms as $vm) {
                $map[$vm['vm']] = $host['name'];
            }
        }

        return $map;
    }

    /**
     * For each given (powered-on) VM id, fetches guest OS filesystem usage
     * in one pooled (concurrent) request batch instead of one round trip
     * per VM — with dozens of VMs, sequential calls would take minutes and
     * risk timing out. Requires VMware Tools to be installed and running;
     * when it's not (or the request otherwise fails), that VM's fields come
     * back null rather than aborting the whole batch.
     *
     * @param  array<int, string>  $vmIds
     * @return array<string, array{disk_usage_pct: ?float, capacity_gb: ?float, used_gb: ?float}>
     */
    public function getVmGuestSnapshots(array $vmIds): array
    {
        if (empty($vmIds)) {
            return [];
        }

        $sessionId = $this->getSessionId();

        $filesystemResponses = Http::pool(fn (Pool $pool) => collect($vmIds)
            ->map(fn ($id) => $this->pooled($pool->as($id), $sessionId)
                ->get($this->baseUrl."/api/vcenter/vm/{$id}/guest/local-filesystem"))
            ->all());

        $snapshots = [];

        foreach ($vmIds as $id) {
            $snapshots[$id] = $this->extractDiskUsage($filesystemResponses[$id] ?? null);
        }

        return $snapshots;
    }

    /**
     * For each given VM id, the time ESXi/vCenter last recorded the VM
     * powering on (`runtime.bootTime`), read via the classic SOAP API. This
     * is tracked by the hypervisor itself, unlike the REST guest/power
     * endpoint's boot_time, which requires the guest OS's VMware Tools to
     * report it — Tools on this environment's VMs don't, so that field is
     * always empty. A VM with no recorded boot time (e.g. powered off)
     * comes back null rather than aborting the whole batch.
     *
     * @param  array<int, string>  $vmIds
     * @return array<string, ?string> vm id => ISO 8601 boot time, or null
     */
    public function getVmBootTimes(array $vmIds, bool $isRetry = false): array
    {
        if (empty($vmIds)) {
            return [];
        }

        $cookie = $this->getSoapSessionCookie();

        $objectSet = collect($vmIds)
            ->map(fn ($id) => '<vim25:objectSet><vim25:obj type="VirtualMachine">'.$this->xmlEscape($id).'</vim25:obj></vim25:objectSet>')
            ->implode('');

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>VirtualMachine</vim25:type>
          <vim25:pathSet>runtime.bootTime</vim25:pathSet>
        </vim25:propSet>
        '.$objectSet.'
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->getVmBootTimes($vmIds, isRetry: true);
        }

        if ($response->failed()) {
            return [];
        }

        return $this->parseBootTimes($response->body());
    }

    /**
     * For each given entity id (of the given vim25 type — HostSystem,
     * VirtualMachine, or Datastore), the currently triggered alarms on it,
     * read via the classic SOAP API's `triggeredAlarmState`. An entity with
     * no active alarms is omitted from the result rather than aborting the
     * whole batch; alarm names/descriptions aren't included here — resolve
     * the `alarm` ids through getAlarmDefinitions().
     *
     * @param  array<int, string>  $entityIds
     * @return array<string, array{name: string, alarms: array<int, array{alarm: string, status: string, time: ?string, acknowledged: bool}>}>
     */
    public function getTriggeredAlarms(array $entityIds, string $entityType, bool $isRetry = false): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $cookie = $this->getSoapSessionCookie();

        $objectSet = collect($entityIds)
            ->map(fn ($id) => '<vim25:objectSet><vim25:obj type="'.$entityType.'">'.$this->xmlEscape($id).'</vim25:obj></vim25:objectSet>')
            ->implode('');

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>'.$entityType.'</vim25:type>
          <vim25:pathSet>name</vim25:pathSet>
          <vim25:pathSet>triggeredAlarmState</vim25:pathSet>
        </vim25:propSet>
        '.$objectSet.'
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->getTriggeredAlarms($entityIds, $entityType, isRetry: true);
        }

        if ($response->failed()) {
            return [];
        }

        return $this->parseTriggeredAlarms($response->body());
    }

    /**
     * Resolves each given Alarm id to its definition (name + description) —
     * the human-readable alarm rule, shared across every entity it's
     * triggered on. An id that can't be resolved is omitted rather than
     * aborting the whole batch.
     *
     * @param  array<int, string>  $alarmIds
     * @return array<string, array{name: string, description: string}>
     */
    public function getAlarmDefinitions(array $alarmIds, bool $isRetry = false): array
    {
        if (empty($alarmIds)) {
            return [];
        }

        $cookie = $this->getSoapSessionCookie();

        $objectSet = collect($alarmIds)
            ->map(fn ($id) => '<vim25:objectSet><vim25:obj type="Alarm">'.$this->xmlEscape($id).'</vim25:obj></vim25:objectSet>')
            ->implode('');

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>Alarm</vim25:type>
          <vim25:pathSet>info.name</vim25:pathSet>
          <vim25:pathSet>info.description</vim25:pathSet>
        </vim25:propSet>
        '.$objectSet.'
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->getAlarmDefinitions($alarmIds, isRetry: true);
        }

        if ($response->failed()) {
            return [];
        }

        return $this->parseAlarmDefinitions($response->body());
    }

    /**
     * For each given (powered-on) VM id, fetches the guest OS's primary IP
     * address and hostname. Requires VMware Tools to be installed and
     * running; when it's not (or the request otherwise fails), both fields
     * come back null rather than aborting the whole batch.
     *
     * @param  array<int, string>  $vmIds
     * @return array<string, array{ip_address: ?string, host_name: ?string}>
     */
    public function getVmGuestIdentities(array $vmIds): array
    {
        if (empty($vmIds)) {
            return [];
        }

        $sessionId = $this->getSessionId();

        $responses = Http::pool(fn (Pool $pool) => collect($vmIds)
            ->map(fn ($id) => $this->pooled($pool->as($id), $sessionId)
                ->get($this->baseUrl."/api/vcenter/vm/{$id}/guest/identity"))
            ->all());

        $identities = [];

        foreach ($vmIds as $id) {
            $response = $responses[$id] ?? null;
            $ok = $response instanceof Response && $response->successful();

            $identities[$id] = [
                'ip_address' => $ok ? ($response->json('ip_address') ?: null) : null,
                'host_name' => $ok ? ($response->json('host_name') ?: null) : null,
            ];
        }

        return $identities;
    }

    /**
     * Resolves every vSphere performance counter (e.g. "cpu.usage.average")
     * to its numeric counterId, read via the classic SOAP API and cached
     * for a day — the mapping is fixed for a given vCenter/ESXi build, so
     * there's no need to re-resolve it on every chart load.
     *
     * @return array<string, int> "group.name.rollup" => counterId
     */
    public function getPerfCounterIds(): array
    {
        return Cache::remember('vsphere.perf_counter_ids', 86400, fn () => $this->fetchPerfCounterIds());
    }

    protected function fetchPerfCounterIds(bool $isRetry = false): array
    {
        $cookie = $this->getSoapSessionCookie();

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>PerformanceManager</vim25:type>
          <vim25:pathSet>perfCounter</vim25:pathSet>
        </vim25:propSet>
        <vim25:objectSet>
          <vim25:obj type="PerformanceManager">PerfMgr</vim25:obj>
        </vim25:objectSet>
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->fetchPerfCounterIds(isRetry: true);
        }

        if ($response->failed()) {
            throw new RuntimeException('ไม่สามารถโหลดรายการ performance counter จาก vCenter ได้');
        }

        return $this->parsePerfCounterIds($response->body());
    }

    /**
     * Real-time performance samples for the given entity and counters —
     * the last hour in 20-second buckets, the same window vCenter's own
     * "Performance Overview" chart shows. An entity with no real-time
     * stats currently available (just added, powered off, stats provider
     * outage) comes back empty rather than throwing.
     *
     * @param  array<int, int>  $counterIds
     * @return array<int, array<int, array{time: string, value: float}>> counterId => samples
     */
    public function queryPerf(string $entityId, string $entityType, array $counterIds, bool $isRetry = false): array
    {
        if (empty($counterIds)) {
            return [];
        }

        $cookie = $this->getSoapSessionCookie();

        $metricIds = collect($counterIds)
            ->map(fn (int $id) => '<vim25:metricId><vim25:counterId>'.$id.'</vim25:counterId><vim25:instance></vim25:instance></vim25:metricId>')
            ->implode('');

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:QueryPerf>
      <vim25:_this type="PerformanceManager">PerfMgr</vim25:_this>
      <vim25:querySpec>
        <vim25:entity type="'.$entityType.'">'.$this->xmlEscape($entityId).'</vim25:entity>
        <vim25:maxSample>180</vim25:maxSample>
        '.$metricIds.'
        <vim25:intervalId>20</vim25:intervalId>
        <vim25:format>normal</vim25:format>
      </vim25:querySpec>
    </vim25:QueryPerf>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->queryPerf($entityId, $entityType, $counterIds, isRetry: true);
        }

        if ($response->failed()) {
            return [];
        }

        return $this->parsePerfEntityMetrics($response->body());
    }

    /**
     * Same as queryPerf(), but for many entities of the same type in one
     * SOAP round trip (QueryPerf's querySpec parameter accepts a list) —
     * used where "the current value for every VM" is needed (e.g. ranking
     * VMs by CPU usage), where querying one entity at a time would mean
     * one round trip per VM. maxSample defaults to 1 (just the latest
     * real-time sample) since a ranking only needs a current value, not a
     * full historical series.
     *
     * @param  array<int, string>  $entityIds
     * @param  array<int, int>  $counterIds
     * @return array<string, array<int, array<int, array{time: string, value: float}>>> entityId => counterId => samples
     */
    public function queryPerfMulti(array $entityIds, string $entityType, array $counterIds, int $maxSample = 1, bool $isRetry = false): array
    {
        if (empty($entityIds) || empty($counterIds)) {
            return [];
        }

        $cookie = $this->getSoapSessionCookie();

        $metricIds = collect($counterIds)
            ->map(fn (int $id) => '<vim25:metricId><vim25:counterId>'.$id.'</vim25:counterId><vim25:instance></vim25:instance></vim25:metricId>')
            ->implode('');

        $querySpecs = collect($entityIds)
            ->map(fn (string $entityId) => '<vim25:querySpec>
        <vim25:entity type="'.$entityType.'">'.$this->xmlEscape($entityId).'</vim25:entity>
        <vim25:maxSample>'.$maxSample.'</vim25:maxSample>
        '.$metricIds.'
        <vim25:intervalId>20</vim25:intervalId>
        <vim25:format>normal</vim25:format>
      </vim25:querySpec>')
            ->implode('');

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:QueryPerf>
      <vim25:_this type="PerformanceManager">PerfMgr</vim25:_this>
      '.$querySpecs.'
    </vim25:QueryPerf>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->queryPerfMulti($entityIds, $entityType, $counterIds, $maxSample, isRetry: true);
        }

        if ($response->failed()) {
            return [];
        }

        return $this->parsePerfEntityMetricsMulti($response->body());
    }

    /**
     * @return array<string, int>
     */
    protected function parsePerfCounterIds(string $body): array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return [];
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $ids = [];

        foreach ($xml->xpath('//vim25:returnval/vim25:propSet/vim25:val/vim25:PerfCounterInfo') as $info) {
            $info->registerXPathNamespace('vim25', 'urn:vim25');

            $key = (string) ($info->xpath('vim25:key')[0] ?? '');
            $group = (string) ($info->xpath('vim25:groupInfo/vim25:key')[0] ?? '');
            $name = (string) ($info->xpath('vim25:nameInfo/vim25:key')[0] ?? '');
            $rollup = (string) ($info->xpath('vim25:rollupType')[0] ?? '');

            if ($key === '' || $group === '' || $name === '' || $rollup === '') {
                continue;
            }

            $ids["{$group}.{$name}.{$rollup}"] = (int) $key;
        }

        return $ids;
    }

    /**
     * Parses a QueryPerf response holding a single PerfEntityMetric
     * (one querySpec was sent, for one entity) into per-counter samples.
     *
     * @return array<int, array<int, array{time: string, value: float}>>
     */
    protected function parsePerfEntityMetrics(string $body): array
    {
        $all = $this->parsePerfEntityMetricsMulti($body);

        return $all === [] ? [] : reset($all);
    }

    /**
     * Same as parsePerfEntityMetrics(), but for a QueryPerf response
     * holding one PerfEntityMetric per entity queried (queryPerfMulti()
     * sends one querySpec per entity) — keyed by entity id.
     *
     * @return array<string, array<int, array<int, array{time: string, value: float}>>>
     */
    protected function parsePerfEntityMetricsMulti(string $body): array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return [];
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $results = [];

        foreach ($xml->xpath('//vim25:returnval') as $entityNode) {
            $entityNode->registerXPathNamespace('vim25', 'urn:vim25');

            $entityId = (string) ($entityNode->xpath('vim25:entity')[0] ?? '');

            if ($entityId === '') {
                continue;
            }

            $timestamps = [];

            foreach ($entityNode->xpath('vim25:sampleInfo') as $sample) {
                $sample->registerXPathNamespace('vim25', 'urn:vim25');
                $timestamps[] = (string) ($sample->xpath('vim25:timestamp')[0] ?? '');
            }

            $series = [];

            foreach ($entityNode->xpath('vim25:value') as $valueNode) {
                $valueNode->registerXPathNamespace('vim25', 'urn:vim25');

                $counterId = (int) ($valueNode->xpath('vim25:id/vim25:counterId')[0] ?? 0);

                $points = [];

                foreach ($valueNode->xpath('vim25:value') as $index => $value) {
                    if (! isset($timestamps[$index]) || $timestamps[$index] === '') {
                        continue;
                    }

                    $points[] = ['time' => $timestamps[$index], 'value' => (float) $value];
                }

                $series[$counterId] = $points;
            }

            $results[$entityId] = $series;
        }

        return $results;
    }

    /**
     * A host's network configuration — default gateway, DNS (servers,
     * domain, search domains), and each VMkernel interface's IP/subnet/MAC
     * — read via the classic SOAP API's HostSystem.config.network, since
     * the REST `/api/vcenter/host` surface doesn't expose networking
     * details at all. Used by the Dashboard's per-host network modal.
     *
     * @return array{
     *     vnics: array<int, array{device: string, portgroup: ?string, ip_address: ?string, subnet_mask: ?string, mac: ?string}>,
     *     dns: ?array{host_name: ?string, domain_name: ?string, addresses: array<int, string>, search_domains: array<int, string>},
     *     default_gateway: ?string,
     * }
     */
    public function getHostNetworkInfo(string $hostId, bool $isRetry = false): array
    {
        $cookie = $this->getSoapSessionCookie();

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>HostSystem</vim25:type>
          <vim25:pathSet>config.network</vim25:pathSet>
        </vim25:propSet>
        <vim25:objectSet>
          <vim25:obj type="HostSystem">'.$this->xmlEscape($hostId).'</vim25:obj>
        </vim25:objectSet>
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->getHostNetworkInfo($hostId, isRetry: true);
        }

        if ($response->failed()) {
            throw new RuntimeException("ไม่สามารถโหลดข้อมูล Network ของ Host ได้ (HTTP {$response->status()})");
        }

        return $this->parseHostNetworkInfo($response->body());
    }

    /**
     * @return array{vnics: array<int, array<string, mixed>>, dns: ?array<string, mixed>, default_gateway: ?string}
     */
    protected function parseHostNetworkInfo(string $body): array
    {
        $empty = ['vnics' => [], 'dns' => null, 'default_gateway' => null];

        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return $empty;
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $network = ($xml->xpath('//vim25:returnval/vim25:propSet/vim25:val') ?: [])[0] ?? null;

        if ($network === null) {
            return $empty;
        }

        $network->registerXPathNamespace('vim25', 'urn:vim25');

        $vnics = [];

        foreach ($network->xpath('vim25:vnic') as $vnic) {
            $vnic->registerXPathNamespace('vim25', 'urn:vim25');

            $vnics[] = [
                'device' => (string) ($vnic->xpath('vim25:device')[0] ?? ''),
                'portgroup' => $this->nullableText($vnic, 'vim25:portgroup'),
                'ip_address' => $this->nullableText($vnic, 'vim25:spec/vim25:ip/vim25:ipAddress'),
                'subnet_mask' => $this->nullableText($vnic, 'vim25:spec/vim25:ip/vim25:subnetMask'),
                'mac' => $this->nullableText($vnic, 'vim25:spec/vim25:mac'),
            ];
        }

        $dns = null;
        $dnsNode = ($network->xpath('vim25:dnsConfig') ?: [])[0] ?? null;

        if ($dnsNode !== null) {
            $dnsNode->registerXPathNamespace('vim25', 'urn:vim25');

            $dns = [
                'host_name' => $this->nullableText($dnsNode, 'vim25:hostName'),
                'domain_name' => $this->nullableText($dnsNode, 'vim25:domainName'),
                'addresses' => collect($dnsNode->xpath('vim25:address'))->map(fn ($n) => (string) $n)->all(),
                'search_domains' => collect($dnsNode->xpath('vim25:searchDomain'))->map(fn ($n) => (string) $n)->all(),
            ];
        }

        $defaultGateway = null;
        $routeNode = ($network->xpath('vim25:ipRouteConfig') ?: [])[0] ?? null;

        if ($routeNode !== null) {
            $routeNode->registerXPathNamespace('vim25', 'urn:vim25');
            $defaultGateway = $this->nullableText($routeNode, 'vim25:defaultGateway');
        }

        return ['vnics' => $vnics, 'dns' => $dns, 'default_gateway' => $defaultGateway];
    }

    /**
     * One host's hardware/hypervisor summary — the same data vCenter's own
     * host Summary tab shows (General panel + Hardware panel) — for the
     * Manage Hosts page's info dialog. Fetched on demand per host, same
     * reasoning as getHostNetworkInfo().
     */
    public function getHostHardwareInfo(string $hostId, bool $isRetry = false): array
    {
        $cookie = $this->getSoapSessionCookie();

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:RetrieveProperties>
      <vim25:_this type="PropertyCollector">propertyCollector</vim25:_this>
      <vim25:specSet>
        <vim25:propSet>
          <vim25:type>HostSystem</vim25:type>
          <vim25:pathSet>summary.hardware</vim25:pathSet>
          <vim25:pathSet>summary.config.product</vim25:pathSet>
        </vim25:propSet>
        <vim25:objectSet>
          <vim25:obj type="HostSystem">'.$this->xmlEscape($hostId).'</vim25:obj>
        </vim25:objectSet>
      </vim25:specSet>
    </vim25:RetrieveProperties>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body, $cookie);

        if ($this->isSoapSessionExpired($response) && ! $isRetry) {
            Cache::forget(self::SOAP_SESSION_CACHE_KEY);

            return $this->getHostHardwareInfo($hostId, isRetry: true);
        }

        if ($response->failed()) {
            throw new RuntimeException("ไม่สามารถโหลดข้อมูล Hardware ของ Host ได้ (HTTP {$response->status()})");
        }

        return $this->parseHostHardwareInfo($response->body());
    }

    /**
     * @return array{hypervisor: ?string, manufacturer: ?string, model: ?string, processor_type: ?string, cpu_cores: ?int, sockets: ?int, cores_per_socket: ?int, logical_processors: ?int, nics: ?int, memory_bytes: ?int}
     */
    protected function parseHostHardwareInfo(string $body): array
    {
        $empty = [
            'hypervisor' => null,
            'manufacturer' => null,
            'model' => null,
            'processor_type' => null,
            'cpu_cores' => null,
            'sockets' => null,
            'cores_per_socket' => null,
            'logical_processors' => null,
            'nics' => null,
            'memory_bytes' => null,
        ];

        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return $empty;
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $hardware = null;
        $product = null;

        foreach ($xml->xpath('//vim25:returnval/vim25:propSet') ?: [] as $propSet) {
            $propSet->registerXPathNamespace('vim25', 'urn:vim25');
            $name = (string) ($propSet->xpath('vim25:name')[0] ?? '');
            $val = ($propSet->xpath('vim25:val')[0] ?? null);

            if ($val === null) {
                continue;
            }

            $val->registerXPathNamespace('vim25', 'urn:vim25');

            if ($name === 'summary.hardware') {
                $hardware = $val;
            } elseif ($name === 'summary.config.product') {
                $product = $val;
            }
        }

        $hypervisor = null;

        if ($product !== null) {
            $hypervisor = $this->nullableText($product, 'vim25:fullName');

            if ($hypervisor === null) {
                $name = $this->nullableText($product, 'vim25:name');
                $version = $this->nullableText($product, 'vim25:version');
                $build = $this->nullableText($product, 'vim25:build');
                $hypervisor = trim(collect([$name, $version, $build ? "build {$build}" : null])->filter()->implode(', ')) ?: null;
            }
        }

        if ($hardware === null) {
            return [...$empty, 'hypervisor' => $hypervisor];
        }

        $numCpuCores = $this->nullableInt($hardware, 'vim25:numCpuCores');
        $numCpuPkgs = $this->nullableInt($hardware, 'vim25:numCpuPkgs');

        return [
            'hypervisor' => $hypervisor,
            'manufacturer' => $this->nullableText($hardware, 'vim25:vendor'),
            'model' => $this->nullableText($hardware, 'vim25:model'),
            'processor_type' => $this->nullableText($hardware, 'vim25:cpuModel'),
            'cpu_cores' => $numCpuCores,
            'sockets' => $numCpuPkgs,
            'cores_per_socket' => ($numCpuCores !== null && $numCpuPkgs) ? intdiv($numCpuCores, $numCpuPkgs) : null,
            'logical_processors' => $this->nullableInt($hardware, 'vim25:numCpuThreads'),
            'nics' => $this->nullableInt($hardware, 'vim25:numNics'),
            'memory_bytes' => $this->nullableInt($hardware, 'vim25:memorySize'),
        ];
    }

    protected function nullableText(SimpleXMLElement $node, string $xpath): ?string
    {
        $value = (string) ($node->xpath($xpath)[0] ?? '');

        return $value !== '' ? $value : null;
    }

    protected function nullableInt(SimpleXMLElement $node, string $xpath): ?int
    {
        $value = $this->nullableText($node, $xpath);

        return $value !== null ? (int) $value : null;
    }

    protected function pooled(PendingRequest $request, string $sessionId): PendingRequest
    {
        $request = $request->timeout(30)->withHeaders(['vmware-api-session-id' => $sessionId]);

        return app()->environment('production') ? $request : $request->withoutVerifying();
    }

    /**
     * Sums capacity/free_space across every reported guest filesystem to get
     * overall disk usage percentage plus raw capacity/used size in GB.
     * Tolerates a couple of differently-shaped responses seen across
     * vCenter versions (a bare array of filesystem objects, or one wrapped
     * under `value`).
     *
     * @return array{disk_usage_pct: ?float, capacity_gb: ?float, used_gb: ?float}
     */
    protected function extractDiskUsage(mixed $response): array
    {
        $empty = ['disk_usage_pct' => null, 'capacity_gb' => null, 'used_gb' => null];

        if (! $response instanceof Response || $response->failed()) {
            return $empty;
        }

        $filesystems = $response->json();
        $filesystems = is_array($filesystems) ? ($filesystems['value'] ?? $filesystems) : [];

        if (! is_array($filesystems)) {
            return $empty;
        }

        $capacity = 0.0;
        $free = 0.0;

        foreach ($filesystems as $fs) {
            $capacity += (float) (data_get($fs, 'capacity') ?? data_get($fs, 'value.capacity') ?? 0);
            $free += (float) (data_get($fs, 'free_space') ?? data_get($fs, 'value.free_space') ?? 0);
        }

        if ($capacity <= 0) {
            return $empty;
        }

        $used = max(0.0, $capacity - $free);

        return [
            'disk_usage_pct' => round(($used / $capacity) * 100, 1),
            'capacity_gb' => round($capacity / 1073741824, 2),
            'used_gb' => round($used / 1073741824, 2),
        ];
    }

    protected function get(string $path): array
    {
        return $this->request($path)->json() ?? [];
    }

    /**
     * Same as get(), but for the legacy `/rest/...` API surface, whose
     * responses are wrapped as {"value": [...]} instead of a bare array.
     */
    protected function getLegacyFiltered(string $path): array
    {
        return $this->request($path)->json('value') ?? [];
    }

    /**
     * Sends an authenticated request to vCenter. On a 401 (expired/invalid
     * session), clears the cached session, re-authenticates, and retries once.
     */
    protected function request(string $path, bool $isRetry = false): Response
    {
        $sessionId = $this->getSessionId();

        $response = $this->client()
            ->withHeaders(['vmware-api-session-id' => $sessionId])
            ->get($this->baseUrl.$path);

        if ($response->status() === 401 && ! $isRetry) {
            Cache::forget(self::SESSION_CACHE_KEY);

            return $this->request($path, isRetry: true);
        }

        if ($response->failed()) {
            throw new RuntimeException("vCenter API error [{$path}]: HTTP {$response->status()}");
        }

        return $response;
    }

    protected function getSessionId(): string
    {
        return Cache::remember(
            self::SESSION_CACHE_KEY,
            self::SESSION_TTL_SECONDS,
            fn () => $this->authenticate(),
        );
    }

    protected function authenticate(): string
    {
        if (! $this->baseUrl || ! $this->username || ! $this->password) {
            throw new RuntimeException('กรุณาตั้งค่า VSPHERE_URL, VSPHERE_USERNAME, VSPHERE_PASSWORD ในไฟล์ .env');
        }

        $response = $this->client()
            ->withBasicAuth($this->username, $this->password)
            ->post($this->baseUrl.'/api/session');

        if ($response->failed()) {
            throw new RuntimeException("ไม่สามารถเข้าสู่ระบบ vCenter ได้ (HTTP {$response->status()})");
        }

        return trim($response->body(), '"');
    }

    /**
     * Sends a request against the classic SOAP API (`/sdk`), used only for
     * `runtime.bootTime` (see getVmBootTimes()) — the REST surface has no
     * equivalent that doesn't depend on guest-side VMware Tools.
     */
    protected function soapRequest(string $body, ?string $cookie = null): Response
    {
        $request = $this->client()->withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'urn:vim25/6.7',
        ]);

        if ($cookie !== null) {
            $request = $request->withHeaders(['Cookie' => $cookie]);
        }

        return $request->withBody($body, 'text/xml')->post($this->baseUrl.'/sdk');
    }

    protected function getSoapSessionCookie(): string
    {
        return Cache::remember(
            self::SOAP_SESSION_CACHE_KEY,
            self::SESSION_TTL_SECONDS,
            fn () => $this->soapLogin(),
        );
    }

    protected function soapLogin(): string
    {
        if (! $this->baseUrl || ! $this->username || ! $this->password) {
            throw new RuntimeException('กรุณาตั้งค่า VSPHERE_URL, VSPHERE_USERNAME, VSPHERE_PASSWORD ในไฟล์ .env');
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vim25="urn:vim25">
  <soapenv:Body>
    <vim25:Login>
      <vim25:_this type="SessionManager">SessionManager</vim25:_this>
      <vim25:userName>'.$this->xmlEscape($this->username).'</vim25:userName>
      <vim25:password>'.$this->xmlEscape($this->password).'</vim25:password>
    </vim25:Login>
  </soapenv:Body>
</soapenv:Envelope>';

        $response = $this->soapRequest($body);

        if ($response->failed()) {
            throw new RuntimeException("ไม่สามารถเข้าสู่ระบบ vCenter (SOAP) ได้ (HTTP {$response->status()})");
        }

        foreach ($response->toPsrResponse()->getHeader('Set-Cookie') as $setCookie) {
            if (str_starts_with($setCookie, 'vmware_soap_session')) {
                return explode(';', $setCookie)[0];
            }
        }

        throw new RuntimeException('ไม่พบ vCenter SOAP session cookie');
    }

    protected function isSoapSessionExpired(Response $response): bool
    {
        return $response->status() === 500 && str_contains($response->body(), 'NotAuthenticated');
    }

    /**
     * @return array<string, ?string> vm id => ISO 8601 boot time, or null
     */
    protected function parseBootTimes(string $body): array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return [];
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $bootTimes = [];

        foreach ($xml->xpath('//vim25:returnval') as $returnval) {
            $returnval->registerXPathNamespace('vim25', 'urn:vim25');

            $id = (string) ($returnval->xpath('vim25:obj')[0] ?? '');

            if ($id === '') {
                continue;
            }

            $bootTimes[$id] = null;

            foreach ($returnval->xpath('vim25:propSet') as $propSet) {
                $propSet->registerXPathNamespace('vim25', 'urn:vim25');

                if ((string) ($propSet->xpath('vim25:name')[0] ?? '') === 'runtime.bootTime') {
                    $value = (string) ($propSet->xpath('vim25:val')[0] ?? '');
                    $bootTimes[$id] = $value !== '' ? $value : null;
                }
            }
        }

        return $bootTimes;
    }

    /**
     * @return array<string, array{name: string, alarms: array<int, array{alarm: string, status: string, time: ?string, acknowledged: bool}>}>
     */
    protected function parseTriggeredAlarms(string $body): array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return [];
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $entities = [];

        foreach ($xml->xpath('//vim25:returnval') as $returnval) {
            $returnval->registerXPathNamespace('vim25', 'urn:vim25');

            $id = (string) ($returnval->xpath('vim25:obj')[0] ?? '');

            if ($id === '') {
                continue;
            }

            $name = $id;
            $alarms = [];

            foreach ($returnval->xpath('vim25:propSet') as $propSet) {
                $propSet->registerXPathNamespace('vim25', 'urn:vim25');

                $propName = (string) ($propSet->xpath('vim25:name')[0] ?? '');

                if ($propName === 'name') {
                    $name = (string) ($propSet->xpath('vim25:val')[0] ?? $id);
                }

                if ($propName === 'triggeredAlarmState') {
                    foreach ($propSet->xpath('vim25:val/vim25:AlarmState') as $state) {
                        $state->registerXPathNamespace('vim25', 'urn:vim25');

                        $time = (string) ($state->xpath('vim25:time')[0] ?? '');

                        $alarms[] = [
                            'alarm' => (string) ($state->xpath('vim25:alarm')[0] ?? ''),
                            'status' => (string) ($state->xpath('vim25:overallStatus')[0] ?? ''),
                            'time' => $time !== '' ? $time : null,
                            'acknowledged' => (string) ($state->xpath('vim25:acknowledged')[0] ?? '') === 'true',
                        ];
                    }
                }
            }

            if (! empty($alarms)) {
                $entities[$id] = ['name' => $name, 'alarms' => $alarms];
            }
        }

        return $entities;
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    protected function parseAlarmDefinitions(string $body): array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            return [];
        }

        $xml->registerXPathNamespace('vim25', 'urn:vim25');

        $definitions = [];

        foreach ($xml->xpath('//vim25:returnval') as $returnval) {
            $returnval->registerXPathNamespace('vim25', 'urn:vim25');

            $id = (string) ($returnval->xpath('vim25:obj')[0] ?? '');

            if ($id === '') {
                continue;
            }

            $name = '';
            $description = '';

            foreach ($returnval->xpath('vim25:propSet') as $propSet) {
                $propSet->registerXPathNamespace('vim25', 'urn:vim25');

                $propName = (string) ($propSet->xpath('vim25:name')[0] ?? '');
                $value = (string) ($propSet->xpath('vim25:val')[0] ?? '');

                if ($propName === 'info.name') {
                    $name = $value;
                }

                if ($propName === 'info.description') {
                    $description = $value;
                }
            }

            $definitions[$id] = ['name' => $name, 'description' => $description];
        }

        return $definitions;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function client(): PendingRequest
    {
        $http = Http::timeout(30);

        // Self-signed certs are common on internal vCenter appliances;
        // only skip verification outside production.
        if (! app()->environment('production')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}

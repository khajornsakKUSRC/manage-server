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

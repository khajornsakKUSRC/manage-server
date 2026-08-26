<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PerformanceVsphereService
{
    /**
     * One entry per chart: display label, unit, and the vSphere perf
     * counters ("group.name.rollup", matching
     * VsphereService::getPerfCounterIds() keys) summed into its series.
     * CPU/memory percent counters are stored by vCenter as hundredths of a
     * percent, so those two charts scale by 0.01; the KBps rate counters
     * are already in their display unit.
     *
     * @var array<string, array{label: string, unit: string, counters: array<int, string>, scale: float}>
     */
    protected const CHARTS = [
        'cpu' => ['label' => 'CPU Usage', 'unit' => '%', 'counters' => ['cpu.usage.average'], 'scale' => 0.01],
        'memory' => ['label' => 'Memory Usage', 'unit' => '%', 'counters' => ['mem.usage.average'], 'scale' => 0.01],
        'memory_rate' => ['label' => 'Memory Swap Rate', 'unit' => 'KBps', 'counters' => ['mem.swapinRate.average', 'mem.swapoutRate.average'], 'scale' => 1.0],
        'disk' => ['label' => 'Disk Rate', 'unit' => 'KBps', 'counters' => ['disk.usage.average'], 'scale' => 1.0],
        'network' => ['label' => 'Network Rate', 'unit' => 'KBps', 'counters' => ['net.usage.average'], 'scale' => 1.0],
    ];

    public function __construct(
        protected VsphereService $vsphere,
    ) {}

    /**
     * Every host, plus every VM annotated with the name of the host it
     * runs on, for the Performance page's host cards + per-host VM
     * dropdown. id/type are what pull() needs; status/power_state feed
     * the health badge.
     *
     * @return array{
     *     hosts: array<int, array{id: string, type: string, name: string, status: ?string, power_state: ?string}>,
     *     vms: array<int, array{id: string, type: string, name: string, host: ?string, power_state: ?string}>,
     * }
     */
    public function entities(): array
    {
        $hosts = collect($this->vsphere->getHosts())
            ->map(fn (array $host) => [
                'id' => $host['host'],
                'type' => 'HostSystem',
                'name' => $host['name'],
                'status' => $host['connection_state'] ?? null,
                'power_state' => $host['power_state'] ?? null,
            ])
            ->sortBy('name')
            ->values();

        $vms = collect($this->vsphere->getVmsWithHost())
            ->map(fn (array $vm) => [
                'id' => $vm['vm'],
                'type' => 'VirtualMachine',
                'name' => $vm['name'],
                'host' => $vm['host'] ?? null,
                'power_state' => $vm['power_state'] ?? null,
            ])
            ->sortBy('name')
            ->values();

        return [
            'hosts' => $hosts->all(),
            'vms' => $vms->all(),
        ];
    }

    /**
     * Real-time (last hour) CPU/memory/disk/network series for one host or
     * VM — the same window as vCenter's own Performance Overview chart.
     *
     * @return array<string, array{label: string, unit: string, series: array<int, array{time: string, value: float}>}>
     */
    public function pull(string $entityId, string $entityType): array
    {
        $counterIds = collect($this->vsphere->getPerfCounterIds());

        $wantedIds = collect(self::CHARTS)
            ->flatMap(fn (array $chart) => $chart['counters'])
            ->unique()
            ->mapWithKeys(fn (string $name) => [$name => $counterIds->get($name)])
            ->filter();

        $raw = $this->vsphere->queryPerf($entityId, $entityType, $wantedIds->values()->unique()->all());

        return collect(self::CHARTS)
            ->mapWithKeys(fn (array $chart, string $key) => [
                $key => [
                    'label' => $chart['label'],
                    'unit' => $chart['unit'],
                    'series' => $this->combineSeries($chart['counters'], $wantedIds, $raw, $chart['scale']),
                ],
            ])
            ->all();
    }

    /**
     * Current CPU usage (%) for every powered-on VM, highest first,
     * capped to $limit — for the Dashboard's "Top VMs by CPU" bar chart.
     * One batched QueryPerf call covers every VM (see
     * VsphereService::queryPerfMulti()) rather than one round trip per
     * VM, and only the latest real-time sample is requested since a
     * ranking only needs a current value, not a history.
     *
     * @return array<int, array{id: string, name: string, cpu_pct: float}>
     */
    public function topCpuVms(int $limit = 10): array
    {
        $poweredOn = collect($this->vsphere->getVms())
            ->filter(fn (array $vm) => ($vm['power_state'] ?? null) === 'POWERED_ON');

        if ($poweredOn->isEmpty()) {
            return [];
        }

        $cpuCounterId = $this->vsphere->getPerfCounterIds()['cpu.usage.average'] ?? null;

        if ($cpuCounterId === null) {
            return [];
        }

        $entityIds = $poweredOn->pluck('vm')->values()->all();
        $raw = $this->vsphere->queryPerfMulti($entityIds, 'VirtualMachine', [$cpuCounterId]);

        return $poweredOn
            ->map(function (array $vm) use ($raw, $cpuCounterId) {
                $points = $raw[$vm['vm']][$cpuCounterId] ?? [];
                $latest = end($points);

                return [
                    'id' => $vm['vm'],
                    'name' => $vm['name'],
                    'cpu_pct' => $latest ? round($latest['value'] * 0.01, 1) : null,
                ];
            })
            ->filter(fn (array $row) => $row['cpu_pct'] !== null)
            ->sortByDesc('cpu_pct')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Sums same-timestamp samples across every counter feeding a chart
     * (e.g. swap-in + swap-out for the memory rate chart) into one series,
     * scaled to the chart's display unit.
     *
     * @param  array<int, string>  $counterNames
     * @param  Collection<string, int>  $wantedIds
     * @param  array<int, array<int, array{time: string, value: float}>>  $raw
     * @return array<int, array{time: string, value: float}>
     */
    protected function combineSeries(array $counterNames, Collection $wantedIds, array $raw, float $scale): array
    {
        $byTime = [];

        foreach ($counterNames as $name) {
            $counterId = $wantedIds->get($name);

            if ($counterId === null) {
                continue;
            }

            foreach ($raw[$counterId] ?? [] as $point) {
                $byTime[$point['time']] = ($byTime[$point['time']] ?? 0.0) + $point['value'];
            }
        }

        ksort($byTime);

        return collect($byTime)
            ->map(fn (float $value, string $time) => ['time' => $time, 'value' => round($value * $scale, 2)])
            ->values()
            ->all();
    }
}

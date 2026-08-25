<?php

namespace App\Services;

use App\Models\Vm;
use Illuminate\Support\Str;

class AlarmVsphereService
{
    public function __construct(
        protected VsphereService $vsphere,
    ) {}

    /**
     * Every Active VM (per the Manage VMs inventory) whose live vCenter
     * power state isn't POWERED_ON — either genuinely powered off/suspended,
     * or missing from vCenter entirely (renamed/removed), which counts as
     * down rather than silently ignored. Only the REST power-state list is
     * queried — no guest-tools or SOAP calls — since that's all a plain
     * up/down check needs.
     *
     * @return array<int, array{name: string, power_state: ?string}>
     */
    public function downVms(): array
    {
        $liveByName = collect($this->vsphere->getVms())
            ->keyBy(fn (array $vm) => Str::lower(trim($vm['name'] ?? '')));

        return Vm::active()
            ->get(['name'])
            ->map(fn (Vm $vm) => [
                'name' => $vm->name,
                'power_state' => $liveByName->get(Str::lower(trim($vm->name)))['power_state'] ?? null,
            ])
            ->filter(fn (array $vm) => $vm['power_state'] !== 'POWERED_ON')
            ->values()
            ->all();
    }

    /**
     * Builds one row per Host/VM/Datastore that currently has a triggered
     * vCenter alarm, each with its alarm list (severity, time triggered,
     * acknowledged, and the alarm's name/description), sorted by alarm
     * count descending — "objects with most alerts" first. Nothing is
     * persisted here; the caller decides whether/when to request AI hints
     * for the alarms found.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array
    {
        $triggered = $this->triggeredEntities();

        $alarmIds = collect($triggered)
            ->flatMap(fn (array $group) => collect($group['entities'])
                ->flatMap(fn (array $entity) => collect($entity['alarms'])->pluck('alarm')))
            ->unique()
            ->values()
            ->all();

        $definitions = $this->vsphere->getAlarmDefinitions($alarmIds);

        $objects = collect($triggered)
            ->flatMap(function (array $group) use ($definitions) {
                return collect($group['entities'])->map(function (array $entity) use ($group, $definitions) {
                    $alarms = collect($entity['alarms'])
                        ->map(fn (array $alarm) => [
                            'name' => $definitions[$alarm['alarm']]['name'] ?? 'Unknown alarm',
                            'description' => $definitions[$alarm['alarm']]['description'] ?? '',
                            'status' => $alarm['status'],
                            'time' => $alarm['time'],
                            'acknowledged' => $alarm['acknowledged'],
                        ])
                        ->values()
                        ->all();

                    return [
                        'type' => $group['type'],
                        'name' => $entity['name'],
                        'alarms' => $alarms,
                    ];
                });
            })
            ->sortByDesc(fn (array $object) => count($object['alarms']))
            ->values()
            ->all();

        return $objects;
    }

    /**
     * Total number of currently triggered alarms across hosts, VMs, and
     * datastores — for the sidebar's notification badge. Skips resolving
     * alarm definitions (name/description) since a plain count doesn't
     * need them, so this is cheaper than pull() and safe to poll often.
     */
    public function countTriggeredAlarms(): int
    {
        return collect($this->triggeredEntities())
            ->sum(fn (array $group) => collect($group['entities'])
                ->sum(fn (array $entity) => count($entity['alarms'])));
    }

    /**
     * @return array<int, array{type: string, entities: array<string, array{name: string, alarms: array<int, array<string, mixed>>}>}>
     */
    protected function triggeredEntities(): array
    {
        $hosts = collect($this->vsphere->getHosts())->pluck('host')->all();
        $vms = collect($this->vsphere->getVms())->pluck('vm')->all();
        $datastores = collect($this->vsphere->getDatastores())->pluck('datastore')->all();

        return [
            ['type' => 'Host', 'entities' => $this->vsphere->getTriggeredAlarms($hosts, 'HostSystem')],
            ['type' => 'VM', 'entities' => $this->vsphere->getTriggeredAlarms($vms, 'VirtualMachine')],
            ['type' => 'Datastore', 'entities' => $this->vsphere->getTriggeredAlarms($datastores, 'Datastore')],
        ];
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;

class VmVsphereService
{
    public function __construct(
        protected VsphereService $vsphere,
    ) {}

    /**
     * Builds one VM inventory row per VM, live from vCenter: host, power
     * state, allocated vCPU/memory, and — for powered-on VMs with VMware
     * Tools running — guest IP address, hostname, uptime, and disk space.
     * Nothing is persisted here; the caller decides whether/when to save
     * the result.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array
    {
        $hostMap = $this->vsphere->getVmHostMap();
        $vms = collect($this->vsphere->getVms());

        $poweredOnIds = $vms
            ->filter(fn (array $vm) => ($vm['power_state'] ?? null) === 'POWERED_ON')
            ->pluck('vm')
            ->all();

        $guestSnapshots = $this->vsphere->getVmGuestSnapshots($poweredOnIds);
        $guestIdentities = $this->vsphere->getVmGuestIdentities($poweredOnIds);

        return $vms
            ->map(function (array $vm) use ($hostMap, $guestSnapshots, $guestIdentities) {
                $guest = $guestSnapshots[$vm['vm']] ?? ['capacity_gb' => null, 'used_gb' => null, 'boot_time' => null];
                $identity = $guestIdentities[$vm['vm']] ?? ['ip_address' => null, 'host_name' => null];

                return [
                    'name' => $vm['name'],
                    'host' => $hostMap[$vm['vm']] ?? null,
                    'ip' => $identity['ip_address'],
                    'dns' => $identity['host_name'],
                    'state' => $vm['power_state'] ?? null,
                    'provisioned_space' => $this->formatGb($guest['capacity_gb']),
                    'used_space' => $this->formatGb($guest['used_gb']),
                    'memory_gb' => isset($vm['memory_size_MiB'])
                        ? (int) round($vm['memory_size_MiB'] / 1024)
                        : null,
                    'cpu_cores' => $vm['cpu_count'] ?? null,
                    'uptime_seconds' => $guest['boot_time']
                        ? max(0, Carbon::parse($guest['boot_time'])->diffInSeconds(now()))
                        : null,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    protected function formatGb(?float $gb): ?string
    {
        return $gb !== null ? $gb.' GB' : null;
    }
}

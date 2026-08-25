<?php

namespace App\Services;

use App\Models\Vm;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DailyReportVsphereService
{
    public function __construct(
        protected VsphereService $vsphere,
    ) {}

    /**
     * Builds one snapshot row per Active VM (per the Manage VMs inventory),
     * live from vCenter: power state (UP/DOWN), allocated vCPU/memory, and
     * — for powered-on VMs — guest disk usage and uptime, plus the
     * inventory's own IP address, Certificate Exp, and Note (IP comes from
     * the Manage VMs inventory rather than a fresh live guest-identity call,
     * same as Certificate Exp/Note — it's already kept in sync via
     * VmController::sync()). Nothing is persisted here; the caller decides
     * whether/when to save the result.
     *
     * The Active list in the VMs inventory is the source of truth for
     * which machines count toward the report — not vCenter's live list —
     * so an Active VM that vCenter doesn't currently return (renamed,
     * removed, momentarily unreachable) still appears here, counted as
     * down, rather than silently vanishing from the total.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array
    {
        $hostMap = $this->vsphere->getVmHostMap();

        $liveByName = collect($this->vsphere->getVms())
            ->keyBy(fn (array $vm) => Str::lower(trim($vm['name'] ?? '')));

        $activeVms = Vm::where('is_active', true)->get(['name', 'ip', 'certificate_exp', 'notes']);

        $matches = $activeVms->map(fn (Vm $vm) => [
            'vm' => $vm,
            'live' => $liveByName->get(Str::lower(trim($vm->name))),
        ]);

        $poweredOnIds = $matches
            ->filter(fn (array $m) => ($m['live']['power_state'] ?? null) === 'POWERED_ON')
            ->pluck('live.vm')
            ->all();

        $guestSnapshots = $this->vsphere->getVmGuestSnapshots($poweredOnIds);
        $bootTimes = $this->vsphere->getVmBootTimes($poweredOnIds);

        return $matches
            ->map(function (array $m) use ($hostMap, $guestSnapshots, $bootTimes) {
                $vm = $m['vm'];
                $live = $m['live'];

                if ($live === null) {
                    return [
                        'name' => $vm->name,
                        'host' => null,
                        'ip' => $vm->ip,
                        'power_state' => null,
                        'cpu_count' => null,
                        'memory_gb' => null,
                        'disk_usage_pct' => null,
                        'uptime_seconds' => null,
                        'certificate_exp' => $vm->certificate_exp,
                        'notes' => $vm->notes,
                    ];
                }

                $guest = $guestSnapshots[$live['vm']] ?? ['disk_usage_pct' => null];
                $bootTime = $bootTimes[$live['vm']] ?? null;

                return [
                    'name' => $live['name'],
                    'host' => $hostMap[$live['vm']] ?? null,
                    'ip' => $vm->ip,
                    'power_state' => $live['power_state'] ?? null,
                    'cpu_count' => $live['cpu_count'] ?? null,
                    'memory_gb' => isset($live['memory_size_MiB'])
                        ? round($live['memory_size_MiB'] / 1024, 2)
                        : null,
                    'disk_usage_pct' => $guest['disk_usage_pct'],
                    'uptime_seconds' => $bootTime
                        ? max(0, (int) Carbon::parse($bootTime)->diffInSeconds(now()))
                        : null,
                    'certificate_exp' => $vm->certificate_exp,
                    'notes' => $vm->notes,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\AlarmHintService;
use App\Services\AlarmRuleHintService;
use App\Services\AlarmVsphereService;
use App\Services\DatastoreTrendService;
use App\Services\PerformanceVsphereService;
use App\Services\VsphereService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class VsphereController extends Controller
{
    public function vms(VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getVmsWithHost());
    }

    public function hosts(VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getHosts());
    }

    /**
     * One host's network config (gateway, DNS, VMkernel interfaces) — for
     * the Dashboard's per-host network modal. Fetched on demand per host
     * rather than bulk-loaded with the host list, since it needs a
     * separate SOAP call per host.
     */
    public function hostNetwork(string $host, VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getHostNetworkInfo($host));
    }

    public function clusters(VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getClusters());
    }

    public function datastores(VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getDatastores());
    }

    public function appliance(VsphereService $vsphere): JsonResponse
    {
        return $this->respond(fn () => $vsphere->getApplianceOverview());
    }

    /**
     * Objects (hosts, VMs, datastores) with currently triggered vCenter
     * alarms, ranked by alarm count, each alarm annotated with a
     * resolution hint. AI-generated hints (AlarmHintService) are used
     * where available — needs ANTHROPIC_API_KEY configured — and every
     * alarm that doesn't get one (no key configured, or that call failed)
     * falls back to AlarmRuleHintService's local, zero-configuration
     * keyword lookup, so there's always a suggestion to show.
     */
    public function alarms(AlarmVsphereService $alarmService, AlarmHintService $hintService, AlarmRuleHintService $ruleHintService): JsonResponse
    {
        return $this->respond(function () use ($alarmService, $hintService, $ruleHintService) {
            $objects = $alarmService->pull();

            $allAlarms = collect($objects)->flatMap(fn (array $object) => $object['alarms'])->all();
            $aiHints = $hintService->hints($allAlarms);

            foreach ($objects as &$object) {
                foreach ($object['alarms'] as &$alarm) {
                    $aiHint = $aiHints[$hintService->cacheKey($alarm)] ?? null;

                    if ($aiHint !== null) {
                        $alarm['hint'] = $aiHint;
                        $alarm['hint_source'] = 'ai';
                    } else {
                        $fallback = $ruleHintService->hint($alarm['name'], $alarm['description']);
                        $alarm['hint'] = $fallback['hint'];
                        $alarm['hint_source'] = $fallback['source'];
                    }
                }
            }

            return $objects;
        });
    }

    /**
     * Just the total count of currently triggered alarms — cheap enough to
     * poll from the sidebar's notification badge without generating AI
     * hints or resolving alarm definitions.
     */
    public function alarmsCount(AlarmVsphereService $alarmService): JsonResponse
    {
        return $this->respond(fn () => $alarmService->countTriggeredAlarms());
    }

    /**
     * Every datastore with its current usage, recorded history, and a
     * fill-up projection (once enough history has accumulated).
     */
    public function datastoreTrends(DatastoreTrendService $trendService): JsonResponse
    {
        return $this->respond(fn () => $trendService->pull());
    }

    /**
     * Every host and VM in vCenter, for the Performance page's entity
     * picker.
     */
    public function performanceEntities(PerformanceVsphereService $performance): JsonResponse
    {
        return $this->respond(fn () => $performance->entities());
    }

    /**
     * Real-time CPU/memory/disk/network series (last hour) for one host or
     * VM, for the Performance page's charts.
     */
    public function performanceMetrics(Request $request, PerformanceVsphereService $performance): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'type' => ['required', 'string', 'in:HostSystem,VirtualMachine'],
        ]);

        return $this->respond(fn () => $performance->pull($validated['id'], $validated['type']));
    }

    /**
     * Runs the vCenter call and returns a clean JSON response. Exceptions are
     * logged server-side only — the client never sees credentials, session
     * IDs, or internal vCenter error details.
     */
    protected function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'ไม่สามารถเชื่อมต่อ vCenter ได้ กรุณาลองใหม่อีกครั้ง',
            ], 502);
        }
    }
}

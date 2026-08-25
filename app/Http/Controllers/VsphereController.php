<?php

namespace App\Http\Controllers;

use App\Services\AlarmHintService;
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
     * alarms, ranked by alarm count, each alarm annotated with an
     * AI-generated resolution hint where available.
     */
    public function alarms(AlarmVsphereService $alarmService, AlarmHintService $hintService): JsonResponse
    {
        return $this->respond(function () use ($alarmService, $hintService) {
            $objects = $alarmService->pull();

            $allAlarms = collect($objects)->flatMap(fn (array $object) => $object['alarms'])->all();
            $hints = $hintService->hints($allAlarms);

            foreach ($objects as &$object) {
                foreach ($object['alarms'] as &$alarm) {
                    $alarm['hint'] = $hints[$hintService->cacheKey($alarm)] ?? null;
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

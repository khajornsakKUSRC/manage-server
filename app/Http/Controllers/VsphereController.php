<?php

namespace App\Http\Controllers;

use App\Services\VsphereService;
use Illuminate\Http\JsonResponse;
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

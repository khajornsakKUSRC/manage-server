<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Services\ServerPingService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('dashboard');
    }

    public function ping(Host $host, ServerPingService $pingService): JsonResponse
    {
        if (blank($host->ip)) {
            return response()->json(['online' => false, 'error' => 'ไม่มี IP Address สำหรับ host นี้']);
        }

        return response()->json($pingService->ping($host->ip));
    }
}

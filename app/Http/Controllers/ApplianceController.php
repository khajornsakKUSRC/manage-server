<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Inertia\Inertia;
use Inertia\Response;

class ApplianceController extends Controller
{
    public function index(): Response
    {
        $settings = SystemSetting::current();

        return Inertia::render('appliance/index', [
            'thresholds' => [
                'cpu_warning_pct' => $settings->cpu_warning_pct,
                'cpu_critical_pct' => $settings->cpu_critical_pct,
                'mem_warning_pct' => $settings->mem_warning_pct,
                'mem_critical_pct' => $settings->mem_critical_pct,
            ],
        ]);
    }
}

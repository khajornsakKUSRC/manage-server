<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Inertia\Inertia;
use Inertia\Response;

class DatastoreController extends Controller
{
    public function index(): Response
    {
        $settings = SystemSetting::current();

        return Inertia::render('datastores/index', [
            'thresholds' => [
                'warning_pct' => $settings->datastore_warning_pct,
                'critical_pct' => $settings->datastore_critical_pct,
            ],
        ]);
    }
}

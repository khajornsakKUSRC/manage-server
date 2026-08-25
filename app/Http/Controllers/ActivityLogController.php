<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    protected const LIMIT = 20;

    public function index(): Response
    {
        return Inertia::render('activity-log/index', [
            'logs' => ActivityLog::with('user:id,name,email')
                ->latest()
                ->limit(self::LIMIT)
                ->get(),
        ]);
    }
}

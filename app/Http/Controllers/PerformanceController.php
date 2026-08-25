<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('performance/index');
    }
}

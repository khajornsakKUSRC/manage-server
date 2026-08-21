<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AlarmController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('alarms/index');
    }
}

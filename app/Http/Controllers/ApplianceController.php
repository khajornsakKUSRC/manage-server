<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ApplianceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('appliance/index');
    }
}

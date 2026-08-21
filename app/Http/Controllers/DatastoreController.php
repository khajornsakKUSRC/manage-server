<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DatastoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('datastores/index');
    }
}

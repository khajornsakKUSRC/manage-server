<?php

namespace App\Http\Controllers;

use App\Models\Host;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HostController extends Controller
{
    public function index()
    {
        return Inertia::render('hosts/index');
    }

    public function create()
    {
        return Inertia::render('hosts/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
        ]);

        Host::create($validated);

        return redirect()->route('hosts.index')->with('success', 'Host created successfully.');
    }

    public function edit(Host $host)
    {
        return Inertia::render('hosts/edit', [
            'host' => $host,
        ]);
    }

    public function update(Request $request, Host $host)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
        ]);

        $host->update($validated);

        return redirect()->route('hosts.index')->with('success', 'Host updated successfully.');
    }

    public function destroy(Host $host)
    {
        $host->delete();

        return redirect()->route('hosts.index')->with('success', 'Host deleted successfully.');
    }
}

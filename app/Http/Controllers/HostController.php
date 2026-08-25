<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Services\ActivityLogger;
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

    public function store(Request $request, ActivityLogger $activityLogger)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
        ]);

        $host = Host::create($validated);

        $activityLogger->record(
            action: 'created',
            description: "Created host '{$host->name}'",
            subjectType: 'host',
            subjectLabel: $host->name,
        );

        return redirect()->route('hosts.index')->with('success', 'Host created successfully.');
    }

    public function edit(Host $host)
    {
        return Inertia::render('hosts/edit', [
            'host' => $host,
        ]);
    }

    public function update(Request $request, Host $host, ActivityLogger $activityLogger)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
        ]);

        $host->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated host '{$host->name}'",
            subjectType: 'host',
            subjectLabel: $host->name,
        );

        return redirect()->route('hosts.index')->with('success', 'Host updated successfully.');
    }

    public function destroy(Host $host, ActivityLogger $activityLogger)
    {
        $name = $host->name;

        $host->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted host '{$name}'",
            subjectType: 'host',
            subjectLabel: $name,
        );

        return redirect()->route('hosts.index')->with('success', 'Host deleted successfully.');
    }
}

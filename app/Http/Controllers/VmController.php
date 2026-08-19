<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Vm;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VmController extends Controller
{
    public function index()
    {
        return Inertia::render('vms/index', [
            'vms' => Vm::with('host')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('vms/create', [
            'hosts' => Host::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'host_id' => 'required|exists:hosts,id',
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
            'dns' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:50',
            'provisioned_space' => 'nullable|string|max:50',
            'used_space' => 'nullable|string|max:50',
            'memory_gb' => 'nullable|integer',
            'cpu_cores' => 'nullable|integer',
        ]);

        Vm::create($validated);

        return redirect()->route('vms.index')->with('success', 'VM created successfully.');
    }

    public function edit(Vm $vm)
    {
        return Inertia::render('vms/edit', [
            'vm' => $vm,
            'hosts' => Host::all(),
        ]);
    }

    public function update(Request $request, Vm $vm)
    {
        $validated = $request->validate([
            'host_id' => 'required|exists:hosts,id',
            'name' => 'required|string|max:255',
            'ip' => 'nullable|string|max:45',
            'dns' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:50',
            'provisioned_space' => 'nullable|string|max:50',
            'used_space' => 'nullable|string|max:50',
            'memory_gb' => 'nullable|integer',
            'cpu_cores' => 'nullable|integer',
        ]);

        $vm->update($validated);

        return redirect()->route('vms.index')->with('success', 'VM updated successfully.');
    }

    public function destroy(Vm $vm)
    {
        $vm->delete();

        return redirect()->route('vms.index')->with('success', 'VM deleted successfully.');
    }
}

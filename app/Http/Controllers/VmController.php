<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Vm;
use App\Services\VmVsphereService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VmController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('vms/index', [
            'vms' => Vm::with('host')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function edit(Vm $vm): Response
    {
        return Inertia::render('vms/edit', [
            'vm' => $vm->load('host'),
        ]);
    }

    /**
     * Only the manually-managed fields are editable here — Name, Host, IP,
     * DNS, State, Provisioned/Used Space, and Memory/CPU all come from
     * vCenter via sync().
     */
    public function update(Request $request, Vm $vm): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'certificate_exp' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $vm->update($validated);

        return redirect()->route('vms.index')->with('success', 'VM updated successfully.');
    }

    public function destroy(Vm $vm): RedirectResponse
    {
        $vm->delete();

        return redirect()->route('vms.index')->with('success', 'VM deleted successfully.');
    }

    /**
     * Pulls the live VM inventory from vCenter and upserts it into the
     * database, matched by name. Host/IP/DNS/state/space/memory/CPU are
     * refreshed from vCenter (a field is left as-is when this pull can't
     * currently supply it, e.g. IP/DNS/space on a VM without VMware Tools
     * running); notes, certificate_exp, and is_active are never touched
     * here since they're manually managed via the Edit form.
     */
    public function sync(VmVsphereService $vmVsphereService): RedirectResponse
    {
        try {
            $items = $vmVsphereService->pull();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['sync' => 'ไม่สามารถดึงข้อมูลจาก vCenter ได้']);
        }

        $synced = 0;

        DB::transaction(function () use ($items, &$synced) {
            $hostIds = [];

            foreach ($items as $item) {
                if (empty($item['name'])) {
                    continue;
                }

                $hostId = null;

                if (! empty($item['host'])) {
                    $hostIds[$item['host']] ??= Host::firstOrCreate(['name' => $item['host']])->id;
                    $hostId = $hostIds[$item['host']];
                }

                $attributes = array_filter([
                    'host_id' => $hostId,
                    'ip' => $item['ip'],
                    'dns' => $item['dns'],
                    'state' => $item['state'],
                    'provisioned_space' => $item['provisioned_space'],
                    'used_space' => $item['used_space'],
                    'memory_gb' => $item['memory_gb'],
                    'cpu_cores' => $item['cpu_cores'],
                ], fn ($value) => $value !== null);

                // Unlike the fields above (kept as-is when this pull can't
                // currently supply them), uptime is always overwritten —
                // including with null — since a stale uptime on a VM that's
                // since powered off would be actively misleading.
                $attributes['uptime_seconds'] = $item['uptime_seconds'];

                $vm = Vm::where('name', $item['name'])->first();

                if ($vm) {
                    $vm->update($attributes);
                    $synced++;
                } elseif ($hostId !== null) {
                    Vm::create(['name' => $item['name']] + $attributes);
                    $synced++;
                }
            }
        });

        return redirect()->route('vms.index')->with('success', "Synced {$synced} VM(s) from vCenter.");
    }
}

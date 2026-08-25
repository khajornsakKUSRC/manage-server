<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\Vm;
use App\Services\ActivityLogger;
use App\Services\VmVsphereService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VmController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filtersFromRequest($request);

        return Inertia::render('vms/index', [
            'vms' => $this->filteredQuery($filters)->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    /**
     * Every VM matching the same search/state/active filters as index(),
     * unpaginated — feeds the "Import Certificate Exp" modal's VM picker so
     * it can select from the whole filtered set, not just the current
     * page of 20.
     */
    public function certificateExpCandidates(Request $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);

        $vms = $this->filteredQuery($filters)
            ->get(['id', 'name', 'host_id', 'certificate_exp'])
            ->map(fn (Vm $vm) => [
                'id' => $vm->id,
                'name' => $vm->name,
                'host' => $vm->host?->name,
                'certificate_exp' => $vm->certificate_exp,
            ]);

        return response()->json(['data' => $vms]);
    }

    /**
     * Sets the same Certificate Exp date on every selected VM at once —
     * the bulk counterpart to editing it one VM at a time via update().
     */
    public function bulkCertificateExp(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'certificate_exp' => 'required|date_format:Y-m-d',
            'vm_ids' => 'required|array|min:1',
            'vm_ids.*' => 'integer|exists:vms,id',
        ]);

        $count = Vm::whereIn('id', $validated['vm_ids'])
            ->update(['certificate_exp' => $validated['certificate_exp']]);

        $activityLogger->record(
            action: 'updated',
            description: "Set Certificate Exp to {$validated['certificate_exp']} for {$count} VM(s)",
            subjectType: 'vm',
        );

        return back()->with('success', "Updated Certificate Exp for {$count} VM(s).");
    }

    /**
     * @return array{search: string, state: string, active: string}
     */
    protected function filtersFromRequest(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'state' => (string) $request->query('state', ''),
            'active' => (string) $request->query('active', ''),
        ];
    }

    /**
     * @param  array{search: string, state: string, active: string}  $filters
     */
    protected function filteredQuery(array $filters): Builder
    {
        // Active VMs first (desc puts true=1 before false=0), then
        // alphabetical within each group.
        $query = Vm::with('host')->orderByDesc('is_active')->orderBy('name');

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhereHas('host', fn ($hostQuery) => $hostQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if ($filters['state'] !== '') {
            $query->where('state', $filters['state']);
        }

        if ($filters['active'] !== '') {
            $query->where('is_active', $filters['active'] === '1');
        }

        return $query;
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
    public function update(Request $request, Vm $vm, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'certificate_exp' => 'nullable|date_format:Y-m-d',
            'is_active' => 'boolean',
        ]);

        $vm->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated VM '{$vm->name}'",
            subjectType: 'vm',
            subjectLabel: $vm->name,
        );

        return redirect()->route('vms.index')->with('success', 'VM updated successfully.');
    }

    public function destroy(Vm $vm, ActivityLogger $activityLogger): RedirectResponse
    {
        $name = $vm->name;

        $vm->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted VM '{$name}'",
            subjectType: 'vm',
            subjectLabel: $name,
        );

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
    public function sync(VmVsphereService $vmVsphereService, ActivityLogger $activityLogger): RedirectResponse
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

        $activityLogger->record(
            action: 'synced',
            description: "Synced {$synced} VM(s) from vCenter",
            subjectType: 'vm',
        );

        return redirect()->route('vms.index')->with('success', "Synced {$synced} VM(s) from vCenter.");
    }
}

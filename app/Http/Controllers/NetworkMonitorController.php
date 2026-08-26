<?php

namespace App\Http\Controllers;

use App\Models\NetworkMonitor;
use App\Models\NetworkMonitorCheck;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NetworkMonitorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('network-infrastructure/index', [
            'categories' => NetworkMonitor::CATEGORIES,
            'types' => NetworkMonitor::TYPES,
        ]);
    }

    /**
     * Every monitor, each annotated with its last-hour heartbeat history
     * (for the uptime bar/chart) and its up/total ratio over the last 24h
     * (for the uptime % badge). Polled by the frontend — see
     * network-infrastructure/index.tsx.
     */
    public function status(): JsonResponse
    {
        $now = now();
        $historySince = $now->copy()->subHour();
        $uptimeSince = $now->copy()->subDay();

        $monitors = NetworkMonitor::orderBy('category')->orderBy('name')->get();

        $checksByMonitor = NetworkMonitorCheck::whereIn('network_monitor_id', $monitors->pluck('id'))
            ->where('checked_at', '>=', $uptimeSince)
            ->orderBy('checked_at')
            ->get()
            ->groupBy('network_monitor_id');

        $data = $monitors->map(function (NetworkMonitor $monitor) use ($checksByMonitor, $historySince) {
            $checks = $checksByMonitor->get($monitor->id, collect());
            $upCount = $checks->where('status', NetworkMonitor::STATUS_UP)->count();
            $total = $checks->count();

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'category' => $monitor->category,
                'category_label' => NetworkMonitor::CATEGORIES[$monitor->category] ?? $monitor->category,
                'type' => $monitor->type,
                'type_label' => NetworkMonitor::TYPES[$monitor->type] ?? $monitor->type,
                'target' => $monitor->target,
                'port' => $monitor->port,
                'is_active' => $monitor->is_active,
                'status' => $monitor->is_active ? ($monitor->last_status ?? NetworkMonitor::STATUS_PENDING) : 'paused',
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                'last_response_time_ms' => $monitor->last_response_time_ms,
                'last_message' => $monitor->last_message,
                'uptime_24h_pct' => $total > 0 ? round($upCount / $total * 100, 1) : null,
                'heartbeats' => $checks
                    ->filter(fn (NetworkMonitorCheck $check) => $check->checked_at->gte($historySince))
                    ->map(fn (NetworkMonitorCheck $check) => [
                        'checked_at' => $check->checked_at->toIso8601String(),
                        'status' => $check->status,
                        'response_time_ms' => $check->response_time_ms,
                    ])
                    ->values(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function create(): Response
    {
        return Inertia::render('network-infrastructure/create', [
            'categories' => NetworkMonitor::CATEGORIES,
            'types' => NetworkMonitor::TYPES,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);

        $monitor = NetworkMonitor::create($validated);

        $activityLogger->record(
            action: 'created',
            description: "Created network monitor '{$monitor->name}'",
            subjectType: 'network_monitor',
            subjectLabel: $monitor->name,
        );

        return redirect()->route('network-monitors.index')->with('success', 'Monitor created successfully.');
    }

    public function edit(NetworkMonitor $networkMonitor): Response
    {
        return Inertia::render('network-infrastructure/edit', [
            'monitor' => $networkMonitor,
            'categories' => NetworkMonitor::CATEGORIES,
            'types' => NetworkMonitor::TYPES,
        ]);
    }

    public function update(Request $request, NetworkMonitor $networkMonitor, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);

        $networkMonitor->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated network monitor '{$networkMonitor->name}'",
            subjectType: 'network_monitor',
            subjectLabel: $networkMonitor->name,
        );

        return redirect()->route('network-monitors.index')->with('success', 'Monitor updated successfully.');
    }

    public function destroy(NetworkMonitor $networkMonitor, ActivityLogger $activityLogger): RedirectResponse
    {
        $name = $networkMonitor->name;

        $networkMonitor->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted network monitor '{$name}'",
            subjectType: 'network_monitor',
            subjectLabel: $name,
        );

        return redirect()->route('network-monitors.index')->with('success', 'Monitor deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(array_keys(NetworkMonitor::CATEGORIES))],
            'type' => ['required', Rule::in(array_keys(NetworkMonitor::TYPES))],
            'target' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535|required_if:type,tcp',
            'interval_seconds' => 'required|integer|min:60|max:86400',
            'timeout_ms' => 'required|integer|min:500|max:30000',
            'is_active' => 'boolean',
        ]);
    }
}

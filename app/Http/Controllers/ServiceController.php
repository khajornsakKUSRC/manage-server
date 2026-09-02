<?php

namespace App\Http\Controllers;

use App\Models\MonitoredService;
use App\Services\ActivityLogger;
use App\Services\ServiceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('services/index', [
            'services' => MonitoredService::orderBy('label')->get()
                ->map(fn (MonitoredService $service) => $this->present($service)),
        ]);
    }

    /**
     * Status for every monitored service. By default this reads the
     * last-known values persisted by the scheduled services:check run —
     * no SSH — so opening the page (or an HMR reload) costs nothing on
     * the fleet. Pass ?refresh=1 (the page's "Refresh" button) to do a
     * live check, one SSH round-trip per host, and persist the result.
     */
    public function statuses(Request $request, ServiceStatusService $statusService): JsonResponse
    {
        $live = $request->boolean('refresh');
        $services = MonitoredService::orderBy('label')->get();

        if ($live) {
            $statusService->refresh($services);
            $services = MonitoredService::orderBy('label')->get();
        }

        return response()->json([
            'data' => $services->map(fn (MonitoredService $service) => $this->present($service)),
            'live' => $live,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MonitoredService $service): array
    {
        return [
            'id' => $service->id,
            'label' => $service->label,
            'host' => $service->host,
            'service_name' => $service->service_name,
            'status' => $service->last_status,
            'healthy' => $service->last_healthy,
            'detail' => $service->last_detail,
            'raw' => $service->last_raw,
            'checked_at' => $service->last_checked_at?->toIso8601String(),
        ];
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);

        $service = MonitoredService::create($validated);

        $activityLogger->record(
            action: 'created',
            description: "Added monitored service '{$service->label}' ({$service->service_name}@{$service->host})",
            subjectType: 'monitored_service',
            subjectLabel: $service->label,
        );

        return redirect()->route('services.index')->with('success', 'Service added successfully.');
    }

    public function update(Request $request, MonitoredService $service, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request, $service);

        $service->update($validated);

        $activityLogger->record(
            action: 'updated',
            description: "Updated monitored service '{$service->label}' ({$service->service_name}@{$service->host})",
            subjectType: 'monitored_service',
            subjectLabel: $service->label,
        );

        return redirect()->route('services.index')->with('success', 'Service updated successfully.');
    }

    public function destroy(MonitoredService $service, ActivityLogger $activityLogger): RedirectResponse
    {
        $label = $service->label;

        $service->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Removed monitored service '{$label}'",
            subjectType: 'monitored_service',
            subjectLabel: $label,
        );

        return redirect()->route('services.index')->with('success', 'Service removed successfully.');
    }

    private function validated(Request $request, ?MonitoredService $service = null): array
    {
        return $request->validate([
            'label' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'service_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('monitored_services')
                    ->where('host', $request->input('host'))
                    ->ignore($service?->id),
            ],
        ]);
    }
}

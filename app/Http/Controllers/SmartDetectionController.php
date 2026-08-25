<?php

namespace App\Http\Controllers;

use App\Models\SmartDetectionFinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SmartDetectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('smart-detection/index', [
            'categories' => SmartDetectionFinding::CATEGORIES,
        ]);
    }

    /**
     * Every finding (default: open + acknowledged, i.e. not resolved),
     * newest first, each annotated with its VM's name/IP. Filterable by
     * status/category/severity — the frontend polls this for the live
     * findings table.
     */
    public function findings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'acknowledged', 'resolved', 'all'])],
            'category' => ['nullable', Rule::in(array_keys(SmartDetectionFinding::CATEGORIES))],
            'severity' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
        ]);

        $status = $validated['status'] ?? null;

        $query = SmartDetectionFinding::with('vm:id,name,ip')
            ->orderByDesc('last_detected_at');

        if ($status === null) {
            $query->where('status', '!=', SmartDetectionFinding::STATUS_RESOLVED);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        $findings = $query->get()->map(fn (SmartDetectionFinding $finding) => [
            'id' => $finding->id,
            'vm' => [
                'id' => $finding->vm?->id,
                'name' => $finding->vm?->name ?? '(deleted VM)',
                'ip' => $finding->vm?->ip,
            ],
            'category' => $finding->category,
            'category_label' => SmartDetectionFinding::CATEGORIES[$finding->category] ?? $finding->category,
            'severity' => $finding->severity,
            'title' => $finding->title,
            'detail' => $finding->detail,
            'status' => $finding->status,
            'first_detected_at' => $finding->first_detected_at->toIso8601String(),
            'last_detected_at' => $finding->last_detected_at->toIso8601String(),
            'acknowledged_at' => $finding->acknowledged_at?->toIso8601String(),
            'resolved_at' => $finding->resolved_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $findings]);
    }

    /**
     * Just the count of currently unacknowledged (status "open") findings
     * — for the sidebar's notification badge, same pattern as the Alarm
     * Notification badge (see app-sidebar.tsx). Cheap enough to poll from
     * every page without the cost of the full findings list.
     */
    public function openCount(): JsonResponse
    {
        return response()->json([
            'data' => SmartDetectionFinding::open()->count(),
        ]);
    }

    public function acknowledge(SmartDetectionFinding $finding): RedirectResponse
    {
        $finding->update([
            'status' => SmartDetectionFinding::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => Auth::id(),
        ]);

        return back()->with('success', 'Finding acknowledged.');
    }

    public function resolve(SmartDetectionFinding $finding): RedirectResponse
    {
        $finding->update([
            'status' => SmartDetectionFinding::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Finding resolved.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\ItAsset;
use App\Models\ItAssetCategory;
use App\Models\ItAssetInspection;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * รอบตรวจนับครุภัณฑ์ — an online counting round. Opening one snapshots every
 * in-scope asset into inventory_session_items; a scan/verify (here or on the
 * public page) flips its item to counted. Progress is always derived.
 */
class InventorySessionController extends Controller
{
    public function index(): Response
    {
        $sessions = InventorySession::with('startedBy:id,name', 'scopeCategory:id,name')
            ->withCount([
                'items',
                'items as counted_count' => fn ($q) => $q->where('counted', true),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (InventorySession $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->status,
                'status_label' => InventorySession::STATUSES[$s->status] ?? $s->status,
                'scope_category' => $s->scopeCategory?->name,
                'scope_location' => $s->scope_location,
                'total' => $s->items_count,
                'counted' => $s->counted_count,
                'started_by' => $s->startedBy?->name,
                'started_at' => $s->started_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
            ]);

        return Inertia::render('it-assets/counting/index', [
            'sessions' => $sessions,
            'categories' => ItAssetCategory::orderBy('name')->get(['id', 'name']),
            'locations' => ItAsset::query()->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scope_category_id' => 'nullable|exists:it_asset_categories,id',
            'scope_location' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $session = DB::transaction(function () use ($validated, $request) {
            $session = InventorySession::create([
                ...$validated,
                'status' => 'open',
                'started_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            // Snapshot the in-scope assets. Retired/lost assets are left
            // out — a counting round is about what should physically exist.
            $assetIds = ItAsset::query()
                ->whereNotIn('status', ['retired', 'lost'])
                ->when($validated['scope_category_id'] ?? null, fn ($q, $c) => $q->where('it_asset_category_id', $c))
                ->when($validated['scope_location'] ?? null, fn ($q, $l) => $q->where('location', $l))
                ->pluck('id');

            $now = now();
            InventorySessionItem::insert($assetIds->map(fn ($id) => [
                'inventory_session_id' => $session->id,
                'it_asset_id' => $id,
                'counted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $session;
        });

        $activityLogger->record(
            action: 'created',
            description: "เปิดรอบตรวจนับครุภัณฑ์ '{$session->name}' ({$session->items()->count()} รายการ)",
            subjectType: 'inventory_session',
            subjectLabel: $session->name,
        );

        return redirect()->route('it-asset-counting.show', $session)->with('success', 'เปิดรอบตรวจนับเรียบร้อยแล้ว');
    }

    public function show(InventorySession $inventorySession): Response
    {
        $inventorySession->load('startedBy:id,name', 'scopeCategory:id,name');

        $items = $inventorySession->items()
            ->with(['asset:id,asset_code,name,location,department,it_asset_category_id', 'asset.category:id,name'])
            ->get()
            ->map(fn (InventorySessionItem $i) => [
                'id' => $i->id,
                'asset_id' => $i->it_asset_id,
                'asset_code' => $i->asset?->asset_code,
                'name' => $i->asset?->name,
                'category' => $i->asset?->category?->name,
                'location' => $i->asset?->location,
                'department' => $i->asset?->department,
                'counted' => $i->counted,
                'status' => $i->status,
                'status_label' => $i->status ? (ItAssetInspection::STATUSES[$i->status] ?? $i->status) : null,
                'counted_by' => $i->counted_by_name,
                'counted_at' => $i->counted_at?->toIso8601String(),
            ]);

        $byStatus = $items->where('counted', true)->groupBy('status')->map->count();

        return Inertia::render('it-assets/counting/show', [
            'session' => [
                'id' => $inventorySession->id,
                'name' => $inventorySession->name,
                'status' => $inventorySession->status,
                'status_label' => InventorySession::STATUSES[$inventorySession->status] ?? $inventorySession->status,
                'scope_category' => $inventorySession->scopeCategory?->name,
                'scope_location' => $inventorySession->scope_location,
                'note' => $inventorySession->note,
                'started_by' => $inventorySession->startedBy?->name,
                'started_at' => $inventorySession->started_at?->toIso8601String(),
                'closed_at' => $inventorySession->closed_at?->toIso8601String(),
            ],
            'items' => $items->values(),
            'progress' => [
                'total' => $items->count(),
                'counted' => $items->where('counted', true)->count(),
                'by_status' => collect(ItAssetInspection::STATUSES)
                    ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($byStatus[$key] ?? 0)])
                    ->values(),
            ],
            'statuses' => ItAssetInspection::STATUSES,
        ]);
    }

    /**
     * Manual count from the round's board (a phone scan on the public page
     * updates the same item on its own — see ItAssetController::syncOpenCountingRounds).
     */
    public function count(Request $request, InventorySession $inventorySession): RedirectResponse
    {
        abort_if($inventorySession->status === 'closed', 422, 'รอบตรวจนับนี้ปิดแล้ว');

        $validated = $request->validate([
            'it_asset_id' => ['required', Rule::exists('inventory_session_items', 'it_asset_id')->where('inventory_session_id', $inventorySession->id)],
            'status' => ['required', Rule::in(array_keys(ItAssetInspection::STATUSES))],
            'note' => 'nullable|string|max:2000',
        ]);

        $item = $inventorySession->items()->where('it_asset_id', $validated['it_asset_id'])->firstOrFail();

        DB::transaction(function () use ($item, $inventorySession, $validated, $request) {
            $inspection = ItAssetInspection::create([
                'it_asset_id' => $item->it_asset_id,
                'inventory_session_id' => $inventorySession->id,
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
                'source' => 'counting',
                'inspected_by' => $request->user()->id,
            ]);

            $item->update([
                'counted' => true,
                'status' => $validated['status'],
                'it_asset_inspection_id' => $inspection->id,
                'counted_by' => $request->user()->id,
                'counted_by_name' => $request->user()->name,
                'counted_at' => now(),
            ]);

            ItAssetController::syncAssetFromInspection($item->asset, $inspection);
        });

        return back()->with('success', 'บันทึกการตรวจนับเรียบร้อยแล้ว');
    }

    public function close(InventorySession $inventorySession, ActivityLogger $activityLogger): RedirectResponse
    {
        if ($inventorySession->status !== 'closed') {
            $inventorySession->update(['status' => 'closed', 'closed_at' => now()]);

            $activityLogger->record(
                action: 'updated',
                description: "ปิดรอบตรวจนับครุภัณฑ์ '{$inventorySession->name}'",
                subjectType: 'inventory_session',
                subjectLabel: $inventorySession->name,
            );
        }

        return back()->with('success', 'ปิดรอบตรวจนับเรียบร้อยแล้ว');
    }
}

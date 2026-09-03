<?php

namespace App\Http\Controllers;

use App\Models\ItAsset;
use App\Models\ItAssetInspection;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login-free asset page. A QR sticker points at /asset/{key}; scanning it
 * lands here. Anyone can see basic identifying info and record a
 * verify/count check (พบ/ปกติ · ชำรุด · ย้าย · ไม่พบ) with a photo and note.
 * Each check is one immutable it_asset_inspections row.
 *
 * {key} is either the unguessable public_token (what the QR encodes) or the
 * printed asset_code (e.g. NB-0002) — the latter so someone doing a count
 * with a broken camera can just type the number on the sticker.
 */
class PublicAssetController extends Controller
{
    public function show(string $key): Response
    {
        $asset = $this->resolve($key);
        $asset->load('category:id,name');

        return Inertia::render('asset/public', [
            // Basic info only — no price / serial / owner / notes.
            'asset' => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'category' => $asset->category?->name,
                'brand' => $asset->brand,
                'model' => $asset->model,
                'department' => $asset->department,
                'location' => $asset->location,
                'status_label' => $asset->statusLabel(),
                'photo_url' => $asset->photo_path ? Storage::disk('public')->url($asset->photo_path) : null,
                'last_inspected_at' => $asset->last_inspected_at?->toIso8601String(),
                'last_inspection_status' => $asset->last_inspection_status
                    ? (ItAssetInspection::STATUSES[$asset->last_inspection_status] ?? $asset->last_inspection_status)
                    : null,
            ],
            // The inspect POST goes back to this same {key}, so echo what
            // was actually used (token or code).
            'token' => $key,
            'statuses' => ItAssetInspection::STATUSES,
            'currentUserName' => Auth::user()?->name,
        ]);
    }

    public function inspect(Request $request, string $key, ActivityLogger $activityLogger): JsonResponse
    {
        $asset = $this->resolve($key);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ItAssetInspection::STATUSES))],
            'note' => 'nullable|string|max:2000',
            'inspector_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|max:5120',
        ]);

        $user = $request->user();

        $inspection = $asset->inspections()->create([
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'source' => 'public',
            'inspected_by' => $user?->id,
            'inspector_name' => $user?->name ?? ($validated['inspector_name'] ?? null),
        ]);

        foreach ((array) $request->file('photos', []) as $file) {
            $inspection->photos()->create([
                'path' => $file->store('it-asset-inspections', 'public'),
            ]);
        }

        ItAssetController::syncAssetFromInspection($asset, $inspection);
        ItAssetController::syncOpenCountingRounds(
            $asset,
            $inspection,
            $user?->id,
            $inspection->inspector_name,
        );

        $activityLogger->record(
            action: 'updated',
            description: "ตรวจสอบครุภัณฑ์ (public) {$asset->asset_code} — {$inspection->statusLabel()}",
            subjectType: 'it_asset',
            subjectLabel: $asset->asset_code,
            userId: $user?->id,
        );

        return response()->json([
            'ok' => true,
            'status_label' => $inspection->statusLabel(),
            'saved_at' => $inspection->created_at->toIso8601String(),
            'inspector' => $inspection->inspector_name,
        ]);
    }

    /**
     * Resolve an asset by its public_token or its printed asset_code.
     * Grouped so the SoftDeletes scope still applies across the OR.
     */
    private function resolve(string $key): ItAsset
    {
        return ItAsset::where(
            fn ($q) => $q->where('public_token', $key)->orWhere('asset_code', $key)
        )->firstOrFail();
    }
}

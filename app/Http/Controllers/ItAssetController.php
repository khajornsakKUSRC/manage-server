<?php

namespace App\Http\Controllers;

use App\Models\InventorySessionItem;
use App\Models\ItAsset;
use App\Models\ItAssetCategory;
use App\Models\ItAssetInspection;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ครุภัณฑ์ไอที — the asset master (register once) plus its QR label,
 * inspection history, and Excel/PDF export. The public verify/count flow
 * lives in PublicAssetController; counting rounds in InventorySessionController.
 */
class ItAssetController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $assets = $this->filtered($request)
            ->with('category:id,name')
            ->orderBy('asset_code')
            ->get()
            ->map(fn (ItAsset $a) => $this->present($a));

        return Inertia::render('it-assets/index', [
            'assets' => $assets,
            'filters' => $filters,
            'categories' => ItAssetCategory::withCount('assets')->orderBy('name')->get(['id', 'name', 'code_prefix']),
            'statuses' => ItAsset::STATUSES,
            'locations' => ItAsset::query()->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'departments' => ItAsset::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'stats' => $this->stats(),
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validateAsset($request);

        $asset = new ItAsset($validated);
        $asset->created_by = $request->user()->id;

        if ($request->hasFile('photo')) {
            $asset->photo_path = $request->file('photo')->store('it-assets', 'public');
        }

        // The column is NOT NULL and unique, so a blank code needs a
        // placeholder for the first insert, then the real running number
        // (<category prefix>-0001, or AST-0001) once the id exists.
        $autoCode = blank($validated['asset_code'] ?? null);
        $asset->asset_code = $autoCode ? 'TMP-'.Str::random(24) : $validated['asset_code'];
        $asset->save();

        if ($autoCode) {
            $prefix = $asset->category?->code_prefix ?: 'AST';
            $asset->asset_code = $prefix.'-'.str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT);
            $asset->saveQuietly();
        }

        $activityLogger->record(
            action: 'created',
            description: "เพิ่มครุภัณฑ์ {$asset->asset_code} ({$asset->name})",
            subjectType: 'it_asset',
            subjectLabel: $asset->asset_code,
        );

        return redirect()->route('it-assets.show', $asset)->with('success', 'บันทึกครุภัณฑ์เรียบร้อยแล้ว');
    }

    public function show(Request $request, ItAsset $itAsset): Response
    {
        $itAsset->load([
            'category:id,name',
            'createdBy:id,name',
            'inspections.photos',
            'inspections.inspectedBy:id,name',
            'maintenances.createdBy:id,name',
            'assignments.createdBy:id,name',
            'software',
        ]);

        return Inertia::render('it-assets/show', [
            'asset' => $this->presentDetail($itAsset),
            'inspectionStatuses' => ItAssetInspection::STATUSES,
            'publicUrl' => route('asset.public', $itAsset->public_token),
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    public function update(Request $request, ItAsset $itAsset, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validateAsset($request, $itAsset->id);

        if ($request->boolean('remove_photo') && $itAsset->photo_path) {
            Storage::disk('public')->delete($itAsset->photo_path);
            $itAsset->photo_path = null;
        }

        if ($request->hasFile('photo')) {
            if ($itAsset->photo_path) {
                Storage::disk('public')->delete($itAsset->photo_path);
            }
            $itAsset->photo_path = $request->file('photo')->store('it-assets', 'public');
        }

        $itAsset->fill($validated);
        $itAsset->asset_code = $validated['asset_code'] ?: $itAsset->asset_code;
        $itAsset->save();

        $activityLogger->record(
            action: 'updated',
            description: "แก้ไขครุภัณฑ์ {$itAsset->asset_code} ({$itAsset->name})",
            subjectType: 'it_asset',
            subjectLabel: $itAsset->asset_code,
        );

        return back()->with('success', 'อัปเดตครุภัณฑ์เรียบร้อยแล้ว');
    }

    public function destroy(ItAsset $itAsset, ActivityLogger $activityLogger): RedirectResponse
    {
        $code = $itAsset->asset_code;
        $itAsset->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "ลบครุภัณฑ์ {$code}",
            subjectType: 'it_asset',
            subjectLabel: $code,
        );

        return redirect()->route('it-assets.index')->with('success', 'ลบครุภัณฑ์เรียบร้อยแล้ว');
    }

    /**
     * A staff member records a check straight from the asset detail page.
     * Same shape as the public flow — every check is one immutable row.
     */
    public function storeInspection(Request $request, ItAsset $itAsset, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ItAssetInspection::STATUSES))],
            'note' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|max:5120',
        ]);

        $inspection = $itAsset->inspections()->create([
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
            'source' => 'staff',
            'inspected_by' => $request->user()->id,
        ]);

        $this->storePhotos($request, $inspection);
        self::syncAssetFromInspection($itAsset, $inspection);
        self::syncOpenCountingRounds($itAsset, $inspection, $request->user()->id, $request->user()->name);

        $activityLogger->record(
            action: 'updated',
            description: "ตรวจสอบครุภัณฑ์ {$itAsset->asset_code} — {$inspection->statusLabel()}",
            subjectType: 'it_asset',
            subjectLabel: $itAsset->asset_code,
        );

        return back()->with('success', 'บันทึกผลการตรวจสอบเรียบร้อยแล้ว');
    }

    // ── QR label ────────────────────────────────────────────────────────

    public function label(ItAsset $itAsset): Response
    {
        return Inertia::render('it-assets/label', [
            'assets' => [$this->presentLabel($itAsset)],
        ]);
    }

    public function labels(Request $request): Response
    {
        $assets = $this->filtered($request)
            ->with('category:id,name')
            ->orderBy('asset_code')
            ->limit(500)
            ->get()
            ->map(fn (ItAsset $a) => $this->presentLabel($a));

        return Inertia::render('it-assets/label', ['assets' => $assets]);
    }

    public function scan(): Response
    {
        return Inertia::render('it-assets/scan');
    }

    // ── Export ──────────────────────────────────────────────────────────

    public function export(Request $request): HttpResponse|StreamedResponse
    {
        $assets = $this->filtered($request)->with('category:id,name')->orderBy('asset_code')->get();
        $stamp = now()->format('Ymd-Hi');

        $headers = ['รหัสครุภัณฑ์', 'ชื่อครุภัณฑ์', 'หมวดหมู่', 'ยี่ห้อ', 'รุ่น', 'Serial No.', 'สถานะ', 'หน่วยงาน', 'สถานที่', 'ผู้ครอบครอง', 'วันที่ได้มา', 'ตรวจสอบล่าสุด'];
        $rows = $assets->map(fn (ItAsset $a) => [
            $a->asset_code,
            $a->name,
            $a->category?->name,
            $a->brand,
            $a->model,
            $a->serial_number,
            $a->statusLabel(),
            $a->department,
            $a->location,
            $a->assigned_to,
            $a->purchased_at?->format('d/m/Y'),
            $a->last_inspected_at?->format('d/m/Y H:i'),
        ])->all();

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('pdf.it-asset-registry', [
                'headers' => $headers,
                'rows' => $rows,
                'generatedAt' => now()->format('d/m/Y H:i'),
                'regularFontPath' => public_path('fonts/sarabun/Sarabun-Regular.ttf'),
                'boldFontPath' => public_path('fonts/sarabun/Sarabun-Bold.ttf'),
            ])->setPaper('a4', 'landscape')->download("it-assets-{$stamp}.pdf");
        }

        return $this->xlsx("it-assets-{$stamp}.xlsx", $headers, $rows);
    }

    // ── Categories ──────────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:it_asset_categories,name',
            'code_prefix' => 'nullable|string|max:20',
        ]);

        ItAssetCategory::create($validated);

        return back()->with('success', 'เพิ่มหมวดหมู่เรียบร้อยแล้ว');
    }

    public function updateCategory(Request $request, ItAssetCategory $itAssetCategory): RedirectResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('it_asset_categories', 'name')->ignore($itAssetCategory->id)],
            'code_prefix' => 'nullable|string|max:20',
        ]);

        $itAssetCategory->update($validated);

        return back()->with('success', 'อัปเดตหมวดหมู่เรียบร้อยแล้ว');
    }

    public function destroyCategory(Request $request, ItAssetCategory $itAssetCategory): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($itAssetCategory->assets()->exists()) {
            return back()->with('error', 'หมวดหมู่นี้มีครุภัณฑ์อยู่ ไม่สามารถลบได้');
        }

        $itAssetCategory->delete();

        return back()->with('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    }

    // ── Shared helpers (also used by other asset controllers) ───────────

    /**
     * Copy the check's outcome onto the asset row for fast listing/filtering.
     */
    public static function syncAssetFromInspection(ItAsset $asset, ItAssetInspection $inspection): void
    {
        $asset->forceFill([
            'last_inspected_at' => $inspection->created_at ?? now(),
            'last_inspection_status' => $inspection->status,
        ])->saveQuietly();
    }

    /**
     * Any open counting round that has this asset in its snapshot gets the
     * item flipped to counted and linked to the check just recorded — so
     * "scan → verify → save" advances the round with no extra step.
     */
    public static function syncOpenCountingRounds(ItAsset $asset, ItAssetInspection $inspection, ?int $userId, ?string $userName): void
    {
        InventorySessionItem::query()
            ->where('it_asset_id', $asset->id)
            ->whereHas('session', fn ($q) => $q->where('status', 'open'))
            ->get()
            ->each(function (InventorySessionItem $item) use ($inspection, $userId, $userName): void {
                $item->update([
                    'counted' => true,
                    'status' => $inspection->status,
                    'it_asset_inspection_id' => $inspection->id,
                    'counted_by' => $userId,
                    'counted_by_name' => $userName ?? $inspection->inspector_name,
                    'counted_at' => now(),
                ]);

                // Tie the check back to the round it advanced (first wins if
                // the asset somehow sits in more than one open round).
                if ($inspection->inventory_session_id === null) {
                    $inspection->update(['inventory_session_id' => $item->inventory_session_id]);
                }
            });
    }

    public function storePhotos(Request $request, ItAssetInspection $inspection): void
    {
        foreach ((array) $request->file('photos', []) as $file) {
            $inspection->photos()->create([
                'path' => $file->store('it-asset-inspections', 'public'),
            ]);
        }
    }

    // ── Internals ──────────────────────────────────────────────────────

    /** @return Builder<ItAsset> */
    private function filtered(Request $request)
    {
        [$q, $category, $status, $location, $department] = array_values($this->filters($request));

        return ItAsset::query()
            ->when($q, fn ($b) => $b->where(fn ($w) => $w
                ->where('asset_code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('serial_number', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%")
                ->orWhere('model', 'like', "%{$q}%")
                ->orWhere('assigned_to', 'like', "%{$q}%")))
            ->when($category, fn ($b) => $b->where('it_asset_category_id', $category))
            ->when($status, fn ($b) => $b->where('status', $status))
            ->when($location, fn ($b) => $b->where('location', $location))
            ->when($department, fn ($b) => $b->where('department', $department));
    }

    /** @return array{q: string|null, category: int|null, status: string|null, location: string|null, department: string|null} */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q')) ?: null,
            'category' => $request->integer('category') ?: null,
            'status' => array_key_exists((string) $request->query('status'), ItAsset::STATUSES) ? $request->query('status') : null,
            'location' => trim((string) $request->query('location')) ?: null,
            'department' => trim((string) $request->query('department')) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $byStatus = ItAsset::query()->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $staleBefore = now()->subMonths(6);

        return [
            'total' => (int) $byStatus->sum(),
            'by_status' => collect(ItAsset::STATUSES)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'count' => (int) ($byStatus[$key] ?? 0),
            ])->values(),
            'never_inspected' => ItAsset::query()->whereNull('last_inspected_at')->count(),
            'inspected_6m' => ItAsset::query()->where('last_inspected_at', '>=', $staleBefore)->count(),
            'damaged_or_missing' => ItAsset::query()->whereIn('last_inspection_status', ['damaged', 'missing'])->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(ItAsset $a): array
    {
        return [
            'id' => $a->id,
            'asset_code' => $a->asset_code,
            'name' => $a->name,
            'category' => $a->category?->name,
            'brand' => $a->brand,
            'model' => $a->model,
            'serial_number' => $a->serial_number,
            'status' => $a->status,
            'status_label' => $a->statusLabel(),
            'department' => $a->department,
            'location' => $a->location,
            'assigned_to' => $a->assigned_to,
            'last_inspected_at' => $a->last_inspected_at?->toIso8601String(),
            'last_inspection_status' => $a->last_inspection_status,
            'photo_url' => $a->photo_path ? Storage::disk('public')->url($a->photo_path) : null,
            'public_url' => route('asset.public', $a->public_token),
            // Editable fields, so the registry's edit dialog can open
            // pre-filled without a second round-trip.
            'category_id' => $a->it_asset_category_id,
            'purchased_at' => $a->purchased_at?->toDateString(),
            'price' => $a->price,
            'warranty_until' => $a->warranty_until?->toDateString(),
            'notes' => $a->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function presentDetail(ItAsset $a): array
    {
        return [
            ...$this->present($a),
            'serial_number' => $a->serial_number,
            'purchased_at' => $a->purchased_at?->toDateString(),
            'price' => $a->price,
            'warranty_until' => $a->warranty_until?->toDateString(),
            'notes' => $a->notes,
            'category_id' => $a->it_asset_category_id,
            'created_by' => $a->createdBy?->name,
            'created_at' => $a->created_at?->toIso8601String(),
            'inspections' => $a->inspections->map(fn (ItAssetInspection $i) => [
                'id' => $i->id,
                'status' => $i->status,
                'status_label' => $i->statusLabel(),
                'note' => $i->note,
                'source' => $i->source,
                'inspector' => $i->inspectedBy?->name ?? $i->inspector_name,
                'created_at' => $i->created_at?->toIso8601String(),
                'photos' => $i->photos->map(fn ($p) => Storage::disk('public')->url($p->path))->all(),
            ])->all(),
            'maintenances' => $a->maintenances->map(fn ($m) => [
                'id' => $m->id, 'type' => $m->type, 'title' => $m->title,
                'vendor' => $m->vendor, 'cost' => $m->cost, 'status' => $m->status,
                'performed_at' => $m->performed_at?->toDateString(), 'by' => $m->createdBy?->name,
            ])->all(),
            'assignments' => $a->assignments->map(fn ($x) => [
                'id' => $x->id, 'assignee_name' => $x->assignee_name, 'department' => $x->department,
                'location' => $x->location, 'assigned_at' => $x->assigned_at?->toDateString(),
                'returned_at' => $x->returned_at?->toDateString(), 'note' => $x->note,
            ])->all(),
            'software' => $a->software->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'version' => $s->version,
                'license_type' => $s->license_type, 'seats' => $s->seats,
                'expires_at' => $s->expires_at?->toDateString(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentLabel(ItAsset $a): array
    {
        return [
            'asset_code' => $a->asset_code,
            'name' => $a->name,
            'category' => $a->category?->name,
            'department' => $a->department,
            'location' => $a->location,
            'public_url' => route('asset.public', $a->public_token),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function xlsx(string $filename, array $headers, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ครุภัณฑ์ไอที');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateAsset(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'asset_code' => ['nullable', 'string', 'max:255', Rule::unique('it_assets', 'asset_code')->ignore($ignoreId)->whereNull('deleted_at')],
            'name' => 'required|string|max:255',
            'it_asset_category_id' => 'nullable|exists:it_asset_categories,id',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(array_keys(ItAsset::STATUSES))],
            'department' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|string|max:255',
            'purchased_at' => 'nullable|date',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'warranty_until' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|max:5120',
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->is_admin, 403);
    }
}

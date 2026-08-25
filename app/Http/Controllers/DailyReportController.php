<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\DailyReportIncident;
use App\Models\DailyReportItem;
use App\Services\ActivityLogger;
use App\Services\DailyReportPdfService;
use App\Services\DailyReportTelegramService;
use App\Services\DailyReportVsphereService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class DailyReportController extends Controller
{
    public function index(Request $request): Response
    {
        $availableDates = DailyReport::orderByDesc('report_date')->pluck('report_date')
            ->map(fn ($date) => $date->format('Y-m-d'));

        $selectedDate = $request->query('date', $availableDates->first() ?? now()->format('Y-m-d'));

        $report = DailyReport::with(['items', 'incidents'])
            ->whereDate('report_date', $selectedDate)
            ->first();

        return Inertia::render('daily-reports/index', [
            'availableDates' => $availableDates,
            'selectedDate' => $selectedDate,
            'report' => $report,
            'incidentOptions' => DailyReport::INCIDENTS,
            'actionOptions' => DailyReport::ACTIONS,
        ]);
    }

    /**
     * Live pull from vCenter (name, host, power state, allocated vCPU/RAM,
     * guest disk usage, uptime) for every VM. Nothing is saved here — this
     * only feeds the auto-filled table for the user to review before saving.
     */
    public function pull(DailyReportVsphereService $vsphereReportService): JsonResponse
    {
        try {
            $items = $vsphereReportService->pull();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'ไม่สามารถดึงข้อมูลจาก vCenter ได้'], 502);
        }

        return response()->json(['items' => $items]);
    }

    public function store(Request $request, DailyReportTelegramService $telegramService, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'report_date' => 'required|date_format:Y-m-d',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.host' => 'nullable|string',
            'items.*.power_state' => 'nullable|string',
            'items.*.cpu_count' => 'nullable|integer',
            'items.*.memory_gb' => 'nullable|numeric',
            'items.*.disk_usage_pct' => 'nullable|numeric',
            'items.*.uptime_seconds' => 'nullable|integer',
            'items.*.certificate_exp' => 'nullable|string|max:255',
            'items.*.notes' => 'nullable|string',
            'incidents' => 'nullable|array',
            'incidents.*.vm_name' => 'nullable|string',
            'incidents.*.incident' => ['nullable', Rule::in(array_keys(DailyReport::INCIDENTS))],
            'incidents.*.action' => ['nullable', Rule::in(array_keys(DailyReport::ACTIONS))],
            'incidents.*.remark' => 'nullable|string|max:2000',
        ]);

        $report = DB::transaction(function () use ($validated) {
            $report = DailyReport::updateOrCreate(
                ['report_date' => $validated['report_date']],
                ['created_by' => Auth::id()]
            );

            $report->items()->delete();

            foreach (array_chunk($validated['items'], 500) as $chunk) {
                $chunk = array_map(fn ($item) => [
                    'daily_report_id' => $report->id,
                    'name' => $item['name'],
                    'host' => $item['host'] ?? null,
                    'power_state' => $item['power_state'] ?? null,
                    'cpu_count' => $item['cpu_count'] ?? null,
                    'memory_gb' => $item['memory_gb'] ?? null,
                    'disk_usage_pct' => $item['disk_usage_pct'] ?? null,
                    'uptime_seconds' => $item['uptime_seconds'] ?? null,
                    'certificate_exp' => $item['certificate_exp'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk);

                DailyReportItem::insert($chunk);
            }

            $report->incidents()->delete();

            $incidents = array_filter(
                $validated['incidents'] ?? [],
                fn ($entry) => filled($entry['vm_name'] ?? null)
                    || filled($entry['incident'] ?? null)
                    || filled($entry['action'] ?? null)
                    || filled($entry['remark'] ?? null),
            );

            foreach ($incidents as $entry) {
                DailyReportIncident::create([
                    'daily_report_id' => $report->id,
                    'vm_name' => $entry['vm_name'] ?? null,
                    'incident' => $entry['incident'] ?? null,
                    'action' => $entry['action'] ?? null,
                    'remark' => $entry['remark'] ?? null,
                ]);
            }

            return $report;
        });

        $activityLogger->record(
            action: $report->wasRecentlyCreated ? 'created' : 'updated',
            description: ($report->wasRecentlyCreated ? 'Created' : 'Updated')." daily report for {$validated['report_date']}",
            subjectType: 'daily_report',
            subjectLabel: $validated['report_date'],
        );

        // Best-effort — a failed Telegram send never blocks the save that
        // already succeeded above.
        $telegramService->send($report->load(['items', 'incidents']));

        return redirect()->route('daily-reports.index', ['date' => $validated['report_date']]);
    }

    /**
     * Exports every saved report within the given date range as one PDF,
     * a page per date (dates with no saved report are skipped).
     */
    public function export(Request $request, DailyReportPdfService $pdfService): HttpResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $reports = DailyReport::with(['items', 'incidents'])
            ->whereBetween('report_date', [$validated['start_date'], $validated['end_date']])
            ->orderBy('report_date')
            ->get();

        if ($reports->isEmpty()) {
            return back()->withErrors(['start_date' => 'ไม่พบรายงานที่บันทึกไว้ในช่วงวันที่ที่เลือก']);
        }

        $pages = $reports->map(fn (DailyReport $report) => $pdfService->buildData($report));

        $pdf = Pdf::loadView('pdf.daily-report', [
            'pages' => $pages,
            'generatedAt' => now()->translatedFormat('d/m/Y H:i'),
            'regularFontPath' => public_path('fonts/sarabun/Sarabun-Regular.ttf'),
            'boldFontPath' => public_path('fonts/sarabun/Sarabun-Bold.ttf'),
        ])->setPaper('a4', 'portrait');

        $filename = $validated['start_date'] === $validated['end_date']
            ? "daily-vm-report-{$validated['start_date']}.pdf"
            : "daily-vm-report-{$validated['start_date']}_to_{$validated['end_date']}.pdf";

        return $pdf->download($filename);
    }
}

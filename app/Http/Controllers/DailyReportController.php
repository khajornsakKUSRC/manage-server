<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Services\DailyReportImportService;
use App\Services\DailyReportPdfService;
use App\Services\DailyReportSummaryService;
use App\Services\DailyReportVsphereService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $selectedDate = $request->query('date', $availableDates->first());

        $report = null;

        if ($selectedDate) {
            $report = DailyReport::with('items')
                ->whereDate('report_date', $selectedDate)
                ->first();

            if ($report && ! $report->summary) {
                $report->update(['summary' => app(DailyReportSummaryService::class)->summarize($report)]);
            }
        }

        return Inertia::render('daily-reports/index', [
            'availableDates' => $availableDates,
            'selectedDate' => $selectedDate,
            'report' => $report,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $report = DailyReport::with('items')->whereDate('report_date', $validated['date'])->first();

        if ($report) {
            $report->update(['summary' => app(DailyReportSummaryService::class)->summarize($report)]);
        }

        return redirect()->route('daily-reports.index', ['date' => $validated['date']]);
    }

    public function create(): Response
    {
        return Inertia::render('daily-reports/import');
    }

    public function store(Request $request, DailyReportImportService $importService): RedirectResponse
    {
        $validated = $request->validate([
            'report_date' => 'required|date_format:Y-m-d',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $importService->import($validated['file'], $validated['report_date'], Auth::id());
        } catch (Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'นำเข้าข้อมูลรายงานประจำวันสำเร็จ']);

        return redirect()->route('daily-reports.index', ['date' => $validated['report_date']]);
    }

    public function export(Request $request, DailyReportPdfService $pdfService): HttpResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'inspector' => 'nullable|string|max:255',
        ]);

        $report = DailyReport::with('items')
            ->whereDate('report_date', $validated['date'])
            ->firstOrFail();

        $data = $pdfService->buildData($report);

        // A report reviewed/saved through the vSphere flow carries the
        // human-ticked checklist and verdict — prefer those over the
        // freshly recomputed ones so the PDF matches what was saved.
        if ($report->checklist !== null) {
            $data['checklist'] = $report->checklist;
        }

        if ($report->overall_normal !== null) {
            $data['overallNormal'] = $report->overall_normal;
        }

        if ($report->reason_text !== null) {
            $data['reasonText'] = $report->reason_text;
        }

        $data['inspector'] = $validated['inspector'] ?: ($report->inspector ?? '');
        $data['reportDate'] = $report->report_date->translatedFormat('d/m/Y');
        $data['generatedAt'] = now()->translatedFormat('d/m/Y H:i');
        $data['regularFontPath'] = public_path('fonts/sarabun/Sarabun-Regular.ttf');
        $data['boldFontPath'] = public_path('fonts/sarabun/Sarabun-Bold.ttf');

        $pdf = Pdf::loadView('pdf.daily-report', $data)->setPaper('a4', 'portrait');

        return $pdf->download("daily-vm-report-{$validated['date']}.pdf");
    }

    public function vspherePreview(Request $request, DailyReportVsphereService $vsphereReportService): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $data = $vsphereReportService->preview($validated['date']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'ไม่สามารถดึงข้อมูลจาก vCenter ได้'], 502);
        }

        return response()->json($data);
    }

    public function vsphereSave(Request $request, DailyReportImportService $importService): RedirectResponse
    {
        $validated = $request->validate([
            'report_date' => 'required|date_format:Y-m-d',
            'inspector' => 'nullable|string|max:255',
            'overall_normal' => 'required|boolean',
            'reason_text' => 'nullable|string',
            'checklist' => 'required|array|min:1',
            'checklist.*.label' => 'required|string',
            'checklist.*.checked' => 'required|boolean',
            'checklist.*.derivable' => 'required|boolean',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.host' => 'nullable|string',
            'items.*.dns' => 'nullable|string',
            'items.*.state' => 'nullable|string',
            'items.*.status' => 'nullable|string',
            'items.*.provisioned_space' => 'nullable|string',
            'items.*.used_space' => 'nullable|string',
            'items.*.host_cpu' => 'nullable|string',
            'items.*.host_mem' => 'nullable|string',
        ]);

        $importService->persist(
            $validated['items'],
            $validated['report_date'],
            Auth::id(),
            [
                'inspector' => $validated['inspector'] ?? null,
                'checklist' => $validated['checklist'],
                'overall_normal' => $validated['overall_normal'],
                'reason_text' => $validated['reason_text'] ?? null,
                'source' => 'vsphere',
            ]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกรายงานประจำวันสำเร็จ']);

        return redirect()->route('daily-reports.index', ['date' => $validated['report_date']]);
    }
}

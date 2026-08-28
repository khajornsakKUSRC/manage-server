<?php

namespace App\Http\Controllers;

use App\Services\RadiusLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RadiusController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('kuwin-radius/index', [
            'host' => config('services.radius.host'),
        ]);
    }

    /**
     * Fetches the last 50 auth lines from the KUWIN Radius log over SSH,
     * optionally filtered by username, MAC address, and/or client (NAS).
     */
    public function logs(Request $request, RadiusLogService $logService): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'nullable|string|max:255',
            'mac' => 'nullable|string|max:255',
            'client' => 'nullable|string|max:255',
        ]);

        try {
            $entries = $logService->fetch(
                $validated['username'] ?? null,
                $validated['mac'] ?? null,
                $validated['client'] ?? null,
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: 'ไม่สามารถอ่าน log จาก Radius server ได้'], 502);
        }

        return response()->json([
            'data' => array_map(fn (array $entry) => [
                'time' => $entry['time']->toIso8601String(),
                'request_id' => $entry['request_id'],
                'status' => $entry['status'],
                'status_ok' => $entry['status_ok'],
                'username' => $entry['username'],
                'auth_type' => $entry['auth_type'],
                'client' => $entry['client'],
                'port' => $entry['port'],
                'mac' => $entry['mac'],
            ], $entries),
        ]);
    }

    /**
     * Exports every auth line within the given (inclusive) date range —
     * capped at RadiusLogService::MAX_EXPORT_DAYS days and
     * ::MAX_EXPORT_ROWS rows — to an .xlsx file, optionally narrowed by
     * the same username/MAC/client filters as the live view. Triggered by
     * a plain browser navigation (not a fetch), same as the Daily
     * Report's PDF export, so the browser handles the download natively
     * and a failure can just redirect back with a flashed error.
     */
    public function export(Request $request, RadiusLogService $logService): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'username' => 'nullable|string|max:255',
            'mac' => 'nullable|string|max:255',
            'client' => 'nullable|string|max:255',
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay();

        if ($from->diffInDays($to) >= RadiusLogService::MAX_EXPORT_DAYS) {
            return back()->withErrors(['to' => 'เลือกช่วงวันที่ได้ไม่เกิน '.RadiusLogService::MAX_EXPORT_DAYS.' วัน']);
        }

        // Parsing tens of thousands of rows (each holding a Carbon
        // instance) and then building a spreadsheet from them is
        // meaningfully heavier than the live view's 50-row default —
        // give this request more headroom than the default limits.
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        try {
            $entries = $logService->fetchRange(
                $from,
                $to,
                $validated['username'] ?? null,
                $validated['mac'] ?? null,
                $validated['client'] ?? null,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['from' => $e->getMessage() ?: 'ไม่สามารถอ่าน log จาก Radius server ได้']);
        }

        if (empty($entries)) {
            return back()->withErrors(['from' => 'ไม่พบรายการ login ในช่วงวันที่ (และตัวกรอง) ที่เลือก']);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KUWIN Radius Log');

        $headers = ['Time', 'Status', 'Username', 'Auth Type', 'Client', 'Port', 'MAC', 'Request ID'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;

        foreach ($entries as $entry) {
            // Explicit TYPE_STRING on anything that looks numeric (MAC,
            // port, phone-number-like usernames, request id) — Excel
            // would otherwise "helpfully" reformat/truncate them as
            // numbers (e.g. dropping a leading zero, or rendering a MAC
            // in scientific notation).
            $sheet->setCellValueExplicit("A{$row}", $entry['time']->format('Y-m-d H:i:s'), DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $entry['status']);
            $sheet->setCellValueExplicit("C{$row}", $entry['username'] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("D{$row}", $entry['auth_type'] ?? '');
            $sheet->setCellValue("E{$row}", $entry['client'] ?? '');
            $sheet->setCellValueExplicit("F{$row}", $entry['port'] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$row}", $entry['mac'] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$row}", $entry['request_id'], DataType::TYPE_STRING);
            $row++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = $validated['from'] === $validated['to']
            ? "kuwin-radius-{$validated['from']}.xlsx"
            : "kuwin-radius-{$validated['from']}_to_{$validated['to']}.xlsx";

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

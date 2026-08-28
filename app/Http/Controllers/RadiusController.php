<?php

namespace App\Http\Controllers;

use App\Services\RadiusLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
     * Fetches the last N auth lines (default 5) from the KUWIN Radius log
     * over SSH, optionally filtered by username, MAC address, client
     * (NAS), and/or status (e.g. "OK", "incorrect", "eap"). As soon as any
     * filter is set, the search covers the entire log (live + rotated),
     * not just the recent tail — see RadiusLogService::fetch().
     */
    public function logs(Request $request, RadiusLogService $logService): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'nullable|string|max:255',
            'mac' => 'nullable|string|max:255',
            'client' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'limit' => ['nullable', 'integer', Rule::in(RadiusLogService::ALLOWED_LIMITS)],
        ]);

        try {
            $entries = $logService->fetch(
                $validated['username'] ?? null,
                $validated['mac'] ?? null,
                $validated['client'] ?? null,
                $validated['status'] ?? null,
                $validated['limit'] ?? RadiusLogService::DEFAULT_LIMIT,
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
     * Exports every auth line within the given (inclusive) date+time
     * range — capped at RadiusLogService::MAX_EXPORT_MINUTES minutes (1
     * hour) and ::MAX_EXPORT_ROWS rows — to an .xlsx file, optionally
     * narrowed by the same username/MAC/client/status filters as the live
     * view. The 1-hour cap is deliberately tight: a wider range was tried
     * in production and brought the server down pulling too much data
     * over the SSH connection at once. Triggered by a plain browser
     * navigation (not a fetch), same as the Daily Report's PDF export, so
     * the browser handles the download natively and a failure can just
     * redirect back with a flashed error.
     */
    public function export(Request $request, RadiusLogService $logService): StreamedResponse|RedirectResponse
    {
        $validated = $request->validate([
            'from' => 'required|date_format:Y-m-d\TH:i',
            'to' => ['required', 'date_format:Y-m-d\TH:i', 'after:from'],
            'username' => 'nullable|string|max:255',
            'mac' => 'nullable|string|max:255',
            'client' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        $from = Carbon::createFromFormat('Y-m-d\TH:i', $validated['from']);
        $to = Carbon::createFromFormat('Y-m-d\TH:i', $validated['to']);

        if ($from->diffInMinutes($to) > RadiusLogService::MAX_EXPORT_MINUTES) {
            return back()->withErrors(['to' => 'เลือกช่วงเวลาได้ไม่เกิน '.RadiusLogService::MAX_EXPORT_MINUTES.' นาที เพื่อป้องกันไม่ให้ server ล่มจากการดึงข้อมูลจำนวนมากเกินไป']);
        }

        // Parsing tens of thousands of rows (each holding a Carbon
        // instance) and then building a spreadsheet from them is
        // meaningfully heavier than the live view's 50-row default —
        // give this request more headroom than the default limits.
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $truncated = false;

        try {
            $entries = $logService->fetchRange(
                $from,
                $to,
                $validated['username'] ?? null,
                $validated['mac'] ?? null,
                $validated['client'] ?? null,
                $validated['status'] ?? null,
                $truncated,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['from' => $e->getMessage() ?: 'ไม่สามารถอ่าน log จาก Radius server ได้']);
        }

        if (empty($entries)) {
            return back()->withErrors(['from' => 'ไม่พบรายการ login ในช่วงเวลา (และตัวกรอง) ที่เลือก']);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KUWIN Radius Log');

        $row = 1;

        // Traffic here is heavy enough that even the 1-hour cap can
        // exceed MAX_EXPORT_ROWS on a busy window — surfaced directly in
        // the file (rather than only in a flash message) since the
        // download is a plain browser navigation the user might not be
        // watching the page for.
        if ($truncated) {
            $sheet->setCellValue('A1', '⚠ ข้อมูลในช่วงเวลาที่เลือกมีปริมาณมาก แสดงเฉพาะ '.count($entries).' รายการแรกที่พบ อาจไม่ครบทั้งช่วงเวลา ลองแบ่งช่วงเวลาให้แคบลง');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->getColor()->setRGB('C00000');
            $row = 2;
        }

        $headers = ['Time', 'Status', 'Username', 'Auth Type', 'Client', 'Port', 'MAC', 'Request ID'];
        $sheet->fromArray($headers, null, "A{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

        $row++;

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

        $filename = 'kuwin-radius-'.$from->format('Y-m-d_Hi').'_to_'.$to->format('Y-m-d_Hi').'.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

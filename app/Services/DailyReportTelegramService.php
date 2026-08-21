<?php

namespace App\Services;

use App\Models\DailyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

class DailyReportTelegramService
{
    public function __construct(
        protected DailyReportPdfService $pdfService,
        protected TelegramNotifier $telegram,
    ) {}

    /**
     * Renders the given report as a PDF (same layout as the manual export)
     * and sends it as a Telegram document to the configured daily-report
     * chat. Best-effort — a failure here never blocks the save that
     * triggered it, it's just reported for visibility. Returns false
     * without attempting anything if the bot isn't configured yet.
     */
    public function send(DailyReport $report): bool
    {
        $botToken = config('services.telegram_daily_report.bot_token');
        $chatId = config('services.telegram_daily_report.chat_id');

        if (! $botToken || ! $chatId) {
            return false;
        }

        try {
            $pdf = Pdf::loadView('pdf.daily-report', [
                'pages' => [$this->pdfService->buildData($report)],
                'generatedAt' => now()->translatedFormat('d/m/Y H:i'),
                'regularFontPath' => public_path('fonts/sarabun/Sarabun-Regular.ttf'),
                'boldFontPath' => public_path('fonts/sarabun/Sarabun-Bold.ttf'),
            ])->setPaper('a4', 'portrait');

            $date = $report->report_date->format('Y-m-d');

            return $this->telegram->sendDocument(
                $botToken,
                $chatId,
                "daily-vm-report-{$date}.pdf",
                $pdf->output(),
                "Daily VM Report — {$date}",
            );
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}

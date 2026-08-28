<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Vm;
use Carbon\Carbon;
use Throwable;

class CertificateNotificationService
{
    public function __construct(
        protected TelegramNotifier $telegram,
    ) {}

    /**
     * Telegram-alerts on every active VM whose certificate_exp is within
     * its warning window — Vm::certificate_notify_days if the VM has its
     * own override set, otherwise Settings → Monitoring Thresholds →
     * Certificate Expiration Warning (days) — once per expiry date (see
     * Vm::certificate_notified_exp). Renewing a certificate changes
     * certificate_exp, so it's free to notify again the next time that new
     * date enters the window; the same unrenewed date is never re-notified
     * while it's already been warned about. Returns the number of
     * Telegram notifications sent.
     */
    public function checkAndNotify(): int
    {
        $defaultWarningDays = SystemSetting::current()->certificate_exp_warning_days;
        $today = Carbon::today();
        $sent = 0;

        $vms = Vm::active()
            ->whereNotNull('certificate_exp')
            ->get(['id', 'name', 'certificate_exp', 'certificate_notified_exp', 'certificate_notify_days']);

        foreach ($vms as $vm) {
            if ($vm->certificate_exp === $vm->certificate_notified_exp) {
                continue;
            }

            try {
                $expiry = Carbon::createFromFormat('Y-m-d', $vm->certificate_exp)->startOfDay();
            } catch (Throwable) {
                continue;
            }

            $daysUntil = $today->diffInDays($expiry, false);
            $warningDays = $vm->certificate_notify_days ?? $defaultWarningDays;

            if ($daysUntil > $warningDays) {
                continue;
            }

            $sentOk = $this->telegram->sendMessage(
                config('services.telegram.bot_token'),
                config('services.telegram.chat_id'),
                $this->formatMessage($vm->name, $vm->certificate_exp, $daysUntil),
            );

            if (! $sentOk) {
                continue;
            }

            $vm->update(['certificate_notified_exp' => $vm->certificate_exp]);
            $sent++;
        }

        return $sent;
    }

    private function formatMessage(string $vmName, string $certificateExp, int $daysUntil): string
    {
        if ($daysUntil < 0) {
            $daysAgo = abs($daysUntil);

            return "❌ <b>Certificate Expired</b>\n{$vmName} — expired {$certificateExp} ({$daysAgo} day(s) ago)";
        }

        if ($daysUntil === 0) {
            return "⚠️ <b>Certificate Expires Today</b>\n{$vmName} — expires {$certificateExp}";
        }

        return "⚠️ <b>Certificate Expiring Soon</b>\n{$vmName} — expires {$certificateExp} ({$daysUntil} day(s) left)";
    }
}

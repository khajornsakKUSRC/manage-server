<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Vm;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CertificateExpirationController extends Controller
{
    /**
     * Every VM with a certificate_exp set, newest-expiring first, each
     * annotated with its effective warning window (its own
     * certificate_notify_days override, or the site-wide default) and a
     * status derived from it — the same rule CertificateNotificationService
     * uses to decide when to Telegram-alert, surfaced here as a page
     * instead of waiting for the daily notification run.
     */
    public function index(): Response
    {
        $defaultWarningDays = SystemSetting::current()->certificate_exp_warning_days;
        $today = Carbon::today();

        $vms = Vm::with('host')
            ->whereNotNull('certificate_exp')
            ->where('certificate_exp', '!=', '')
            ->get()
            ->map(function (Vm $vm) use ($today, $defaultWarningDays) {
                $warningDays = $vm->certificate_notify_days ?? $defaultWarningDays;
                $daysUntil = null;
                $status = 'unknown';

                try {
                    $expiry = Carbon::createFromFormat('Y-m-d', $vm->certificate_exp)->startOfDay();
                    $daysUntil = (int) $today->diffInDays($expiry, false);
                    $status = match (true) {
                        $daysUntil < 0 => 'expired',
                        $daysUntil <= $warningDays => 'warning',
                        default => 'ok',
                    };
                } catch (Throwable) {
                    // Unparseable certificate_exp — status stays "unknown"
                    // rather than crashing the page over one bad row.
                }

                return [
                    'id' => $vm->id,
                    'name' => $vm->name,
                    'host' => $vm->host?->name,
                    'is_active' => $vm->is_active,
                    'certificate_exp' => $vm->certificate_exp,
                    'days_until' => $daysUntil,
                    'warning_days' => $warningDays,
                    'is_custom_warning_days' => $vm->certificate_notify_days !== null,
                    'status' => $status,
                ];
            })
            ->sortBy(fn (array $vm) => $vm['days_until'] ?? PHP_INT_MAX)
            ->values();

        return Inertia::render('certificate-expiration/index', [
            'vms' => $vms,
            'defaultWarningDays' => $defaultWarningDays,
        ]);
    }
}

<?php

namespace App\Services;

use App\Mail\ServiceDownMail;
use App\Models\MonitoredService;
use App\Models\ServiceNotification;
use App\Models\SystemSetting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ServiceNotificationService
{
    public function __construct(
        protected ServiceStatusService $statusService,
        protected TelegramNotifier $telegram,
    ) {}

    /**
     * Checks every monitored service and sends a Telegram message and/or
     * email (per Settings → Telegram Notifications → Service Monitoring)
     * for each one that's down and not already notified. A service that
     * recovers is dropped from the notified set, so it alerts again if it
     * goes down again later. Returns the number of services newly
     * notified (not the number of messages — one service down can send
     * both a Telegram message and an email, counted once).
     */
    public function checkAndNotify(): int
    {
        $settings = SystemSetting::current();
        $recipients = $this->parseRecipients($settings->notify_services_emails);

        $sent = 0;
        $currentlyDownIds = [];

        // One SSH round-trip per host (see ServiceStatusService::refresh),
        // and it persists last-known status onto each row on the way
        // through so the Services page never has to SSH on its own.
        $services = MonitoredService::all();
        $results = $this->statusService->refresh($services);

        foreach ($services as $service) {
            $result = $results[$service->id];

            if ($result['healthy']) {
                continue;
            }

            $currentlyDownIds[] = $service->id;

            if (ServiceNotification::where('monitored_service_id', $service->id)->exists()) {
                continue;
            }

            $notifiedAny = false;

            if ($settings->notify_services_telegram_enabled) {
                $notifiedAny = $this->telegram->sendMessage(
                    config('services.telegram.bot_token'),
                    config('services.telegram.chat_id'),
                    $this->formatMessage($service, $result),
                ) || $notifiedAny;
            }

            if (! empty($recipients)) {
                $notifiedAny = $this->sendEmail($recipients, $service, $result) || $notifiedAny;
            }

            // Neither channel is configured, or both failed (e.g. Telegram
            // unreachable and mail not configured yet) — stays un-notified
            // so the next scheduled run retries rather than silently
            // dropping it.
            if (! $notifiedAny) {
                continue;
            }

            // withoutOverlapping() on the schedule (routes/console.php) is
            // the real guard against two runs racing here — this catch is
            // only a backstop, same reasoning as AlarmNotificationService.
            try {
                ServiceNotification::create([
                    'monitored_service_id' => $service->id,
                    'status' => $result['status'],
                    'notified_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $sent++;
        }

        ServiceNotification::whereNotIn('monitored_service_id', $currentlyDownIds)->delete();

        return $sent;
    }

    /**
     * Pulls the send-able addresses out of Settings → "Notify Email":
     * an address only counts once it's both in the list AND has its
     * "notify" permission turned on. Anything without a valid email or
     * with notify off is dropped.
     *
     * @param  array<int, array{email?: string, notify?: bool}>|null  $rows
     * @return array<int, string>
     */
    protected function parseRecipients(?array $rows): array
    {
        return collect($rows ?? [])
            ->filter(fn ($row) => is_array($row) && ! empty($row['notify']))
            ->map(fn (array $row) => trim((string) ($row['email'] ?? '')))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}  $result
     */
    protected function sendEmail(array $recipients, MonitoredService $service, array $result): bool
    {
        try {
            Mail::to($recipients)->send(new ServiceDownMail($service, $result));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * @param  array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}  $result
     */
    protected function formatMessage(MonitoredService $service, array $result): string
    {
        $lines = [
            '🔴 <b>Service Down</b>',
            'Service: <b>'.$this->escape($service->label).'</b> ('.$this->escape($service->service_name).')',
            'Host: '.$this->escape($service->host),
            'Status: '.$this->escape($result['status']),
        ];

        if ($result['detail']) {
            $lines[] = 'Detail: '.$this->escape($result['detail']);
        }

        $lines[] = 'Time: '.$result['checked_at']->toDateTimeString();

        return implode("\n", $lines);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

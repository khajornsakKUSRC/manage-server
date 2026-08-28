<?php

namespace App\Services;

use App\Models\AlarmNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class AlarmNotificationService
{
    public function __construct(
        protected AlarmVsphereService $alarmService,
        protected TelegramNotifier $telegram,
    ) {}

    /**
     * Pulls currently triggered vCenter alarms and sends a Telegram message
     * for each one not already recorded as notified. Alarms that have since
     * cleared are dropped from the notified set, so the same alarm firing
     * again later is treated as new again. Returns the number of Telegram
     * notifications sent.
     */
    public function checkAndNotify(): int
    {
        $objects = $this->alarmService->pull();

        $currentKeys = [];
        $sent = 0;

        foreach ($objects as $object) {
            foreach ($object['alarms'] as $alarm) {
                $key = $this->alarmKey($object['type'], $object['name'], $alarm['name']);
                $currentKeys[] = $key;

                if (AlarmNotification::where('alarm_key', $key)->exists()) {
                    continue;
                }

                // Only recorded once the Telegram send actually succeeds —
                // if it fails (e.g. not configured yet, or a transient
                // network error), this alarm stays "un-notified" and gets
                // retried on the next scheduled run instead of being
                // silently dropped.
                $sentOk = $this->telegram->sendMessage(
                    config('services.telegram.bot_token'),
                    config('services.telegram.chat_id'),
                    $this->formatMessage($object, $alarm),
                );

                if (! $sentOk) {
                    continue;
                }

                // withoutOverlapping() on the schedule (routes/console.php)
                // is the real guard against two runs racing here — this
                // catch is only a backstop in case that lock ever slips
                // (e.g. it expired mid-run on an unusually slow vCenter
                // call): a duplicate-key error means some other process
                // already recorded this exact alarm, which is exactly the
                // outcome we want, so it's swallowed rather than crashing
                // the rest of this run's loop.
                try {
                    AlarmNotification::create([
                        'alarm_key' => $key,
                        'object_type' => $object['type'],
                        'object_name' => $object['name'],
                        'alarm_name' => $alarm['name'],
                        'notified_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }

                $sent++;
            }
        }

        // Scoped to exclude "vm_down:" keys (see checkDownVmsAndNotify) —
        // both methods share this table, and an unscoped delete here would
        // wipe the other's notified state too, causing it to re-notify on
        // every run instead of only on genuinely new events.
        AlarmNotification::where('alarm_key', 'not like', 'vm_down:%')
            ->whereNotIn('alarm_key', $currentKeys)
            ->delete();

        return $sent;
    }

    /**
     * Pulls every Active VM that's currently down/powered-off and sends a
     * Telegram message for each one not already notified. A VM that comes
     * back up is dropped from the notified set, so it alerts again if it
     * goes back down later. Returns the number of Telegram notifications
     * sent.
     */
    public function checkDownVmsAndNotify(): int
    {
        $downVms = $this->alarmService->downVms();

        $currentKeys = [];
        $sent = 0;

        foreach ($downVms as $vm) {
            $key = $this->downVmKey($vm['name']);
            $currentKeys[] = $key;

            if (AlarmNotification::where('alarm_key', $key)->exists()) {
                continue;
            }

            $sentOk = $this->telegram->sendMessage(
                config('services.telegram.bot_token'),
                config('services.telegram.chat_id'),
                $this->formatDownVmMessage($vm),
            );

            if (! $sentOk) {
                continue;
            }

            // See the matching comment in checkAndNotify() above.
            try {
                AlarmNotification::create([
                    'alarm_key' => $key,
                    'object_type' => 'VM',
                    'object_name' => $vm['name'],
                    'alarm_name' => 'VM Down',
                    'notified_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $sent++;
        }

        AlarmNotification::where('alarm_key', 'like', 'vm_down:%')
            ->whereNotIn('alarm_key', $currentKeys)
            ->delete();

        return $sent;
    }

    protected function alarmKey(string $type, string $name, string $alarmName): string
    {
        return md5("{$type}|{$name}|{$alarmName}");
    }

    protected function downVmKey(string $name): string
    {
        return 'vm_down:'.md5($name);
    }

    protected function formatMessage(array $object, array $alarm): string
    {
        $emoji = match (strtolower($alarm['status'])) {
            'red' => '🔴',
            'yellow' => '🟡',
            'green' => '🟢',
            default => '⚪',
        };

        $lines = [
            "{$emoji} <b>New vCenter Alarm</b>",
            'Object: <b>'.$this->escape($object['name']).'</b> ('.$this->escape($object['type']).')',
            'Alarm: '.$this->escape($alarm['name']),
        ];

        if (! empty($alarm['description'])) {
            $lines[] = 'Detail: '.$this->escape($alarm['description']);
        }

        $lines[] = 'Severity: '.$this->escape($alarm['status']);

        if (! empty($alarm['time'])) {
            $lines[] = 'Time: '.$this->formatAlarmTime($alarm['time']);
        }

        return implode("\n", $lines);
    }

    protected function formatDownVmMessage(array $vm): string
    {
        $status = $vm['power_state'] ?? 'Not found in vCenter';

        return implode("\n", [
            '🔴 <b>VM Down</b>',
            'VM: <b>'.$this->escape($vm['name']).'</b>',
            'Status: '.$this->escape($status),
            'Time: '.now()->toDateTimeString(),
        ]);
    }

    /**
     * vCenter reports alarm trigger times as UTC (e.g. "2026-08-24T03:15:00Z")
     * via the SOAP API — converted to the app's Thailand timezone here so the
     * Telegram message reads in local time instead of UTC.
     */
    protected function formatAlarmTime(string $time): string
    {
        try {
            return Carbon::parse($time)->setTimezone(config('app.timezone'))->toDateTimeString();
        } catch (Throwable) {
            return $this->escape($time);
        }
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

<?php

namespace App\Services;

use App\Models\AlarmNotification;

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

                AlarmNotification::create([
                    'alarm_key' => $key,
                    'object_type' => $object['type'],
                    'object_name' => $object['name'],
                    'alarm_name' => $alarm['name'],
                    'notified_at' => now(),
                ]);

                $sent++;
            }
        }

        AlarmNotification::whereNotIn('alarm_key', $currentKeys)->delete();

        return $sent;
    }

    protected function alarmKey(string $type, string $name, string $alarmName): string
    {
        return md5("{$type}|{$name}|{$alarmName}");
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
            $lines[] = 'Time: '.$this->escape($alarm['time']);
        }

        return implode("\n", $lines);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

<?php

namespace App\Services;

use App\Models\CalendarNotice;

class CalendarNoticeReminderService
{
    public function __construct(
        protected TelegramNotifier $telegram,
    ) {}

    /**
     * Sends a Telegram message for every Calendar Notice whose reminder
     * date/time has passed and that hasn't been reminded yet, stamping
     * reminded_at on success so it never fires twice. Returns how many
     * reminders were sent.
     */
    public function sendDueReminders(): int
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        $sent = 0;

        foreach (CalendarNotice::dueForReminder()->with('createdBy:id,name')->get() as $notice) {
            $ok = $this->telegram->sendMessage($botToken, $chatId, $this->formatMessage($notice));

            // No Telegram configured (or the send failed) — leave
            // reminded_at null so the next run retries rather than
            // silently dropping the reminder.
            if (! $ok) {
                continue;
            }

            $notice->forceFill(['reminded_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }

    protected function formatMessage(CalendarNotice $notice): string
    {
        $lines = [
            '🔔 <b>Calendar Reminder</b>',
            'Title: <b>'.$this->escape($notice->title).'</b>',
            'Type: '.$this->escape(CalendarNotice::TYPES[$notice->type] ?? $notice->type),
            'For: '.$this->escape($notice->notice_date->toDateString()),
        ];

        if (trim((string) $notice->message) !== '') {
            $lines[] = '';
            $lines[] = $this->escape($notice->message);
        }

        if ($notice->createdBy) {
            $lines[] = '';
            $lines[] = 'Created by '.$this->escape($notice->createdBy->name);
        }

        return implode("\n", $lines);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

<?php

namespace App\Console\Commands;

use App\Services\CalendarNoticeReminderService;
use Illuminate\Console\Command;
use Throwable;

class NotifyCalendarNotices extends Command
{
    protected $signature = 'calendar-notices:notify';

    protected $description = 'Sends a Telegram reminder for every Calendar Notice whose reminder date/time has arrived';

    public function handle(CalendarNoticeReminderService $reminderService): int
    {
        try {
            $sent = $reminderService->sendDueReminders();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not send calendar reminders: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent {$sent} calendar reminder(s) to Telegram.");

        return self::SUCCESS;
    }
}

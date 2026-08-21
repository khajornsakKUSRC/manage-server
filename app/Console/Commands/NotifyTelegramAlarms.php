<?php

namespace App\Console\Commands;

use App\Services\AlarmNotificationService;
use Illuminate\Console\Command;
use Throwable;

class NotifyTelegramAlarms extends Command
{
    protected $signature = 'alarms:notify-telegram';

    protected $description = 'Checks vCenter for newly triggered alarms and sends a Telegram message for each one not already notified';

    public function handle(AlarmNotificationService $notificationService): int
    {
        try {
            $sent = $notificationService->checkAndNotify();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not check alarms: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent {$sent} new alarm notification(s) to Telegram.");

        return self::SUCCESS;
    }
}

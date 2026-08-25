<?php

namespace App\Console\Commands;

use App\Services\AlarmNotificationService;
use Illuminate\Console\Command;
use Throwable;

class NotifyTelegramAlarms extends Command
{
    protected $signature = 'alarms:notify-telegram';

    protected $description = 'Checks vCenter for newly triggered alarms and down VMs, sending a Telegram message for each one not already notified';

    public function handle(AlarmNotificationService $notificationService): int
    {
        $sent = 0;
        $failed = false;

        try {
            $sent += $notificationService->checkAndNotify();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not check vCenter alarms: '.$e->getMessage());
            $failed = true;
        }

        // Kept independent of the check above — a failure fetching vCenter
        // alarms shouldn't also skip the down-VM check, and vice versa.
        try {
            $sent += $notificationService->checkDownVmsAndNotify();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not check down VMs: '.$e->getMessage());
            $failed = true;
        }

        $this->info("Sent {$sent} new alarm notification(s) to Telegram.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

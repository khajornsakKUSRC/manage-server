<?php

namespace App\Console\Commands;

use App\Services\ServiceNotificationService;
use Illuminate\Console\Command;
use Throwable;

class NotifyServiceStatus extends Command
{
    protected $signature = 'services:check';

    protected $description = 'Checks every monitored systemd service and sends a Telegram/email notification for each one down and not already notified';

    public function handle(ServiceNotificationService $notificationService): int
    {
        try {
            $sent = $notificationService->checkAndNotify();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not check monitored services: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent {$sent} new service-down notification(s).");

        return self::SUCCESS;
    }
}

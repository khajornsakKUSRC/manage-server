<?php

namespace App\Console\Commands;

use App\Services\CertificateNotificationService;
use Illuminate\Console\Command;
use Throwable;

class NotifyCertificateExpirations extends Command
{
    protected $signature = 'certificates:notify-telegram';

    protected $description = 'Checks every active VM\'s certificate expiration date against the configured warning window and sends a Telegram message for each one not already notified';

    public function handle(CertificateNotificationService $notificationService): int
    {
        try {
            $sent = $notificationService->checkAndNotify();
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not check certificate expirations: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent {$sent} new certificate expiration notification(s) to Telegram.");

        return self::SUCCESS;
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Feeds the Datastore page's fill-up projection — needs Laravel's scheduler
// actually running (`php artisan schedule:run` on a 1-minute cron/Task
// Scheduler entry, or Herd's per-site "Scheduler" toggle) to fire daily.
Schedule::command('datastores:snapshot')->dailyAt('00:05');

// Same scheduler dependency as above — polls vCenter for newly triggered
// alarms and down/powered-off VMs, Telegram-notifying on each one not
// already sent.
Schedule::command('alarms:notify-telegram')->everyFiveMinutes();

// SSHes into every active VM with an IP (silently skipping any that aren't
// reachable — e.g. Windows VMs, or ones without the shared guest_ssh
// credential) to run Smart Detection's five checks, Telegram-notifying on
// each new/reopened warning-or-critical finding.
Schedule::command('smart-detection:scan')->everyFifteenMinutes();

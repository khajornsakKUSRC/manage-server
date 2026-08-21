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
// alarms and Telegram-notifies on each one not already sent.
Schedule::command('alarms:notify-telegram')->everyFiveMinutes();

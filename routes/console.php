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
// already sent. runInBackground() so a slow vCenter round-trip here can't
// delay every other command still queued behind it in the same tick (see
// network-monitors:check below — this was observed blocking it for
// minutes at a time).
Schedule::command('alarms:notify-telegram')->everyFiveMinutes()->runInBackground();

// SSHes into every active VM with an IP (silently skipping any that aren't
// reachable — e.g. Windows VMs, or ones without the shared guest_ssh
// credential) to run Smart Detection's five checks, Telegram-notifying on
// each new/reopened warning-or-critical finding. runInBackground() for the
// same reason as alarms:notify-telegram above — with enough VMs (each SSH
// attempt has its own ~10s connect timeout), this could otherwise run for
// several minutes and block everything queued after it.
Schedule::command('smart-detection:scan')->everyFifteenMinutes()->runInBackground();

// Feeds the Network Infrastructure page's uptime heartbeats — runs every
// minute (the scheduler's finest grain) but each monitor only actually gets
// checked once its own interval_seconds has elapsed. withoutOverlapping()
// guards against a run still checking slow HTTP/TCP targets when the next
// minute ticks over.
Schedule::command('network-monitors:check')->everyMinute()->withoutOverlapping();

// Keeps network_monitor_checks from growing unbounded — the page only ever
// shows the last hour (and the uptime % only looks back 24h), so a rolling
// 24h of history is enough buffer.
Schedule::command('network-monitors:prune')->dailyAt('00:10');

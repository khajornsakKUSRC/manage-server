<?php

use App\Models\SystemSetting;
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

// Feeds the Network Infrastructure page's uptime heartbeats — runs every
// minute (the scheduler's finest grain) but each monitor only actually gets
// checked once its own interval_seconds has elapsed. withoutOverlapping()
// guards against a run still checking slow HTTP/TCP targets when the next
// minute ticks over. Always runs regardless of Settings → Telegram
// Notifications → Network Infrastructure (WAN) — that toggle only mutes
// the Telegram alert inside the command, not this page-feeding check.
Schedule::command('network-monitors:check')->everyMinute()->withoutOverlapping();

// Keeps network_monitor_checks from growing unbounded — the page only ever
// shows the last hour (and the uptime % only looks back 24h), so a rolling
// 24h of history is enough buffer.
Schedule::command('network-monitors:prune')->dailyAt('00:10');

// The schedules below read their cadence/enabled state from Settings →
// Telegram Notifications, re-evaluated fresh on every artisan boot (this
// file loads on every console invocation, and SystemSetting::current() is
// cached indefinitely, so this costs nothing beyond the first read). Falls
// back to the original hardcoded defaults if settings can't be read yet —
// e.g. a fresh install running `php artisan migrate` before the
// system_settings table exists.
try {
    $notificationSettings = SystemSetting::current();
} catch (Throwable) {
    $notificationSettings = null;
}

$alarmsEnabled = $notificationSettings?->notify_alarms_enabled ?? true;
$alarmsIntervalMinutes = $notificationSettings?->notify_alarms_interval_minutes ?? 1;
$smartDetectionIntervalMinutes = $notificationSettings?->notify_smart_detection_interval_minutes ?? 15;
$certificateEnabled = $notificationSettings?->notify_certificate_enabled ?? true;
$certificateCheckTime = $notificationSettings?->notify_certificate_check_time ?? '08:00';

// Polls vCenter for newly triggered alarms and down/powered-off VMs,
// Telegram-notifying on each one not already sent. Settings → Telegram
// Notifications → Alarm Notification controls both whether this runs at
// all and how often — nothing else in the app depends on this command
// running, so disabling it here is safe. Defaults to every 1 minute (the
// scheduler's finest grain) so an alert reaches Telegram as close to
// "immediately" as this polling-based design allows. runInBackground() so
// a slow vCenter round-trip can't delay every other command still queued
// behind it in the same tick. withoutOverlapping() is required, not just
// nice-to-have: without it, a run that's still going (slow vCenter/
// Telegram call) when the next tick fires overlaps with the new one, and
// both processes can race past the "already notified?" check for the same
// alarm before either records it — this actually happened in production
// (duplicate-key crashes in the log, and likely a duplicated Telegram
// message before the crash).
if ($alarmsEnabled) {
    Schedule::command('alarms:notify-telegram')
        ->cron("*/{$alarmsIntervalMinutes} * * * *")
        ->withoutOverlapping()
        ->runInBackground();
}

// SSHes into every active VM with an IP (silently skipping any that aren't
// reachable — e.g. Windows VMs, or ones without the shared guest_ssh
// credential) to run Smart Detection's five checks, storing findings for
// the Smart Detection page and Telegram-notifying on each new/reopened
// warning-or-critical one. Always runs on its configured interval — the
// Smart Detection page's data depends on it — Settings → Telegram
// Notifications → Smart Detection's "enabled" toggle only mutes the
// Telegram alert inside the command, not the scan itself.
// runInBackground() since with enough VMs (each SSH attempt has its own
// ~10s connect timeout) this could otherwise run for several minutes and
// block everything queued after it.
Schedule::command('smart-detection:scan')
    ->cron("*/{$smartDetectionIntervalMinutes} * * * *")
    ->withoutOverlapping()
    ->runInBackground();

// Warns about VM certificates expiring soon — the window is Settings →
// Monitoring Thresholds → Certificate Expiration Warning (days); Settings
// → Telegram Notifications → Certificate Expiration controls whether this
// runs at all and what time of day. Nothing else depends on this command.
if ($certificateEnabled) {
    Schedule::command('certificates:notify-telegram')
        ->dailyAt($certificateCheckTime)
        ->withoutOverlapping()
        ->runInBackground();
}

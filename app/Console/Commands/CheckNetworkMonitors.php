<?php

namespace App\Console\Commands;

use App\Models\NetworkMonitor;
use App\Models\NetworkMonitorCheck;
use App\Services\NetworkMonitorCheckService;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class CheckNetworkMonitors extends Command
{
    protected $signature = 'network-monitors:check';

    protected $description = 'Runs a ping/http/tcp/dns check for every active Network Infrastructure monitor that is due, recording a heartbeat for the uptime history';

    public function handle(NetworkMonitorCheckService $service, TelegramNotifier $telegram): int
    {
        $now = now();

        $due = NetworkMonitor::where('is_active', true)
            ->get()
            ->filter(fn (NetworkMonitor $monitor) => $monitor->last_checked_at === null
                || $monitor->last_checked_at->diffInSeconds($now) >= $monitor->interval_seconds);

        foreach ($due as $monitor) {
            $previousStatus = $monitor->last_status;
            $result = $service->check($monitor);
            $checkedAt = now();

            NetworkMonitorCheck::create([
                'network_monitor_id' => $monitor->id,
                'status' => $result['status'],
                'response_time_ms' => $result['response_time_ms'],
                'message' => $result['message'],
                'checked_at' => $checkedAt,
            ]);

            $monitor->update([
                'last_status' => $result['status'],
                'last_checked_at' => $checkedAt,
                'last_response_time_ms' => $result['response_time_ms'],
                'last_message' => $result['message'],
            ]);

            $this->notifyWanTransition($telegram, $monitor, $previousStatus, $result);
        }

        $this->info("Checked {$due->count()} network monitor(s).");

        return self::SUCCESS;
    }

    /**
     * Telegram-alerts on a WAN monitor's down/recovered transitions only —
     * i.e. once when it first times out, and once when it comes back —
     * rather than on every check while it stays down, by comparing against
     * the status the monitor had before this check. Scoped to the "wan"
     * category since that's the link an outage actually pages someone
     * for; other categories stay silent here.
     */
    private function notifyWanTransition(TelegramNotifier $telegram, NetworkMonitor $monitor, ?string $previousStatus, array $result): void
    {
        if ($monitor->category !== 'wan') {
            return;
        }

        $justWentDown = $result['status'] === NetworkMonitor::STATUS_DOWN && $previousStatus !== NetworkMonitor::STATUS_DOWN;
        $justRecovered = $result['status'] === NetworkMonitor::STATUS_UP && $previousStatus === NetworkMonitor::STATUS_DOWN;

        if (! $justWentDown && ! $justRecovered) {
            return;
        }

        $message = $justWentDown
            ? "⚠️ <b>WAN Down</b>\n{$monitor->name} ({$monitor->target}) is not responding.".($result['message'] ? "\nReason: {$result['message']}" : '')
            : "✅ <b>WAN Recovered</b>\n{$monitor->name} ({$monitor->target}) is back up".($result['response_time_ms'] !== null ? " ({$result['response_time_ms']} ms)." : '.');

        $telegram->sendMessage(
            config('services.telegram.bot_token'),
            config('services.telegram.chat_id'),
            $message,
        );
    }
}

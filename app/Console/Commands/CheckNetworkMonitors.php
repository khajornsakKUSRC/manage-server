<?php

namespace App\Console\Commands;

use App\Models\NetworkMonitor;
use App\Models\NetworkMonitorCheck;
use App\Services\NetworkMonitorCheckService;
use Illuminate\Console\Command;

class CheckNetworkMonitors extends Command
{
    protected $signature = 'network-monitors:check';

    protected $description = 'Runs a ping/http/tcp/dns check for every active Network Infrastructure monitor that is due, recording a heartbeat for the uptime history';

    public function handle(NetworkMonitorCheckService $service): int
    {
        $now = now();

        $due = NetworkMonitor::where('is_active', true)
            ->get()
            ->filter(fn (NetworkMonitor $monitor) => $monitor->last_checked_at === null
                || $monitor->last_checked_at->diffInSeconds($now) >= $monitor->interval_seconds);

        foreach ($due as $monitor) {
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
        }

        $this->info("Checked {$due->count()} network monitor(s).");

        return self::SUCCESS;
    }
}

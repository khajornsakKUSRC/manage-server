<?php

namespace App\Console\Commands;

use App\Models\NetworkMonitorCheck;
use Illuminate\Console\Command;

class PruneNetworkMonitorChecks extends Command
{
    protected $signature = 'network-monitors:prune';

    protected $description = 'Deletes Network Infrastructure heartbeat history older than 24 hours, so the checks table does not grow unbounded';

    public function handle(): int
    {
        $deleted = NetworkMonitorCheck::where('checked_at', '<', now()->subDay())->delete();

        $this->info("Pruned {$deleted} old network monitor check(s).");

        return self::SUCCESS;
    }
}

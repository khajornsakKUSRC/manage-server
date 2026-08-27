<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class ServerPingService
{
    public function ping(string $host): array
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? ['ping', '-n', '1', '-w', '1500', $host]
            : ['ping', '-c', '1', '-W', '2', $host];

        $start = microtime(true);
        $result = Process::timeout(5)->run($command);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'online' => $result->successful(),
            'response_time_ms' => $result->successful() ? $elapsedMs : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}

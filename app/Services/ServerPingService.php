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

        $result = Process::timeout(5)->run($command);

        return [
            'online' => $result->successful(),
            'checked_at' => now()->toIso8601String(),
        ];
    }
}

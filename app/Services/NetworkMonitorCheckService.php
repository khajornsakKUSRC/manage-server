<?php

namespace App\Services;

use App\Models\NetworkMonitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class NetworkMonitorCheckService
{
    /**
     * Runs the monitor's configured check (ping/http/tcp/dns) and returns
     * ['status' => up|down, 'response_time_ms' => ?int, 'message' => ?string].
     * Never throws — an unreachable target is a normal "down" result, not
     * an error.
     */
    public function check(NetworkMonitor $monitor): array
    {
        return match ($monitor->type) {
            'ping' => $this->pingCheck($monitor->target, $monitor->timeout_ms),
            'http' => $this->httpCheck($monitor->target, $monitor->timeout_ms),
            'tcp' => $this->tcpCheck($monitor->target, $monitor->port, $monitor->timeout_ms),
            'dns' => $this->dnsCheck($monitor->target, $monitor->timeout_ms),
            default => ['status' => 'down', 'response_time_ms' => null, 'message' => 'Unknown monitor type'],
        };
    }

    private function pingCheck(string $target, int $timeoutMs): array
    {
        $timeoutSeconds = max(1, (int) ceil($timeoutMs / 1000));

        $command = PHP_OS_FAMILY === 'Windows'
            ? ['ping', '-n', '1', '-w', (string) $timeoutMs, $target]
            : ['ping', '-c', '1', '-W', (string) $timeoutSeconds, $target];

        $start = microtime(true);
        $result = Process::timeout($timeoutSeconds + 2)->run($command);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if ($result->successful()) {
            return ['status' => 'up', 'response_time_ms' => $elapsedMs, 'message' => null];
        }

        return ['status' => 'down', 'response_time_ms' => null, 'message' => 'Host unreachable'];
    }

    private function httpCheck(string $target, int $timeoutMs): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout(max(1, (int) ceil($timeoutMs / 1000)))
                ->withoutVerifying()
                ->get($target);

            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful() || $response->redirect()) {
                return ['status' => 'up', 'response_time_ms' => $elapsedMs, 'message' => null];
            }

            return ['status' => 'down', 'response_time_ms' => $elapsedMs, 'message' => "HTTP {$response->status()}"];
        } catch (Throwable $e) {
            return ['status' => 'down', 'response_time_ms' => null, 'message' => Str::limit($e->getMessage(), 200)];
        }
    }

    private function tcpCheck(string $target, ?int $port, int $timeoutMs): array
    {
        if (! $port) {
            return ['status' => 'down', 'response_time_ms' => null, 'message' => 'Port not configured'];
        }

        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $connection = @fsockopen($target, $port, $errno, $errstr, $timeoutMs / 1000);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if ($connection) {
            fclose($connection);

            return ['status' => 'up', 'response_time_ms' => $elapsedMs, 'message' => null];
        }

        return ['status' => 'down', 'response_time_ms' => null, 'message' => $errstr ?: 'Connection failed'];
    }

    private function dnsCheck(string $target, int $timeoutMs): array
    {
        $start = microtime(true);
        $records = @dns_get_record($target, DNS_A + DNS_AAAA);
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if ($records !== false && count($records) > 0) {
            return ['status' => 'up', 'response_time_ms' => $elapsedMs, 'message' => null];
        }

        return ['status' => 'down', 'response_time_ms' => null, 'message' => 'No DNS record found'];
    }
}

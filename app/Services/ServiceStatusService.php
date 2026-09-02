<?php

namespace App\Services;

use App\Models\MonitoredService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class ServiceStatusService
{
    /**
     * The systemd properties we pull. `systemctl show` is used instead of
     * `systemctl status` on purpose: it takes many units in one call, its
     * output is trivially machine-parseable (Key=Value blocks), and it
     * carries only the fields we need rather than the full unit dump +
     * cgroup tree + journal tail. LoadState=not-found is how a missing
     * unit shows up (there's no error line to grep for).
     */
    protected const SHOW_PROPERTIES = 'Id,LoadState,ActiveState,SubState,ActiveEnterTimestamp';

    public function __construct(
        protected GuestSshService $ssh,
    ) {}

    /**
     * Live status for one service. Thin wrapper over checkMany() — prefer
     * checkMany()/refresh() when handling more than one, so services that
     * share a host share a single SSH connection.
     *
     * @return array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}
     */
    public function check(MonitoredService $service): array
    {
        return $this->checkMany(collect([$service]))[$service->id];
    }

    /**
     * Live status for many services, one SSH round-trip per distinct
     * host (not per service). Never throws — an SSH/connection failure is
     * itself a kind of down state ("unreachable").
     *
     * @param  Collection<int, MonitoredService>  $services
     * @return array<int, array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}>
     *                                                                                                           keyed by MonitoredService id
     */
    public function checkMany(Collection $services): array
    {
        $results = [];

        foreach ($services->groupBy('host') as $host => $group) {
            $results += $this->checkHost((string) $host, $group->values());
        }

        return $results;
    }

    /**
     * Like checkMany(), but also persists each result onto the
     * monitored_services row so the Services page can render last-known
     * status without doing any SSH of its own.
     *
     * @param  Collection<int, MonitoredService>  $services
     * @return array<int, array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}>
     */
    public function refresh(Collection $services): array
    {
        $results = $this->checkMany($services);

        foreach ($services as $service) {
            $result = $results[$service->id];

            $service->forceFill([
                'last_status' => $result['status'],
                'last_healthy' => $result['healthy'],
                'last_detail' => $result['detail'],
                'last_raw' => $result['raw'],
                'last_checked_at' => $result['checked_at'],
            ])->save();
        }

        return $results;
    }

    /**
     * @param  Collection<int, MonitoredService>  $services  all on the same host
     * @return array<int, array{status: string, healthy: bool, detail: string, raw: string, checked_at: Carbon}>
     */
    protected function checkHost(string $host, Collection $services): array
    {
        $names = $services->pluck('service_name')->all();

        $command = 'SYSTEMD_COLORS=0 systemctl show --no-pager --property='.self::SHOW_PROPERTIES.' '
            .implode(' ', array_map('escapeshellarg', $names)).' 2>&1';

        try {
            $raw = $this->ssh->run($host, $command, 20);
        } catch (Throwable $e) {
            return $services->mapWithKeys(fn (MonitoredService $s) => [$s->id => [
                'status' => 'unreachable',
                'healthy' => false,
                'detail' => $e->getMessage(),
                'raw' => '',
                'checked_at' => now(),
            ]])->all();
        }

        // `systemctl show a b c` emits one blank-line-separated block per
        // unit, in the order given — so match blocks back to services by
        // position rather than trying to normalise unit names.
        $blocks = $this->parseBlocks($raw);
        $checkedAt = now();
        $out = [];

        foreach ($services as $i => $service) {
            $out[$service->id] = [
                ...$this->interpret($blocks[$i] ?? null),
                'checked_at' => $checkedAt,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>|null  $block
     * @return array{status: string, healthy: bool, detail: string, raw: string}
     */
    protected function interpret(?array $block): array
    {
        if ($block === null) {
            return ['status' => 'unknown', 'healthy' => false, 'detail' => 'ไม่สามารถแปลผลสถานะ service ได้', 'raw' => ''];
        }

        if (strtolower($block['LoadState'] ?? '') === 'not-found') {
            return ['status' => 'not-found', 'healthy' => false, 'detail' => 'ไม่พบ service นี้บนเครื่อง', 'raw' => ''];
        }

        $active = strtolower($block['ActiveState'] ?? '');
        $sub = trim($block['SubState'] ?? '');
        $since = trim($block['ActiveEnterTimestamp'] ?? '');

        if ($active === '') {
            return ['status' => 'unknown', 'healthy' => false, 'detail' => 'ไม่สามารถแปลผลสถานะ service ได้', 'raw' => ''];
        }

        // Reconstruct the one "Active:" line the old code kept, so the
        // Services page's raw box and the alert email read the same as
        // before.
        $raw = 'Active: '.$active
            .($sub !== '' ? " ({$sub})" : '')
            .($since !== '' && $since !== '0' ? " since {$since}" : '');

        return [
            'status' => $active,
            'healthy' => $active === 'active',
            'detail' => trim($sub.($since !== '' && $since !== '0' ? " since {$since}" : '')),
            'raw' => $raw,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function parseBlocks(string $raw): array
    {
        $blocks = [];

        foreach (preg_split('/\R{2,}/', trim($raw)) ?: [] as $chunk) {
            $kv = [];

            foreach (preg_split('/\R/', trim($chunk)) ?: [] as $line) {
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $kv[trim($key)] = trim($value);
                }
            }

            if ($kv !== []) {
                $blocks[] = $kv;
            }
        }

        return $blocks;
    }
}

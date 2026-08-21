<?php

namespace App\Services;

use App\Models\DatastoreSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DatastoreTrendService
{
    /**
     * Growth rate is averaged over at most this many trailing days of
     * recorded history (or all of it, if less has accumulated so far).
     */
    protected const TREND_WINDOW_DAYS = 30;

    public function __construct(
        protected VsphereService $vsphere,
    ) {}

    /**
     * One row per live vCenter datastore: current capacity/usage, its
     * recorded daily history, and — once at least two distinct days of
     * history exist — a fill-up projection derived from the average daily
     * growth in used space over the trailing window. Datastores with fewer
     * than two recorded days come back with a null projection ("not enough
     * data yet"); see SnapshotDatastores for how history accumulates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pull(): array
    {
        $datastores = $this->vsphere->getDatastores();
        $names = collect($datastores)->pluck('name')->all();

        $historyByName = DatastoreSnapshot::whereIn('name', $names)
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy('name');

        return collect($datastores)
            ->map(fn (array $datastore) => $this->buildRow(
                $datastore,
                $historyByName->get($datastore['name'], collect()),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DatastoreSnapshot>  $rows
     */
    protected function buildRow(array $datastore, Collection $rows): array
    {
        $capacity = (float) $datastore['capacity'];
        $freeSpace = (float) $datastore['free_space'];
        $used = max(0.0, $capacity - $freeSpace);
        $usedPct = $capacity > 0 ? round(($used / $capacity) * 100, 1) : 0.0;

        $history = $rows->map(fn (DatastoreSnapshot $row) => [
            'date' => $row->snapshot_date->toDateString(),
            'used_bytes' => (float) max(0, $row->capacity - $row->free_space),
            'used_pct' => $row->capacity > 0
                ? round((max(0, $row->capacity - $row->free_space) / $row->capacity) * 100, 1)
                : 0.0,
        ])->values()->all();

        // The scheduled snapshot may not have run yet today — append the
        // live reading so "today" on the chart always matches the numbers
        // shown beside it, without waiting for tonight's snapshot.
        $today = now()->toDateString();

        if (empty($history) || end($history)['date'] !== $today) {
            $history[] = ['date' => $today, 'used_bytes' => $used, 'used_pct' => $usedPct];
        }

        $trend = $this->trend($history, $freeSpace);

        return [
            'name' => $datastore['name'],
            'type' => $datastore['type'] ?? null,
            'capacity' => $capacity,
            'free_space' => $freeSpace,
            'used' => $used,
            'used_pct' => $usedPct,
            'history' => array_map(
                fn (array $point) => ['date' => $point['date'], 'used_pct' => $point['used_pct']],
                $history,
            ),
            'daily_growth_bytes' => $trend['daily_growth_bytes'],
            'days_until_full' => $trend['days_until_full'],
            'projected_full_date' => $trend['projected_full_date'],
            // Two points — today and the projected full date — enough to
            // draw the dashed forecast segment continuing from the actual
            // history line.
            'projection' => $trend['days_until_full'] === null ? null : [
                ['date' => $today, 'used_pct' => $usedPct],
                ['date' => $trend['projected_full_date'], 'used_pct' => 100.0],
            ],
        ];
    }

    /**
     * @param  array<int, array{date: string, used_bytes: float, used_pct: float}>  $history
     * @return array{daily_growth_bytes: ?float, days_until_full: ?int, projected_full_date: ?string}
     */
    protected function trend(array $history, float $freeSpace): array
    {
        $empty = ['daily_growth_bytes' => null, 'days_until_full' => null, 'projected_full_date' => null];

        $windowStart = now()->subDays(self::TREND_WINDOW_DAYS)->toDateString();

        $windowed = collect($history)
            ->filter(fn (array $point) => $point['date'] >= $windowStart)
            ->values();

        if ($windowed->count() < 2) {
            return $empty;
        }

        $first = $windowed->first();
        $last = $windowed->last();

        $days = Carbon::parse($first['date'])->diffInDays(Carbon::parse($last['date']));

        if ($days < 1) {
            return $empty;
        }

        $dailyGrowthBytes = ($last['used_bytes'] - $first['used_bytes']) / $days;

        if ($dailyGrowthBytes <= 0) {
            // Flat or shrinking usage — not filling up, nothing to project.
            return ['daily_growth_bytes' => round($dailyGrowthBytes), 'days_until_full' => null, 'projected_full_date' => null];
        }

        $daysUntilFull = (int) ceil($freeSpace / $dailyGrowthBytes);

        return [
            'daily_growth_bytes' => round($dailyGrowthBytes),
            'days_until_full' => $daysUntilFull,
            'projected_full_date' => now()->addDays($daysUntilFull)->toDateString(),
        ];
    }
}

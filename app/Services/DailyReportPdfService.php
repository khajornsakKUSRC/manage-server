<?php

namespace App\Services;

use App\Models\DailyReport;

class DailyReportPdfService
{
    protected const DISK_WARNING = 70.0;

    protected const DISK_CRITICAL = 85.0;

    /**
     * Builds the view-model for one date's report page.
     */
    public function buildData(DailyReport $report): array
    {
        $items = $report->items;
        $total = $items->count();
        $up = $items->where('power_state', 'POWERED_ON')->count();
        $down = $total - $up;

        $rows = $items->map(fn ($item) => [
            'name' => $item->name,
            'host' => $item->host ?: '-',
            'up' => $item->power_state === 'POWERED_ON',
            'power_state' => $item->power_state ?: '-',
            'cpu_count' => $item->cpu_count,
            'memory_gb' => $item->memory_gb,
            'disk_usage_pct' => $item->disk_usage_pct,
            'disk_level' => $this->diskLevel($item->disk_usage_pct),
            'uptime' => $this->formatUptime($item->uptime_seconds),
            'certificate_exp' => $item->certificate_exp ?: '-',
            'notes' => $item->notes ?: '-',
        ])->values();

        $incidents = $report->incidents->map(fn ($entry) => [
            'vm_name' => $entry->vm_name ?: '-',
            'incident' => $entry->incident ? (DailyReport::INCIDENTS[$entry->incident] ?? $entry->incident) : '-',
            'action' => $entry->action ? (DailyReport::ACTIONS[$entry->action] ?? $entry->action) : '-',
            'remark' => $entry->remark ?: '-',
        ])->values();

        return [
            'reportDate' => $report->report_date->translatedFormat('d/m/Y'),
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'availabilityPct' => $total > 0 ? round(($up / $total) * 100, 1) : 0,
            'rows' => $rows,
            'incidents' => $incidents,
            'createdBy' => $report->createdBy?->name,
        ];
    }

    protected function diskLevel(?float $pct): ?string
    {
        if ($pct === null) {
            return null;
        }

        if ($pct >= self::DISK_CRITICAL) {
            return 'critical';
        }

        if ($pct >= self::DISK_WARNING) {
            return 'warning';
        }

        return 'normal';
    }

    public function formatUptime(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}

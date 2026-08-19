<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use Illuminate\Support\Collection;

class DailyReportPdfService
{
    protected const STORAGE_WARNING = 70.0;

    protected const STORAGE_CRITICAL = 85.0;

    protected const UTIL_WARNING = 70.0;

    protected const UTIL_CRITICAL = 85.0;

    protected const DOWN_STATE_KEYWORDS = ['stop', 'off', 'disconnect', 'error', 'fail', 'invalid', 'unknown'];

    protected const ABNORMAL_STATUS_KEYWORDS_OK = ['ok', 'normal', 'good', 'healthy', 'green', 'ปกติ'];

    public function buildData(DailyReport $report): array
    {
        $items = $report->items;

        $downStateIds = $this->filterByKeywords($items, 'state', self::DOWN_STATE_KEYWORDS)->pluck('id');
        $abnormalStatusIds = $this->abnormalStatusItems($items)->pluck('id');
        $storageFindings = $this->storageFindings($items);
        $cpuFindings = $this->utilizationFindings($items, 'host_cpu');
        $memFindings = $this->utilizationFindings($items, 'host_mem');

        $total = $items->count();
        $poweredOn = $items->filter(fn ($item) => ! $downStateIds->contains($item->id))->count();
        $poweredOff = $total - $poweredOn;
        $availabilityPct = $total > 0 ? round(($poweredOn / $total) * 100, 1) : 0;

        $hostRows = $this->buildHostRows($items, $downStateIds, $abnormalStatusIds);

        $checklist = [
            ['label' => 'VM Power State (ใช้งานได้ปกติ)', 'derivable' => true, 'checked' => $downStateIds->isEmpty()],
            ['label' => 'Network Connectivity', 'derivable' => false, 'checked' => false],
            ['label' => 'CPU Usage (ทำงานปกติไม่เกิด Over heat)', 'derivable' => true, 'checked' => $cpuFindings->isEmpty()],
            ['label' => 'Memory Usage (พร้อมใช้งาน)', 'derivable' => true, 'checked' => $memFindings->isEmpty()],
            ['label' => 'Storage (พร้อมใช้งาน)', 'derivable' => true, 'checked' => $storageFindings->isEmpty()],
            ['label' => 'Service สำคัญ (php, httpd, postfix, dovecot)', 'derivable' => false, 'checked' => false],
            ['label' => 'VMware Alarm (สำคัญ)', 'derivable' => false, 'checked' => false],
        ];

        $problemVms = $this->buildProblemVms($items, $downStateIds, $abnormalStatusIds, $storageFindings, $cpuFindings, $memFindings);

        $derivableAllChecked = collect($checklist)->where('derivable', true)->every(fn ($c) => $c['checked']);
        $overallNormal = $problemVms->isEmpty() && $derivableAllChecked;

        return [
            'total' => $total,
            'poweredOn' => $poweredOn,
            'poweredOff' => $poweredOff,
            'availabilityPct' => $availabilityPct,
            'hostRows' => $hostRows,
            'checklist' => $checklist,
            'problemVms' => $problemVms,
            'overallNormal' => $overallNormal,
            'reasonText' => $overallNormal ? '' : $this->buildReasonText($downStateIds, $abnormalStatusIds, $storageFindings, $cpuFindings, $memFindings),
        ];
    }

    protected function buildHostRows(Collection $items, Collection $downStateIds, Collection $abnormalStatusIds): Collection
    {
        return $items
            ->groupBy(fn ($item) => $item->host ?: '-')
            ->map(function (Collection $group, $host) use ($downStateIds, $abnormalStatusIds) {
                $hasIssue = $group->contains(fn ($item) => $downStateIds->contains($item->id) || $abnormalStatusIds->contains($item->id));
                $onlineCount = $group->filter(fn ($item) => ! $downStateIds->contains($item->id))->count();

                return [
                    'host' => $host,
                    'total' => $group->count(),
                    'online' => $onlineCount,
                    'pass' => ! $hasIssue,
                ];
            })
            ->values();
    }

    protected function buildProblemVms(
        Collection $items,
        Collection $downStateIds,
        Collection $abnormalStatusIds,
        Collection $storageFindings,
        Collection $cpuFindings,
        Collection $memFindings
    ): Collection {
        $storageByItemId = $storageFindings->keyBy(fn ($f) => $f['item']->id);
        $cpuByItemId = $cpuFindings->keyBy(fn ($f) => $f['item']->id);
        $memByItemId = $memFindings->keyBy(fn ($f) => $f['item']->id);

        return $items
            ->filter(fn ($item) => $downStateIds->contains($item->id)
                || $abnormalStatusIds->contains($item->id)
                || $storageByItemId->has($item->id)
                || $cpuByItemId->has($item->id)
                || $memByItemId->has($item->id)
            )
            ->map(function (DailyReportItem $item) use ($downStateIds, $abnormalStatusIds, $storageByItemId, $cpuByItemId, $memByItemId) {
                $remarks = [];

                if ($downStateIds->contains($item->id)) {
                    $remarks[] = "State: {$item->state}";
                }

                if ($abnormalStatusIds->contains($item->id)) {
                    $remarks[] = "Status: {$item->status}";
                }

                if ($storageByItemId->has($item->id)) {
                    $remarks[] = "Storage {$storageByItemId[$item->id]['pct']}%";
                }

                if ($cpuByItemId->has($item->id)) {
                    $remarks[] = "Host CPU {$item->host_cpu}";
                }

                if ($memByItemId->has($item->id)) {
                    $remarks[] = "Host Mem {$item->host_mem}";
                }

                return [
                    'host' => $item->host ?: '-',
                    'name' => $item->name,
                    'dns' => $item->dns ?: '-',
                    'status' => $item->status ?: ($item->state ?: '-'),
                    'remark' => implode(', ', $remarks),
                ];
            })
            ->values();
    }

    protected function buildReasonText(
        Collection $downStateIds,
        Collection $abnormalStatusIds,
        Collection $storageFindings,
        Collection $cpuFindings,
        Collection $memFindings
    ): string {
        $parts = [];

        if ($downStateIds->isNotEmpty()) {
            $parts[] = "state ผิดปกติ {$downStateIds->count()} เครื่อง";
        }

        if ($abnormalStatusIds->isNotEmpty()) {
            $parts[] = "status ผิดปกติ {$abnormalStatusIds->count()} เครื่อง";
        }

        if ($storageFindings->isNotEmpty()) {
            $parts[] = "พื้นที่จัดเก็บใกล้เต็ม {$storageFindings->count()} เครื่อง";
        }

        if ($cpuFindings->isNotEmpty()) {
            $parts[] = "Host CPU สูง {$cpuFindings->count()} เครื่อง";
        }

        if ($memFindings->isNotEmpty()) {
            $parts[] = "Host Memory สูง {$memFindings->count()} เครื่อง";
        }

        return 'พบ '.implode(', ', $parts);
    }

    protected function filterByKeywords(Collection $items, string $field, array $keywords): Collection
    {
        return $items->filter(function ($item) use ($field, $keywords) {
            $value = $item->{$field};

            if (blank($value)) {
                return false;
            }

            $value = mb_strtolower($value);

            foreach ($keywords as $keyword) {
                if (str_contains($value, $keyword)) {
                    return true;
                }
            }

            return false;
        });
    }

    protected function abnormalStatusItems(Collection $items): Collection
    {
        return $items->filter(function ($item) {
            if (blank($item->status)) {
                return false;
            }

            $status = mb_strtolower($item->status);

            foreach (self::ABNORMAL_STATUS_KEYWORDS_OK as $ok) {
                if (str_contains($status, mb_strtolower($ok))) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function storageFindings(Collection $items): Collection
    {
        return $items
            ->map(function ($item) {
                $used = $this->extractNumber($item->used_space);
                $provisioned = $this->extractNumber($item->provisioned_space);

                if ($used === null || $provisioned === null || $provisioned <= 0) {
                    return null;
                }

                $pct = round(($used / $provisioned) * 100, 1);

                if ($pct < self::STORAGE_WARNING) {
                    return null;
                }

                return ['item' => $item, 'pct' => $pct];
            })
            ->filter()
            ->values();
    }

    protected function utilizationFindings(Collection $items, string $field): Collection
    {
        return $items
            ->map(function ($item) use ($field) {
                $value = $this->extractNumber($item->{$field});

                if ($value === null || $value < self::UTIL_WARNING) {
                    return null;
                }

                return ['item' => $item, 'value' => $value];
            })
            ->filter()
            ->values();
    }

    protected function extractNumber(?string $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/-?\d+(\.\d+)?/', str_replace(',', '', $value), $matches)) {
            return (float) $matches[0];
        }

        return null;
    }
}

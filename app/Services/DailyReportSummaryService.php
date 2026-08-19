<?php

namespace App\Services;

use App\Models\DailyReport;
use Illuminate\Support\Collection;

class DailyReportSummaryService
{
    protected const STORAGE_WARNING = 70.0;

    protected const STORAGE_CRITICAL = 85.0;

    protected const UTIL_WARNING = 70.0;

    protected const UTIL_CRITICAL = 85.0;

    protected const DOWN_STATE_KEYWORDS = ['stop', 'off', 'disconnect', 'error', 'fail', 'invalid', 'unknown'];

    protected const ABNORMAL_STATUS_KEYWORDS_OK = ['ok', 'normal', 'good', 'healthy', 'green', 'ปกติ'];

    public function summarize(DailyReport $report): string
    {
        $items = $report->items;
        $date = $report->report_date->translatedFormat('d/m/Y');
        $total = $items->count();

        if ($total === 0) {
            return "รายงานประจำวันที่ {$date} ไม่มีข้อมูล VM";
        }

        $downStates = $this->filterByKeywords($items, 'state', self::DOWN_STATE_KEYWORDS, matchIfContains: true);
        $abnormalStatus = $items->filter(function ($item) {
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

        $storageFindings = $this->storageFindings($items);
        $cpuFindings = $this->utilizationFindings($items, 'host_cpu');
        $memFindings = $this->utilizationFindings($items, 'host_mem');

        $lines = [];
        $lines[] = "สรุปรายงานประจำวันที่ {$date}";
        $lines[] = '';
        $lines[] = "ตรวจสอบทั้งหมด {$total} รายการ พบ VM ที่ state ผิดปกติ {$downStates->count()} รายการ, ".
            "status ผิดปกติ {$abnormalStatus->count()} รายการ, พื้นที่จัดเก็บใกล้เต็ม {$storageFindings->count()} รายการ, ".
            "Host CPU สูง {$cpuFindings->count()} รายการ, Host Memory สูง {$memFindings->count()} รายการ";

        $hasIssue = false;

        if ($downStates->isNotEmpty()) {
            $hasIssue = true;
            $lines[] = '';
            $lines[] = '⚠ VM ที่ state ผิดปกติ (ควรตรวจสอบ):';
            $lines[] = $this->bulletList($downStates->map(fn ($item) => "{$item->name} (state: {$item->state})"));
        }

        if ($abnormalStatus->isNotEmpty()) {
            $hasIssue = true;
            $lines[] = '';
            $lines[] = '⚠ VM ที่ status ผิดปกติ:';
            $lines[] = $this->bulletList($abnormalStatus->map(fn ($item) => "{$item->name} (status: {$item->status})"));
        }

        if ($storageFindings->isNotEmpty()) {
            $hasIssue = true;
            $lines[] = '';
            $lines[] = '⚠ พื้นที่จัดเก็บใกล้เต็ม (เรียงจากมากไปน้อย):';
            $lines[] = $this->bulletList($storageFindings->map(
                fn ($f) => "{$f['item']->name} ใช้ไป {$f['pct']}% (Used {$f['item']->used_space} / Provisioned {$f['item']->provisioned_space}) [{$f['level']}]"
            ));
        }

        if ($cpuFindings->isNotEmpty()) {
            $hasIssue = true;
            $lines[] = '';
            $lines[] = '⚠ Host CPU ใช้งานสูง:';
            $lines[] = $this->bulletList($cpuFindings->map(
                fn ($f) => "{$f['item']->name}: Host CPU {$f['item']->host_cpu} [{$f['level']}]"
            ));
        }

        if ($memFindings->isNotEmpty()) {
            $hasIssue = true;
            $lines[] = '';
            $lines[] = '⚠ Host Memory ใช้งานสูง:';
            $lines[] = $this->bulletList($memFindings->map(
                fn ($f) => "{$f['item']->name}: Host Memory {$f['item']->host_mem} [{$f['level']}]"
            ));
        }

        $lines[] = '';
        $lines[] = $hasIssue
            ? 'ข้อเสนอแนะ: ควรตรวจสอบและดำเนินการแก้ไขรายการที่มีเครื่องหมาย ⚠ โดยเฉพาะรายการระดับ Critical ก่อน เพื่อป้องกันผลกระทบต่อบริการ'
            : 'สรุป: ระบบทั้งหมดอยู่ในเกณฑ์ปกติ ไม่มีข้อควรระวังที่ต้องดำเนินการเพิ่มเติมสำหรับวันนี้';

        return implode("\n", $lines);
    }

    protected function filterByKeywords(Collection $items, string $field, array $keywords, bool $matchIfContains): Collection
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

                return [
                    'item' => $item,
                    'pct' => $pct,
                    'level' => $pct >= self::STORAGE_CRITICAL ? 'Critical' : 'Warning',
                ];
            })
            ->filter()
            ->sortByDesc('pct')
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

                return [
                    'item' => $item,
                    'value' => $value,
                    'level' => $value >= self::UTIL_CRITICAL ? 'Critical' : 'Warning',
                ];
            })
            ->filter()
            ->sortByDesc('value')
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

    protected function bulletList(Collection $lines, int $limit = 15): string
    {
        $shown = $lines->take($limit)->map(fn ($line) => "  - {$line}");
        $remaining = $lines->count() - $shown->count();

        if ($remaining > 0) {
            $shown->push("  - และอีก {$remaining} รายการ");
        }

        return $shown->implode("\n");
    }
}

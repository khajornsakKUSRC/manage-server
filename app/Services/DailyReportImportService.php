<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class DailyReportImportService
{
    /**
     * Maps normalized (lowercase, no spaces/underscores) header text to a DailyReportItem column.
     */
    protected const COLUMN_MAP = [
        'name' => 'name',
        'vmname' => 'name',
        'host' => 'host',
        'hostname' => 'host',
        'esxihost' => 'host',
        'dns' => 'dns',
        'dnsname' => 'dns',
        'state' => 'state',
        'status' => 'status',
        'provisionedspace' => 'provisioned_space',
        'provisioned' => 'provisioned_space',
        'usedspace' => 'used_space',
        'used' => 'used_space',
        'hostcpu' => 'host_cpu',
        'cpu' => 'host_cpu',
        'hostmem' => 'host_mem',
        'hostmemory' => 'host_mem',
        'mem' => 'host_mem',
        'memory' => 'host_mem',
    ];

    public function import(UploadedFile $file, string $reportDate, ?int $importedBy = null): DailyReport
    {
        $rows = $this->readRows($file);

        if (empty($rows)) {
            throw new RuntimeException('ไม่พบข้อมูลในไฟล์ที่อัปโหลด');
        }

        $header = array_shift($rows);
        $columnIndex = $this->mapHeader($header);

        if (! isset($columnIndex['name'])) {
            throw new RuntimeException('ไม่พบคอลัมน์ "Name" ในไฟล์ Excel กรุณาตรวจสอบหัวตาราง');
        }

        $items = [];

        foreach ($rows as $row) {
            $name = $this->cell($row, $columnIndex['name'] ?? null);

            if ($name === null || $name === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'host' => $this->cell($row, $columnIndex['host'] ?? null),
                'dns' => $this->cell($row, $columnIndex['dns'] ?? null),
                'state' => $this->cell($row, $columnIndex['state'] ?? null),
                'status' => $this->cell($row, $columnIndex['status'] ?? null),
                'provisioned_space' => $this->cell($row, $columnIndex['provisioned_space'] ?? null),
                'used_space' => $this->cell($row, $columnIndex['used_space'] ?? null),
                'host_cpu' => $this->cell($row, $columnIndex['host_cpu'] ?? null),
                'host_mem' => $this->cell($row, $columnIndex['host_mem'] ?? null),
            ];
        }

        if (empty($items)) {
            throw new RuntimeException('ไม่พบแถวข้อมูลที่มีค่า Name ในไฟล์ที่อัปโหลด');
        }

        return $this->persist($items, $reportDate, $importedBy, [
            'original_filename' => $file->getClientOriginalName(),
            'source' => 'import',
        ]);
    }

    /**
     * Upserts a DailyReport by date and replaces its items. Shared by the
     * Excel import flow and any other source (e.g. a live vSphere save)
     * that already has rows shaped like a DailyReportItem.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $reportAttributes  extra DailyReport columns to set (e.g. inspector, checklist)
     */
    public function persist(array $items, string $reportDate, ?int $importedBy, array $reportAttributes = []): DailyReport
    {
        return DB::transaction(function () use ($items, $reportDate, $importedBy, $reportAttributes) {
            $report = DailyReport::updateOrCreate(
                ['report_date' => $reportDate],
                array_merge([
                    'original_filename' => null,
                    'imported_by' => $importedBy,
                    'summary' => null,
                ], $reportAttributes)
            );

            $report->items()->delete();

            foreach (array_chunk($items, 500) as $chunk) {
                $chunk = array_map(fn ($item) => $item + [
                    'daily_report_id' => $report->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk);
                DailyReportItem::insert($chunk);
            }

            return $report->fresh('items');
        });
    }

    protected function readRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    }

    protected function mapHeader(array $header): array
    {
        $columnIndex = [];

        foreach ($header as $index => $label) {
            $normalized = $this->normalizeHeader((string) $label);

            if (isset(self::COLUMN_MAP[$normalized]) && ! isset($columnIndex[self::COLUMN_MAP[$normalized]])) {
                $columnIndex[self::COLUMN_MAP[$normalized]] = $index;
            }
        }

        return $columnIndex;
    }

    protected function normalizeHeader(string $label): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $label));
    }

    protected function cell(array $row, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $value = trim((string) $row[$index]);

        return $value === '' ? null : $value;
    }
}

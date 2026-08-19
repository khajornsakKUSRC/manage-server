<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyReportItem;

class DailyReportVsphereService
{
    public function __construct(
        protected VsphereService $vsphere,
        protected DailyReportPdfService $pdfService,
    ) {}

    /**
     * Builds the same report view-model as DailyReportPdfService::buildData(),
     * but sourced live from vCenter instead of imported DailyReportItem rows.
     * Nothing is persisted here — this is a preview the user reviews and
     * edits (checklist, verdict, inspector) before explicitly saving.
     */
    public function preview(string $reportDate): array
    {
        $vms = $this->vsphere->getVms();
        $hosts = $this->vsphere->getHosts();
        $hostMap = $this->vsphere->getVmHostMap();
        $allHostsConnected = collect($hosts)->isNotEmpty()
            && collect($hosts)->every(fn ($host) => ($host['connection_state'] ?? null) === 'CONNECTED');

        $items = collect($vms)
            ->values()
            ->map(function (array $vm, int $index) use ($hostMap) {
                $item = new DailyReportItem([
                    'name' => $vm['name'],
                    'host' => $hostMap[$vm['vm']] ?? null,
                    'dns' => null,
                    'state' => strtolower($vm['power_state'] ?? ''),
                    'status' => null,
                    'provisioned_space' => null,
                    'used_space' => null,
                    'host_cpu' => null,
                    'host_mem' => null,
                ]);

                // Synthetic id: DailyReportPdfService matches findings back to
                // rows by id, which only works if these unsaved rows have one.
                $item->id = $index + 1;

                return $item;
            });

        $report = new DailyReport(['report_date' => $reportDate]);
        $report->setRelation('items', $items);

        $data = $this->pdfService->buildData($report);

        // DailyReportPdfService's checklist is built for Excel-imported rows.
        // A vSphere-sourced report has no per-VM CPU/Mem/storage data, so
        // those three would silently read "checked" (no findings raised)
        // even though nothing was actually verified — mark them honestly as
        // not derivable instead. Host connection_state IS real data here
        // (unlike the Excel path), so Network Connectivity can be upgraded
        // to an actual check. Indices below match the fixed order
        // DailyReportPdfService::buildData() always builds the checklist in.
        $checklist = $data['checklist'];
        $checklist[1]['derivable'] = true; // Network Connectivity
        $checklist[1]['checked'] = $allHostsConnected;
        $checklist[2]['derivable'] = false; // CPU Usage
        $checklist[2]['checked'] = false;
        $checklist[3]['derivable'] = false; // Memory Usage
        $checklist[3]['checked'] = false;
        $checklist[4]['derivable'] = false; // Storage
        $checklist[4]['checked'] = false;
        $data['checklist'] = $checklist;

        $derivableAllChecked = collect($checklist)->where('derivable', true)->every(fn ($c) => $c['checked']);
        $data['overallNormal'] = collect($data['problemVms'])->isEmpty() && $derivableAllChecked;

        $data['reportDate'] = $reportDate;
        $data['items'] = $items->map(fn (DailyReportItem $item) => $item->only([
            'name', 'host', 'dns', 'state', 'status',
            'provisioned_space', 'used_space', 'host_cpu', 'host_mem',
        ]))->values();

        return $data;
    }
}

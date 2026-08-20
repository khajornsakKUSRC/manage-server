import type { RequestPayload } from '@inertiajs/core';
import { Head, router, usePage } from '@inertiajs/react';
import { Cloud, FileDown, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyError, notifyInfo, notifySuccess } from '@/lib/swal';

interface ReportRow {
    id?: number;
    name: string;
    host: string | null;
    power_state: string | null;
    cpu_count: number | null;
    memory_gb: number | null;
    disk_usage_pct: number | null;
    uptime_seconds: number | null;
    certificate_exp: string | null;
    notes: string | null;
}

interface DailyReport {
    id: number;
    report_date: string;
    incident: string | null;
    action: string | null;
    remark: string | null;
    items: ReportRow[];
}

interface Props {
    availableDates: string[];
    selectedDate: string;
    report: DailyReport | null;
    incidentOptions: Record<string, string>;
    actionOptions: Record<string, string>;
}

const NONE = 'none';

function isUp(row: ReportRow): boolean {
    return row.power_state === 'POWERED_ON';
}

function formatUptime(seconds: number | null): string {
    if (seconds === null) {
        return '-';
    }

    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
        return `${days}d ${hours}h`;
    }

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }

    return `${minutes}m`;
}

function diskBadgeClass(pct: number | null): string {
    if (pct === null) {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }

    if (pct >= 85) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }

    if (pct >= 70) {
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    }

    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
}

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function Index({
    availableDates,
    selectedDate,
    report,
    incidentOptions,
    actionOptions,
}: Props) {
    const { errors } = usePage().props as unknown as {
        errors?: Record<string, string>;
    };

    const [date, setDate] = useState(selectedDate);
    const [exportOpen, setExportOpen] = useState(false);
    const [exportStart, setExportStart] = useState(selectedDate);
    const [exportEnd, setExportEnd] = useState(selectedDate);

    // A failed export redirects back with a flashed validation error via a
    // plain browser navigation (window.location.href, not an Inertia visit),
    // so the app remounts fresh with that error already in props — a
    // mount-only effect is the right place to surface it, same pattern as
    // the app-wide flash-toast listener.
    useEffect(() => {
        if (errors?.start_date) {
            notifyError(errors.start_date, 'ส่งออก PDF ไม่สำเร็จ');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const goToDate = (value: string) => {
        setDate(value);
        router.get('/daily-reports', { date: value }, { preserveState: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            start_date: exportStart,
            end_date: exportEnd,
        });
        notifyInfo('กำลังสร้างไฟล์ PDF และเริ่มดาวน์โหลด...', 'กำลังดำเนินการ');
        window.location.href = `/daily-reports/export?${params.toString()}`;
        setExportOpen(false);
    };

    return (
        <>
            <Head title="Daily Report" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Daily Report</h1>

                    <Dialog open={exportOpen} onOpenChange={setExportOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <FileDown className="mr-2 h-4 w-4" />
                                Export PDF
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Export Daily Report เป็น PDF
                                </DialogTitle>
                            </DialogHeader>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="export-start">
                                        ตั้งแต่วันที่
                                    </Label>
                                    <Input
                                        id="export-start"
                                        type="date"
                                        value={exportStart}
                                        onChange={(e) =>
                                            setExportStart(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="export-end">
                                        ถึงวันที่
                                    </Label>
                                    <Input
                                        id="export-end"
                                        type="date"
                                        value={exportEnd}
                                        onChange={(e) =>
                                            setExportEnd(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            {errors?.start_date && (
                                <p className="text-sm text-red-500">
                                    {errors.start_date}
                                </p>
                            )}
                            <DialogFooter>
                                <Button onClick={handleExport}>
                                    <FileDown className="mr-2 h-4 w-4" />
                                    ดาวน์โหลด PDF
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-4 pt-6">
                        <div className="space-y-2">
                            <Label htmlFor="date">เลือกวันที่</Label>
                            <Input
                                id="date"
                                type="date"
                                className="w-48"
                                value={date}
                                onChange={(e) => goToDate(e.target.value)}
                            />
                        </div>

                        {availableDates.length > 0 && (
                            <div className="ml-auto flex flex-wrap gap-2">
                                {availableDates.slice(0, 10).map((d) => (
                                    <button
                                        key={d}
                                        onClick={() => goToDate(d)}
                                        className={`rounded-full border px-2.5 py-1 text-xs transition-colors ${
                                            d === date
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-transparent bg-muted/50 hover:bg-muted'
                                        }`}
                                    >
                                        {d}
                                    </button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Keyed by date so navigating to a different day remounts this
                    form fresh from that day's saved report, instead of an
                    effect reaching back to resync state from new props. */}
                <ReportForm
                    key={selectedDate}
                    date={selectedDate}
                    report={report}
                    incidentOptions={incidentOptions}
                    actionOptions={actionOptions}
                />
            </div>
        </>
    );
}

function ReportForm({
    date,
    report,
    incidentOptions,
    actionOptions,
}: {
    date: string;
    report: DailyReport | null;
    incidentOptions: Record<string, string>;
    actionOptions: Record<string, string>;
}) {
    const [rows, setRows] = useState<ReportRow[]>(report?.items ?? []);
    const [pulling, setPulling] = useState(false);

    const [incident, setIncident] = useState(report?.incident ?? NONE);
    const [action, setAction] = useState(report?.action ?? NONE);
    const [remark, setRemark] = useState(report?.remark ?? '');
    const [saving, setSaving] = useState(false);

    const handlePull = async () => {
        setPulling(true);

        try {
            const response = await fetch('/daily-reports/pull', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error('pull failed');
            }

            const data: { items: ReportRow[] } = await response.json();
            setRows(data.items);
            notifySuccess(
                `ดึงข้อมูล ${data.items.length} เครื่องจาก vCenter สำเร็จ`,
                'ดึงข้อมูลสำเร็จ',
            );
        } catch {
            notifyError(
                'ไม่สามารถดึงข้อมูลจาก vCenter ได้',
                'ดึงข้อมูลไม่สำเร็จ',
            );
        } finally {
            setPulling(false);
        }
    };

    const handleSave = () => {
        if (rows.length === 0) {
            return;
        }

        setSaving(true);
        router.post(
            '/daily-reports',
            {
                report_date: date,
                incident: incident === NONE ? '' : incident,
                action: action === NONE ? '' : action,
                remark,
                items: rows,
                // Inertia's FormDataConvertible constraint requires an
                // explicit index signature, which named interfaces don't
                // structurally provide — the payload itself is plain JSON.
            } as unknown as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () =>
                    notifySuccess('บันทึกรายงานประจำวันสำเร็จ', 'บันทึกสำเร็จ'),
                onError: (formErrors) => {
                    const message =
                        Object.values(formErrors)[0] ??
                        'กรุณาตรวจสอบข้อมูลอีกครั้ง';
                    notifyError(message, 'บันทึกไม่สำเร็จ');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const up = rows.filter(isUp).length;

    return (
        <>
            <Card className="flex min-h-0 flex-1 flex-col">
                <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                    <CardTitle>
                        ข้อมูลอัตโนมัติจาก vCenter
                        {rows.length > 0 && (
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {rows.length} เครื่อง — UP {up} / DOWN{' '}
                                {rows.length - up}
                            </span>
                        )}
                    </CardTitle>
                    <Button size="sm" onClick={handlePull} disabled={pulling}>
                        <Cloud
                            className={`mr-2 h-4 w-4 ${pulling ? 'animate-pulse' : ''}`}
                        />
                        {pulling
                            ? 'กำลังดึงข้อมูล...'
                            : 'ดึงข้อมูลล่าสุดจาก vCenter'}
                    </Button>
                </CardHeader>
                <CardContent className="flex min-h-0 flex-1 flex-col">
                    {rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {report
                                ? 'ยังไม่มีข้อมูล VM ในรายงานนี้'
                                : 'กด "ดึงข้อมูลล่าสุดจาก vCenter" เพื่อดึงสถานะ Server, CPU, RAM, Disk และ Uptime โดยอัตโนมัติ'}
                        </p>
                    ) : (
                        <div className="max-h-80 overflow-auto rounded-md border">
                            <table className="w-full text-left text-sm">
                                <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Host
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            CPU
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            RAM
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Disk
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Uptime
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Certificate Exp
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Note
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.map((row, i) => (
                                        <tr
                                            key={`${row.name}-${i}`}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="px-3 py-2 font-medium">
                                                {row.name}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.host || '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    className={
                                                        isUp(row)
                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                    }
                                                >
                                                    {isUp(row) ? 'UP' : 'DOWN'}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.cpu_count !== null
                                                    ? `${row.cpu_count} vCPU`
                                                    : '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.memory_gb !== null
                                                    ? `${row.memory_gb} GB`
                                                    : '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    className={diskBadgeClass(
                                                        row.disk_usage_pct,
                                                    )}
                                                >
                                                    {row.disk_usage_pct !== null
                                                        ? `${row.disk_usage_pct}%`
                                                        : 'N/A'}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatUptime(
                                                    row.uptime_seconds,
                                                )}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {row.certificate_exp || '-'}
                                            </td>
                                            <td
                                                className="max-w-[200px] truncate px-3 py-2"
                                                title={row.notes ?? undefined}
                                            >
                                                {row.notes || '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>บันทึกเหตุการณ์ประจำวัน</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-3">
                    <div className="space-y-2">
                        <Label>Incident (ถ้ามี)</Label>
                        <Select value={incident} onValueChange={setIncident}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="เลือก Incident" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>ไม่มี</SelectItem>
                                {Object.entries(incidentOptions).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>Action</Label>
                        <Select value={action} onValueChange={setAction}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="เลือก Action" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>ไม่มี</SelectItem>
                                {Object.entries(actionOptions).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2 sm:col-span-1">
                        <Label htmlFor="remark">Remark</Label>
                        <textarea
                            id="remark"
                            className="flex min-h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            rows={1}
                            value={remark}
                            onChange={(e) => setRemark(e.target.value)}
                            placeholder="หมายเหตุเพิ่มเติม"
                        />
                    </div>
                </CardContent>
                <CardContent className="pt-0">
                    <Button
                        onClick={handleSave}
                        disabled={saving || rows.length === 0}
                    >
                        <Save className="mr-2 h-4 w-4" />
                        {saving ? 'กำลังบันทึก...' : 'บันทึกรายงาน'}
                    </Button>
                </CardContent>
            </Card>
        </>
    );
}

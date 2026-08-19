import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import { Cloud, FileDown, Printer, Save, Sparkles, Upload } from 'lucide-react';
import { useState } from 'react';
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

interface DailyReportItem {
    id: number;
    name: string;
    host: string | null;
    dns: string | null;
    state: string | null;
    status: string | null;
    provisioned_space: string | null;
    used_space: string | null;
    host_cpu: string | null;
    host_mem: string | null;
}

interface DailyReport {
    id: number;
    report_date: string;
    original_filename: string | null;
    summary: string | null;
    items: DailyReportItem[];
}

interface Props {
    availableDates: string[];
    selectedDate: string | null;
    report: DailyReport | null;
}

interface VsphereChecklistItem {
    label: string;
    derivable: boolean;
    checked: boolean;
}

interface VsphereHostRow {
    host: string;
    total: number;
    online: number;
    pass: boolean;
}

interface VsphereProblemVm {
    host: string;
    name: string;
    dns: string;
    status: string;
    remark: string;
}

interface VsphereReportRow {
    name: string;
    host: string | null;
    dns: string | null;
    state: string | null;
    status: string | null;
    provisioned_space: string | null;
    used_space: string | null;
    host_cpu: string | null;
    host_mem: string | null;
}

interface VsphereReportPreview {
    total: number;
    poweredOn: number;
    poweredOff: number;
    availabilityPct: number;
    hostRows: VsphereHostRow[];
    checklist: VsphereChecklistItem[];
    problemVms: VsphereProblemVm[];
    overallNormal: boolean;
    reasonText: string;
    reportDate: string;
    items: VsphereReportRow[];
}

function isAbnormalStatus(status: string | null): boolean {
    if (!status) {
        return false;
    }

    const s = status.toLowerCase();

    return !['ok', 'normal', 'good', 'healthy', 'green', 'ปกติ'].some((ok) =>
        s.includes(ok),
    );
}

function isDownState(state: string | null): boolean {
    if (!state) {
        return false;
    }

    const s = state.toLowerCase();

    return [
        'stop',
        'off',
        'disconnect',
        'error',
        'fail',
        'invalid',
        'unknown',
    ].some((kw) => s.includes(kw));
}

function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export default function Index({ availableDates, selectedDate, report }: Props) {
    const [date, setDate] = useState(selectedDate ?? '');
    const [generating, setGenerating] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [inspector, setInspector] = useState('');

    const [vspherePreview, setVspherePreview] =
        useState<VsphereReportPreview | null>(null);
    const [vsphereLoading, setVsphereLoading] = useState(false);
    const [vsphereError, setVsphereError] = useState<string | null>(null);
    const [vsphereSaving, setVsphereSaving] = useState(false);
    const [vsphereInspector, setVsphereInspector] = useState('');
    const [vsphereChecklist, setVsphereChecklist] = useState<
        VsphereChecklistItem[]
    >([]);
    const [vsphereOverallNormal, setVsphereOverallNormal] = useState(true);
    const [vsphereReasonText, setVsphereReasonText] = useState('');

    const goToDate = (value: string) => {
        setDate(value);
        router.get('/daily-reports', { date: value }, { preserveState: true });
    };

    const handleGenerate = () => {
        if (!date) {
            return;
        }

        setGenerating(true);
        router.post(
            '/daily-reports/generate',
            { date },
            { preserveScroll: true, onFinish: () => setGenerating(false) },
        );
    };

    const handleExport = () => {
        const params = new URLSearchParams({ date, inspector });
        window.location.href = `/daily-reports/export?${params.toString()}`;
        setExportOpen(false);
    };

    const handleGenerateFromVsphere = async () => {
        if (!date) {
            return;
        }

        setVsphereLoading(true);
        setVsphereError(null);

        try {
            const response = await fetch('/daily-reports/vsphere/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ date }),
            });

            if (!response.ok) {
                throw new Error('vsphere preview failed');
            }

            const data: VsphereReportPreview = await response.json();

            setVspherePreview(data);
            setVsphereChecklist(data.checklist);
            setVsphereOverallNormal(data.overallNormal);
            setVsphereReasonText(data.reasonText);
            setVsphereInspector('');
        } catch {
            setVsphereError('ไม่สามารถสร้างรายงานจาก vCenter ได้');
        } finally {
            setVsphereLoading(false);
        }
    };

    const handleSaveVsphereReport = () => {
        if (!vspherePreview) {
            return;
        }

        setVsphereSaving(true);
        router.post(
            '/daily-reports/vsphere/save',
            {
                report_date: vspherePreview.reportDate,
                inspector: vsphereInspector,
                overall_normal: vsphereOverallNormal,
                reason_text: vsphereOverallNormal ? '' : vsphereReasonText,
                checklist: vsphereChecklist,
                items: vspherePreview.items,
                // Inertia's FormDataConvertible constraint requires an
                // explicit index signature, which named interfaces don't
                // structurally provide — the payload itself is plain JSON.
            } as unknown as RequestPayload,
            {
                preserveScroll: true,
                onSuccess: () => setVspherePreview(null),
                onFinish: () => setVsphereSaving(false),
            },
        );
    };

    const toggleChecklistItem = (index: number) => {
        setVsphereChecklist((prev) =>
            prev.map((item, i) =>
                i === index ? { ...item, checked: !item.checked } : item,
            ),
        );
    };

    return (
        <>
            <Head title="Daily Report" />
            <style>{`
                @media print {
                    body * { visibility: hidden; }
                    #vsphere-report-print, #vsphere-report-print * { visibility: visible; }
                    #vsphere-report-print { position: absolute; left: 0; top: 0; width: 100%; }
                }
            `}</style>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold">Daily Report</h1>
                    <Button asChild>
                        <Link href="/daily-reports/import">
                            <Upload className="mr-2 h-4 w-4" />
                            Import Daily Report
                        </Link>
                    </Button>
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
                        <Button
                            variant="outline"
                            onClick={handleGenerate}
                            disabled={generating || !report}
                        >
                            <Sparkles
                                className={`mr-2 h-4 w-4 ${generating ? 'animate-pulse' : ''}`}
                            />
                            {generating
                                ? 'กำลังสร้างรายงาน...'
                                : 'Generate Report'}
                        </Button>

                        <Button
                            variant="outline"
                            onClick={handleGenerateFromVsphere}
                            disabled={vsphereLoading || !date}
                        >
                            <Cloud
                                className={`mr-2 h-4 w-4 ${vsphereLoading ? 'animate-pulse' : ''}`}
                            />
                            {vsphereLoading
                                ? 'กำลังดึงข้อมูล...'
                                : 'Generate from vCenter'}
                        </Button>

                        <Dialog open={exportOpen} onOpenChange={setExportOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" disabled={!report}>
                                    <FileDown className="mr-2 h-4 w-4" />
                                    Export PDF
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Export Daily Report เป็น PDF</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-2">
                                    <Label htmlFor="inspector">ผู้ตรวจสอบ</Label>
                                    <Input
                                        id="inspector"
                                        value={inspector}
                                        onChange={(e) => setInspector(e.target.value)}
                                        placeholder="ชื่อผู้ตรวจสอบ"
                                    />
                                </div>
                                <DialogFooter>
                                    <Button onClick={handleExport}>
                                        <FileDown className="mr-2 h-4 w-4" />
                                        ดาวน์โหลด PDF
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

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

                {vsphereError && (
                    <Card className="border-l-4 border-l-red-500">
                        <CardContent className="pt-6">
                            <p className="text-sm text-red-600 dark:text-red-400">
                                {vsphereError}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {vspherePreview && (
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
                            <CardTitle>
                                ร่างรายงานจาก vCenter (ยังไม่บันทึก)
                            </CardTitle>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => window.print()}
                                >
                                    <Printer className="mr-2 h-4 w-4" />
                                    Print
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSaveVsphereReport}
                                    disabled={vsphereSaving}
                                >
                                    <Save className="mr-2 h-4 w-4" />
                                    {vsphereSaving
                                        ? 'กำลังบันทึก...'
                                        : 'บันทึกลงฐานข้อมูล'}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setVspherePreview(null)}
                                >
                                    ยกเลิก
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                id="vsphere-report-print"
                                className="mx-auto max-w-3xl space-y-4 rounded-lg border bg-white p-6 text-black"
                            >
                                <div className="text-center">
                                    <h2 className="text-lg font-bold">
                                        รายงานการตรวจสอบระบบ Virtual Machine
                                        ประจำวัน
                                    </h2>
                                    <p className="font-semibold">
                                        Daily VM Health Check Report
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold">
                                            ผู้ตรวจสอบ
                                        </span>
                                        <Input
                                            className="h-8 w-48 text-black"
                                            value={vsphereInspector}
                                            onChange={(e) =>
                                                setVsphereInspector(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="ชื่อผู้ตรวจสอบ"
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold">
                                            รอบการตรวจ
                                        </span>
                                        <span className="rounded-full bg-gray-200 px-3 py-0.5">
                                            Daily
                                        </span>
                                    </div>
                                    <span className="ml-auto">
                                        วันที่: {vspherePreview.reportDate}
                                    </span>
                                </div>

                                <table className="w-full border-collapse text-sm">
                                    <caption className="border border-gray-400 bg-gray-100 px-2 py-1 text-left font-semibold">
                                        สรุปสถานะ VM
                                    </caption>
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="border border-gray-400 px-2 py-1">
                                                VM ทั้งหมด
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                Powered On
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                Powered Off
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                Availability
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr className="text-center">
                                            <td className="border border-gray-400 px-2 py-1">
                                                {vspherePreview.total} เครื่อง
                                            </td>
                                            <td className="border border-gray-400 px-2 py-1">
                                                {vspherePreview.poweredOn}{' '}
                                                เครื่อง
                                            </td>
                                            <td className="border border-gray-400 px-2 py-1">
                                                {vspherePreview.poweredOff}{' '}
                                                เครื่อง
                                            </td>
                                            <td className="border border-gray-400 px-2 py-1">
                                                {vspherePreview.availabilityPct}
                                                %
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table className="w-full border-collapse text-sm">
                                    <caption className="border border-gray-400 bg-gray-100 px-2 py-1 text-left font-semibold">
                                        ตรวจสอบสถานะ Host
                                    </caption>
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="border border-gray-400 px-2 py-1">
                                                Host
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                VM ทั้งหมด
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                ผลตรวจ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vspherePreview.hostRows.map((row) => (
                                            <tr
                                                key={row.host}
                                                className="text-center"
                                            >
                                                <td className="border border-gray-400 px-2 py-1">
                                                    {row.host}
                                                </td>
                                                <td className="border border-gray-400 px-2 py-1">
                                                    {row.total}
                                                </td>
                                                <td className="border border-gray-400 px-2 py-1">
                                                    {row.pass
                                                        ? 'ปกติ'
                                                        : 'พบปัญหา'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <div>
                                    <p className="mb-1 font-semibold">
                                        Checklist ตรวจสอบ VM ประจำวัน
                                    </p>
                                    <div className="space-y-1">
                                        {vsphereChecklist.map(
                                            (item, index) => (
                                                <label
                                                    key={item.label}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="h-4 w-4"
                                                        checked={item.checked}
                                                        onChange={() =>
                                                            toggleChecklistItem(
                                                                index,
                                                            )
                                                        }
                                                    />
                                                    <span>{item.label}</span>
                                                    {!item.derivable && (
                                                        <span className="text-xs text-gray-500">
                                                            (ไม่มีข้อมูลตรวจสอบอัตโนมัติ
                                                            — โปรดตรวจด้วยตนเอง)
                                                        </span>
                                                    )}
                                                </label>
                                            ),
                                        )}
                                    </div>
                                </div>

                                <table className="w-full border-collapse text-sm">
                                    <caption className="border border-gray-400 bg-gray-100 px-2 py-1 text-left font-semibold">
                                        VM ที่พบปัญหา
                                    </caption>
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="border border-gray-400 px-2 py-1">
                                                Host
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                VM Name
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                Status
                                            </th>
                                            <th className="border border-gray-400 px-2 py-1">
                                                หมายเหตุ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vspherePreview.problemVms.length ===
                                        0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="border border-gray-400 px-2 py-1 text-center"
                                                >
                                                    -
                                                </td>
                                            </tr>
                                        ) : (
                                            vspherePreview.problemVms.map(
                                                (vm, i) => (
                                                    <tr
                                                        key={`${vm.name}-${i}`}
                                                        className="text-center"
                                                    >
                                                        <td className="border border-gray-400 px-2 py-1">
                                                            {vm.host}
                                                        </td>
                                                        <td className="border border-gray-400 px-2 py-1">
                                                            {vm.name}
                                                        </td>
                                                        <td className="border border-gray-400 px-2 py-1">
                                                            {vm.status}
                                                        </td>
                                                        <td className="border border-gray-400 px-2 py-1">
                                                            {vm.remark}
                                                        </td>
                                                    </tr>
                                                ),
                                            )
                                        )}
                                    </tbody>
                                </table>

                                <div className="flex flex-wrap items-center gap-4 text-sm">
                                    <span className="font-semibold">
                                        สรุปผลการตรวจสอบ
                                    </span>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4"
                                            checked={vsphereOverallNormal}
                                            onChange={() =>
                                                setVsphereOverallNormal(true)
                                            }
                                        />
                                        ปกติ
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4"
                                            checked={!vsphereOverallNormal}
                                            onChange={() =>
                                                setVsphereOverallNormal(false)
                                            }
                                        />
                                        ผิดปกติ
                                    </label>
                                    <div className="flex min-w-[240px] flex-1 items-center gap-2">
                                        <span>เพราะ</span>
                                        <Input
                                            className="h-8 flex-1 text-black"
                                            value={vsphereReasonText}
                                            onChange={(e) =>
                                                setVsphereReasonText(
                                                    e.target.value,
                                                )
                                            }
                                            disabled={vsphereOverallNormal}
                                            placeholder={
                                                vsphereOverallNormal
                                                    ? '-'
                                                    : 'ระบุเหตุผล'
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {!report ? (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">
                                {date
                                    ? `ไม่พบข้อมูลรายงานสำหรับวันที่ ${date} กรุณา Import ข้อมูลก่อน หรือกด Generate from vCenter`
                                    : 'กรุณาเลือกวันที่เพื่อดูรายงาน หรือ Import ข้อมูลใหม่'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>สรุปรายงาน (AI Summary)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="font-sans text-sm leading-relaxed whitespace-pre-wrap">
                                    {report.summary}
                                </pre>
                                {report.original_filename && (
                                    <p className="mt-4 text-xs text-muted-foreground">
                                        ที่มาไฟล์: {report.original_filename}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    รายละเอียดทั้งหมด ({report.items.length}{' '}
                                    รายการ)
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-2 font-medium">
                                                    Name
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Host
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    DNS
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    State
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Provisioned Space
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Used Space
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Host CPU
                                                </th>
                                                <th className="px-4 py-2 font-medium">
                                                    Host Mem
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {report.items.map((item) => (
                                                <tr
                                                    key={item.id}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        {item.name}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.host || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.dns || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                isDownState(
                                                                    item.state,
                                                                )
                                                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                                    : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                            }`}
                                                        >
                                                            {item.state || '-'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                isAbnormalStatus(
                                                                    item.status,
                                                                )
                                                                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                            }`}
                                                        >
                                                            {item.status || '-'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.provisioned_space ||
                                                            '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.used_space || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.host_cpu || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {item.host_mem || '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}

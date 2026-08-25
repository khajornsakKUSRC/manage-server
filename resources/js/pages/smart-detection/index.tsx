import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    RefreshCw,
    ShieldCheck,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyError, notifySuccess } from '@/lib/swal';

interface Finding {
    id: number;
    vm: { id: number | null; name: string; ip: string | null };
    category: string;
    category_label: string;
    severity: 'info' | 'warning' | 'critical';
    title: string;
    detail: string | null;
    status: 'open' | 'acknowledged' | 'resolved';
    first_detected_at: string;
    last_detected_at: string;
    acknowledged_at: string | null;
    resolved_at: string | null;
}

interface Props {
    categories: Record<string, string>;
}

const ALL = '__all__';
const POLL_MS = 60_000;

const SEVERITY_STYLES: Record<Finding['severity'], string> = {
    critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    warning:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
};

const STATUS_STYLES: Record<Finding['status'], string> = {
    open: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    acknowledged:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    resolved:
        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
};

function formatTime(value: string): string {
    return new Date(value).toLocaleString();
}

export default function Index({ categories }: Props) {
    const [findings, setFindings] = useState<Finding[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState<string>(ALL);
    const [categoryFilter, setCategoryFilter] = useState<string>(ALL);
    const [severityFilter, setSeverityFilter] = useState<string>(ALL);
    const [actingId, setActingId] = useState<number | null>(null);

    const fetchFindings = useCallback(async (): Promise<Finding[]> => {
        const params = new URLSearchParams();

        // Backend default (no "status" param) is "not resolved" — pass it
        // explicitly only when the user picked something other than "All".
        if (statusFilter !== ALL) {
            params.set('status', statusFilter);
        } else {
            params.set('status', 'all');
        }

        if (categoryFilter !== ALL) {
            params.set('category', categoryFilter);
        }

        if (severityFilter !== ALL) {
            params.set('severity', severityFilter);
        }

        const res = await fetch(
            `/api/smart-detection/findings?${params.toString()}`,
        );

        if (!res.ok) {
            throw new Error('findings request failed');
        }

        const json = await res.json();

        return json.data ?? [];
    }, [statusFilter, categoryFilter, severityFilter]);

    const load = useCallback(
        async (showSpinner: boolean) => {
            if (showSpinner) {
                setLoading(true);
            }

            setError(null);

            try {
                const data = await fetchFindings();
                setFindings(data);
                setUpdatedAt(new Date().toLocaleTimeString());
            } catch {
                setError('ไม่สามารถโหลดข้อมูล Smart Detection ได้');
            } finally {
                if (showSpinner) {
                    setLoading(false);
                }
            }
        },
        [fetchFindings],
    );

    useEffect(() => {
        // Deferred via setTimeout so the initial fetch's setState calls
        // don't run synchronously inside the effect body.
        const initial = setTimeout(() => load(true), 0);
        const interval = setInterval(() => load(false), POLL_MS);

        return () => {
            clearTimeout(initial);
            clearInterval(interval);
        };
    }, [load]);

    const summary = useMemo(() => {
        const rows = findings ?? [];

        return {
            critical: rows.filter(
                (f) => f.severity === 'critical' && f.status !== 'resolved',
            ).length,
            warning: rows.filter(
                (f) => f.severity === 'warning' && f.status !== 'resolved',
            ).length,
            info: rows.filter(
                (f) => f.severity === 'info' && f.status !== 'resolved',
            ).length,
        };
    }, [findings]);

    const act = (finding: Finding, action: 'acknowledge' | 'resolve') => {
        setActingId(finding.id);

        router.post(
            `/smart-detection/findings/${finding.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    notifySuccess(
                        action === 'acknowledge'
                            ? 'รับทราบรายการนี้แล้ว'
                            : 'ปิดรายการนี้แล้ว',
                        'สำเร็จ',
                    );
                    load(false);
                },
                onError: () =>
                    notifyError('ไม่สามารถอัปเดตสถานะได้', 'ไม่สำเร็จ'),
                onFinish: () => setActingId(null),
            },
        );
    };

    return (
        <>
            <Head title="Smart Detection" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Smart Detection
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Brute-force, unknown process, malware-pattern,
                            new port/service, and service-failure findings
                            across every VM Smart Detection can reach over
                            SSH.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {updatedAt && (
                            <span className="text-xs text-muted-foreground">
                                อัปเดตล่าสุด: {updatedAt}
                            </span>
                        )}
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => load(true)}
                            disabled={loading}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card className="border-l-4 border-l-red-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Critical
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.critical}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-amber-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Warning
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.warning}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-blue-500">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.info}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {error && (
                    <Card className="border-l-4 border-l-red-500">
                        <CardContent className="flex items-center justify-between gap-4 pt-6">
                            <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <p className="text-sm">{error}</p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => load(true)}
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                            <ShieldCheck className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <CardTitle>Findings</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <Select
                                value={statusFilter}
                                onValueChange={setStatusFilter}
                            >
                                <SelectTrigger className="w-[160px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Active (default)
                                    </SelectItem>
                                    <SelectItem value="open">Open</SelectItem>
                                    <SelectItem value="acknowledged">
                                        Acknowledged
                                    </SelectItem>
                                    <SelectItem value="resolved">
                                        Resolved
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Select
                                value={categoryFilter}
                                onValueChange={setCategoryFilter}
                            >
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All Categories
                                    </SelectItem>
                                    {Object.entries(categories).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>

                            <Select
                                value={severityFilter}
                                onValueChange={setSeverityFilter}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Severity" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All Severities
                                    </SelectItem>
                                    <SelectItem value="critical">
                                        Critical
                                    </SelectItem>
                                    <SelectItem value="warning">
                                        Warning
                                    </SelectItem>
                                    <SelectItem value="info">Info</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {loading && !findings ? (
                            <div className="h-32 animate-pulse rounded bg-muted" />
                        ) : !findings || findings.length === 0 ? (
                            <div className="flex items-center gap-3 rounded-lg border border-l-4 border-l-green-500 p-4">
                                <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                                <p className="text-sm text-muted-foreground">
                                    No findings match the current filters.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                VM
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Category
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Severity
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Finding
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Last Detected
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {findings.map((finding) => (
                                            <tr
                                                key={finding.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    <p className="font-medium">
                                                        {finding.vm.name}
                                                    </p>
                                                    {finding.vm.ip && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {finding.vm.ip}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    {finding.category_label}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    <Badge
                                                        className={
                                                            SEVERITY_STYLES[
                                                                finding
                                                                    .severity
                                                            ]
                                                        }
                                                    >
                                                        {finding.severity.toUpperCase()}
                                                    </Badge>
                                                </td>
                                                <td className="max-w-md px-3 py-2">
                                                    <p className="font-medium">
                                                        {finding.title}
                                                    </p>
                                                    {finding.detail && (
                                                        <p className="text-xs break-words text-muted-foreground">
                                                            {finding.detail}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                    {formatTime(
                                                        finding.last_detected_at,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    <Badge
                                                        className={
                                                            STATUS_STYLES[
                                                                finding.status
                                                            ]
                                                        }
                                                    >
                                                        {finding.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2 text-right whitespace-nowrap">
                                                    {finding.status ===
                                                        'open' && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="mr-2"
                                                            disabled={
                                                                actingId ===
                                                                finding.id
                                                            }
                                                            onClick={() =>
                                                                act(
                                                                    finding,
                                                                    'acknowledge',
                                                                )
                                                            }
                                                        >
                                                            Acknowledge
                                                        </Button>
                                                    )}
                                                    {finding.status !==
                                                        'resolved' && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled={
                                                                actingId ===
                                                                finding.id
                                                            }
                                                            onClick={() =>
                                                                act(
                                                                    finding,
                                                                    'resolve',
                                                                )
                                                            }
                                                        >
                                                            Resolve
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

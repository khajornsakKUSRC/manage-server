import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    BellRing,
    CheckCircle2,
    Lightbulb,
    RefreshCw,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface AlarmEntry {
    name: string;
    description: string;
    status: string;
    time: string | null;
    acknowledged: boolean;
    hint: string | null;
}

interface AlarmObject {
    type: 'Host' | 'VM' | 'Datastore';
    name: string;
    alarms: AlarmEntry[];
}

const STATUS_STYLES: Record<string, string> = {
    red: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    yellow: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    green: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
};

const TYPE_STYLES: Record<string, string> = {
    Host: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    VM: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    Datastore:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
};

function statusClass(status: string): string {
    return STATUS_STYLES[status] ?? STATUS_STYLES.gray;
}

function formatTime(time: string | null): string {
    if (!time) {
        return '-';
    }

    return new Date(time).toLocaleString();
}

export default function Index() {
    const [objects, setObjects] = useState<AlarmObject[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);

    const fetchAlarms = useCallback(async (): Promise<AlarmObject[]> => {
        const res = await fetch('/api/vsphere/alarms');

        if (!res.ok) {
            throw new Error('alarms request failed');
        }

        const json = await res.json();

        return json.data ?? [];
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await fetchAlarms();
            setObjects(data);
            setUpdatedAt(new Date().toLocaleTimeString());
        } catch {
            setError('ไม่สามารถโหลดข้อมูล Alarm จาก vCenter ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchAlarms]);

    useEffect(() => {
        let cancelled = false;

        fetchAlarms()
            .then((data) => {
                if (cancelled) {
                    return;
                }

                setObjects(data);
                setUpdatedAt(new Date().toLocaleTimeString());
            })
            .catch(() => {
                if (!cancelled) {
                    setError('ไม่สามารถโหลดข้อมูล Alarm จาก vCenter ได้');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [fetchAlarms]);

    const totalAlarms =
        objects?.reduce((sum, o) => sum + o.alarms.length, 0) ?? 0;

    const rows =
        objects?.flatMap((object) =>
            object.alarms.map((alarm, i) => ({
                object,
                alarm,
                key: `${object.type}-${object.name}-${i}`,
            })),
        ) ?? [];

    return (
        <>
            <Head title="Alarm Notification" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Alarm Notification
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Hosts, VMs, and datastores with currently triggered
                            vCenter alarms, with AI-generated hints on how to
                            resolve them.
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
                            onClick={load}
                            disabled={loading}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </Button>
                    </div>
                </div>

                {error && (
                    <Card className="border-l-4 border-l-red-500">
                        <CardContent className="flex items-center justify-between gap-4 pt-6">
                            <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <p className="text-sm">{error}</p>
                            </div>
                            <Button size="sm" variant="outline" onClick={load}>
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {loading && !objects ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i}>
                                <CardContent className="pt-6">
                                    <div className="h-16 animate-pulse rounded bg-muted" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    objects && (
                        <>
                            {objects.length === 0 ? (
                                <Card className="border-l-4 border-l-green-500">
                                    <CardContent className="flex items-center gap-3 pt-6">
                                        <div className="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                                            <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-semibold">
                                                No triggered alarms
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                No hosts, VMs, or datastores
                                                currently have an active vCenter
                                                alarm.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : (
                                <>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                                            <div className="rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                                                <BellRing className="h-4 w-4 text-red-600 dark:text-red-400" />
                                            </div>
                                            <CardTitle>
                                                Objects with most alerts
                                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                                    {objects.length} object
                                                    {objects.length > 1
                                                        ? 's'
                                                        : ''}{' '}
                                                    — {totalAlarms} alarm
                                                    {totalAlarms > 1 ? 's' : ''}
                                                </span>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            {objects.map((object) => {
                                                const pct = Math.round(
                                                    (object.alarms.length /
                                                        objects[0].alarms
                                                            .length) *
                                                        100,
                                                );

                                                return (
                                                    <div
                                                        key={`${object.type}-${object.name}`}
                                                        className="flex items-center gap-3"
                                                    >
                                                        <div className="flex w-56 shrink-0 items-center gap-2">
                                                            <Badge
                                                                className={
                                                                    TYPE_STYLES[
                                                                        object
                                                                            .type
                                                                    ]
                                                                }
                                                            >
                                                                {object.type}
                                                            </Badge>
                                                            <span
                                                                className="truncate text-sm font-medium"
                                                                title={
                                                                    object.name
                                                                }
                                                            >
                                                                {object.name}
                                                            </span>
                                                        </div>
                                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                            <div
                                                                className="h-full rounded-full bg-red-500"
                                                                style={{
                                                                    width: `${pct}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="w-20 shrink-0 text-right text-sm text-muted-foreground">
                                                            {
                                                                object.alarms
                                                                    .length
                                                            }{' '}
                                                            alert
                                                            {object.alarms
                                                                .length > 1
                                                                ? 's'
                                                                : ''}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </CardContent>
                                    </Card>

                                    <Card className="flex min-h-0 flex-1 flex-col">
                                        <CardHeader>
                                            <CardTitle>Alarm Details</CardTitle>
                                        </CardHeader>
                                        <CardContent className="min-h-0 flex-1">
                                            <div className="overflow-auto rounded-md border">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground uppercase">
                                                        <tr>
                                                            <th className="px-3 py-2 font-medium">
                                                                Object
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Alarm
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Severity
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Triggered At
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Ack
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                AI Suggested Fix
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y">
                                                        {rows.map(
                                                            ({
                                                                object,
                                                                alarm,
                                                                key,
                                                            }) => (
                                                                <tr
                                                                    key={key}
                                                                    className="hover:bg-muted/30"
                                                                >
                                                                    <td className="px-3 py-2">
                                                                        <div className="flex items-center gap-2">
                                                                            <Badge
                                                                                className={
                                                                                    TYPE_STYLES[
                                                                                        object
                                                                                            .type
                                                                                    ]
                                                                                }
                                                                            >
                                                                                {
                                                                                    object.type
                                                                                }
                                                                            </Badge>
                                                                            <span className="font-medium whitespace-nowrap">
                                                                                {
                                                                                    object.name
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        <p className="font-medium">
                                                                            {
                                                                                alarm.name
                                                                            }
                                                                        </p>
                                                                        {alarm.description && (
                                                                            <p className="text-xs text-muted-foreground">
                                                                                {
                                                                                    alarm.description
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        <Badge
                                                                            className={statusClass(
                                                                                alarm.status,
                                                                            )}
                                                                        >
                                                                            {alarm.status.toUpperCase()}
                                                                        </Badge>
                                                                    </td>
                                                                    <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                                        {formatTime(
                                                                            alarm.time,
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {alarm.acknowledged ? (
                                                                            <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                                        ) : (
                                                                            <span className="text-muted-foreground">
                                                                                -
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="max-w-sm min-w-[16rem] px-3 py-2">
                                                                        {alarm.hint ? (
                                                                            <div className="flex items-start gap-1.5 text-sm">
                                                                                <Lightbulb className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
                                                                                <span>
                                                                                    {
                                                                                        alarm.hint
                                                                                    }
                                                                                </span>
                                                                            </div>
                                                                        ) : (
                                                                            <span className="text-xs text-muted-foreground">
                                                                                ไม่มีคำแนะนำ
                                                                                (ยังไม่ได้ตั้งค่า
                                                                                AI)
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            )}
                        </>
                    )
                )}
            </div>
        </>
    );
}

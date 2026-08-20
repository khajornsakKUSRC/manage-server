import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeftRight,
    Cpu,
    HardDrive,
    MemoryStick,
    RefreshCw,
    ShieldCheck,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ApplianceMetric {
    id: string;
    units: string | null;
    instance: string | null;
    value: number | null;
}

interface ApplianceOverview {
    health: Record<string, string>;
    metrics: ApplianceMetric[];
}

const HEALTH_COMPONENTS: { key: string; label: string }[] = [
    { key: 'system', label: 'Overall System' },
    { key: 'cpu', label: 'CPU' },
    { key: 'mem', label: 'Memory' },
    { key: 'swap', label: 'Swap' },
    { key: 'storage', label: 'Storage' },
    { key: 'database_storage', label: 'Database Storage' },
    { key: 'load', label: 'System Load' },
    { key: 'applmgmt', label: 'Appliance Management' },
    { key: 'software_packages', label: 'Software Updates' },
];

const METRIC_GROUPS: {
    key: string;
    label: string;
    icon: typeof Cpu;
    prefix: string;
}[] = [
    { key: 'cpu', label: 'CPU', icon: Cpu, prefix: 'cpu.' },
    { key: 'mem', label: 'Memory', icon: MemoryStick, prefix: 'mem.' },
    { key: 'swap', label: 'Swap', icon: ArrowLeftRight, prefix: 'swap.' },
    { key: 'storage', label: 'Storage', icon: HardDrive, prefix: 'storage.' },
];

const HEALTH_STYLES: Record<
    string,
    { badge: string; dot: string; label: string }
> = {
    green: {
        badge: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        dot: 'bg-green-500',
        label: 'Normal',
    },
    yellow: {
        badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        dot: 'bg-amber-500',
        label: 'Warning',
    },
    orange: {
        badge: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        dot: 'bg-orange-500',
        label: 'Degraded',
    },
    red: {
        badge: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        dot: 'bg-red-500',
        label: 'Critical',
    },
    gray: {
        badge: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
        dot: 'bg-gray-400',
        label: 'Unknown',
    },
};

function healthStyle(status: string | undefined) {
    return HEALTH_STYLES[status ?? 'gray'] ?? HEALTH_STYLES.gray;
}

function formatBytesFromKb(kb: number): string {
    const gb = kb / 1024 ** 2;

    if (gb >= 1024) {
        return `${(gb / 1024).toFixed(1)} TB`;
    }

    if (gb >= 1) {
        return `${gb.toFixed(1)} GB`;
    }

    return `${(kb / 1024).toFixed(1)} MB`;
}

// vCenter reports units as dotted codes like "com.vmware.applmgmt.mon.unit.kb"
// — only the last segment ("kb", "percent", "mhz", "kb_per_sec", ...) is
// meaningful.
function unitCode(units: string | null): string {
    if (!units) {
        return '';
    }

    const parts = units.split('.');

    return parts[parts.length - 1];
}

const RATE_UNIT_LABELS: Record<string, string> = {
    load_per_min: 'load/min',
    errors_per_sample: 'errors/sample',
    pages_per_sec: 'pages/s',
    num_of_io_per_msec: 'IO/ms',
    msec_per_io: 'ms/IO',
    packets_per_sec: 'pkt/s',
    drops_per_sample: 'drops/sample',
};

function formatMetricValue(metric: ApplianceMetric): string {
    if (metric.value === null || metric.value === undefined) {
        return 'No data';
    }

    const code = unitCode(metric.units);

    // A handful of storage.used/totalsize items on this vCenter are tagged
    // "percent" but actually report raw KB (a metadata bug on the appliance
    // side, confirmed against live data — genuine percentages never exceed
    // 100). Render those as sizes instead of a nonsensical "473624%".
    if (code === 'percent' && metric.value <= 100) {
        return `${metric.value.toFixed(1)}%`;
    }

    if (code === 'kb' || (code === 'percent' && metric.value > 100)) {
        return formatBytesFromKb(metric.value);
    }

    if (code === 'kb_per_sec') {
        return `${formatBytesFromKb(metric.value)}/s`;
    }

    if (code === 'mhz') {
        return `${metric.value.toLocaleString()} MHz`;
    }

    const label = RATE_UNIT_LABELS[code] ?? code.replace(/_/g, ' ');

    return `${metric.value.toLocaleString()}${label ? ' ' + label : ''}`;
}

function isPercent(metric: ApplianceMetric): boolean {
    return unitCode(metric.units) === 'percent' && (metric.value ?? 0) <= 100;
}

// Monitoring items' `name`/`description` fields are untranslated
// localization keys, not human text (e.g. "com.vmware.applmgmt.mon.name.
// cpu.util") — so the display label is derived from the metric `id` itself.
const ID_WORD_LABELS: Record<string, string> = {
    cpu: 'CPU',
    mem: 'Memory',
    util: 'Utilization',
    usage: 'Usage',
    filesystem: 'Filesystem',
    directory: 'Directory',
    totalsize: 'Total Size',
    totalfrequency: 'Total Frequency',
    systemload: 'System Load',
    pagerate: 'Page Rate',
    db: 'DB',
    dblog: 'DB Log',
    swap: 'Swap',
    storage: 'Storage',
};

function humanizeIdSegment(id: string): string {
    return id
        .split(/[._]/)
        .filter(Boolean)
        .flatMap((part) => part.replace(/([a-z])([A-Z])/g, '$1 $2').split(' '))
        .map(
            (word) =>
                ID_WORD_LABELS[word.toLowerCase()] ??
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase(),
        )
        .join(' ');
}

function barColor(pct: number): string {
    if (pct >= 85) {
        return 'bg-red-500';
    }

    if (pct >= 70) {
        return 'bg-amber-500';
    }

    return 'bg-blue-500';
}

export default function Index() {
    const [overview, setOverview] = useState<ApplianceOverview | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);

    const fetchOverview = useCallback(async (): Promise<ApplianceOverview> => {
        const res = await fetch('/api/vsphere/appliance');

        if (!res.ok) {
            throw new Error('appliance request failed');
        }

        const json = await res.json();

        return {
            health: json.data?.health ?? {},
            metrics: json.data?.metrics ?? [],
        };
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await fetchOverview();
            setOverview(data);
            setUpdatedAt(new Date().toLocaleTimeString());
        } catch {
            setError('ไม่สามารถโหลดข้อมูล Appliance จาก vCenter ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchOverview]);

    useEffect(() => {
        let cancelled = false;

        fetchOverview()
            .then((data) => {
                if (cancelled) {
                    return;
                }

                setOverview(data);
                setUpdatedAt(new Date().toLocaleTimeString());
            })
            .catch(() => {
                if (!cancelled) {
                    setError(
                        'ไม่สามารถโหลดข้อมูล Appliance จาก vCenter ได้',
                    );
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
    }, [fetchOverview]);

    const overallHealth = healthStyle(overview?.health.system);

    const metricGroups = useMemo(
        () =>
            METRIC_GROUPS.map((group) => ({
                ...group,
                metrics: (overview?.metrics ?? [])
                    .filter((m) => m.id.startsWith(group.prefix))
                    .map((m) => ({
                        ...m,
                        label: humanizeIdSegment(
                            m.id.slice(group.prefix.length),
                        ),
                    }))
                    .sort((a, b) => a.label.localeCompare(b.label)),
            })),
        [overview],
    );

    return (
        <>
            <Head title="Appliance Health" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">
                            vCenter Appliance Health
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Live status and resource utilization of the
                            vCenter Server Appliance itself.
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

                {loading && !overview ? (
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
                    overview && (
                        <>
                            <Card
                                className={`border-l-4 ${
                                    overview.health.system === 'green'
                                        ? 'border-l-green-500'
                                        : overview.health.system === 'red'
                                          ? 'border-l-red-500'
                                          : 'border-l-amber-500'
                                }`}
                            >
                                <CardContent className="flex items-center gap-3 pt-6">
                                    <div
                                        className={`rounded-lg p-2 ${overallHealth.badge}`}
                                    >
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {overallHealth.label === 'Normal'
                                                ? 'All appliance systems operational'
                                                : `Appliance status: ${overallHealth.label}`}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Based on the vCenter Server
                                            Appliance's own health checks
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Subsystem Health</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {HEALTH_COMPONENTS.map(
                                            ({ key, label }) => {
                                                const style = healthStyle(
                                                    overview.health[key],
                                                );

                                                return (
                                                    <div
                                                        key={key}
                                                        className="flex items-center justify-between rounded-lg border p-3"
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <span
                                                                className={`h-2 w-2 rounded-full ${style.dot}`}
                                                            />
                                                            <span className="text-sm font-medium">
                                                                {label}
                                                            </span>
                                                        </div>
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${style.badge}`}
                                                        >
                                                            {style.label}
                                                        </span>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="grid gap-4 lg:grid-cols-2">
                                {metricGroups.map((group) => (
                                    <Card key={group.key}>
                                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                                            <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                                                <group.icon className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <CardTitle className="text-base">
                                                {group.label}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {group.metrics.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    ไม่มีข้อมูล
                                                </p>
                                            ) : (
                                                group.metrics.map(
                                                    (metric) => (
                                                        <div key={metric.id}>
                                                            <div className="mb-1 flex items-center justify-between text-xs">
                                                                <span className="font-medium">
                                                                    {
                                                                        metric.label
                                                                    }
                                                                    {metric.instance && (
                                                                        <span className="text-muted-foreground">
                                                                            {' '}
                                                                            (
                                                                            {
                                                                                metric.instance
                                                                            }

                                                                            )
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="text-muted-foreground">
                                                                    {formatMetricValue(
                                                                        metric,
                                                                    )}
                                                                </span>
                                                            </div>
                                                            {isPercent(
                                                                metric,
                                                            ) &&
                                                                metric.value !==
                                                                    null && (
                                                                    <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                                        <div
                                                                            className={`h-full rounded-full ${barColor(metric.value)}`}
                                                                            style={{
                                                                                width: `${Math.min(100, Math.max(0, metric.value))}%`,
                                                                            }}
                                                                        />
                                                                    </div>
                                                                )}
                                                        </div>
                                                    ),
                                                )
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </>
                    )
                )}
            </div>
        </>
    );
}

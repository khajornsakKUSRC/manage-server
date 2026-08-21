import { Head } from '@inertiajs/react';
import { AlertCircle, Database, RefreshCw, TrendingUp } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    Area,
    CartesianGrid,
    ComposedChart,
    Line,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';

interface HistoryPoint {
    date: string;
    used_pct: number;
}

interface DatastoreTrend {
    name: string;
    type: string | null;
    capacity: number;
    free_space: number;
    used: number;
    used_pct: number;
    history: HistoryPoint[];
    daily_growth_bytes: number | null;
    days_until_full: number | null;
    projected_full_date: string | null;
    projection: HistoryPoint[] | null;
}

interface ChartPoint {
    date: string;
    actual: number | null;
    projected: number | null;
}

// Matches this app's existing blue used for storage/identity elsewhere
// (dashboard/appliance icon accents) — kept consistent rather than
// introducing an unrelated hue just for this chart.
const SERIES_COLOR = { light: '#2563eb', dark: '#60a5fa' };
const GRID_COLOR = { light: '#e5e7eb', dark: '#27272a' };
const AXIS_COLOR = { light: '#71717a', dark: '#a1a1aa' };

function formatBytes(bytes: number): string {
    const gb = bytes / 1024 ** 3;

    if (gb >= 1024) {
        return `${(gb / 1024).toFixed(1)} TB`;
    }

    return `${gb.toFixed(1)} GB`;
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

// Same thresholds as the disk-usage badges elsewhere in the app
// (daily-reports, appliance) — kept consistent.
function usageBadgeClass(pct: number): string {
    if (pct >= 85) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }

    if (pct >= 70) {
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    }

    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
}

function urgencyBadgeClass(days: number): string {
    if (days <= 30) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }

    if (days <= 90) {
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    }

    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
}

function buildChartData(
    history: HistoryPoint[],
    projection: HistoryPoint[] | null,
): ChartPoint[] {
    const points: ChartPoint[] = history.map((p, i) => ({
        date: p.date,
        actual: p.used_pct,
        // The dashed projection segment starts exactly where the solid
        // history line ends, so there's no visual gap between them.
        projected: i === history.length - 1 && projection ? p.used_pct : null,
    }));

    if (projection) {
        // projection[0] is the same point as the last history entry
        // (already added above) — only append what comes after it.
        projection
            .slice(1)
            .forEach((p) =>
                points.push({
                    date: p.date,
                    actual: null,
                    projected: p.used_pct,
                }),
            );
    }

    return points;
}

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: { value: number | null; dataKey: string }[];
    label?: string;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const point = payload.find(
        (p) => p.value !== null && p.value !== undefined,
    );

    if (!point) {
        return null;
    }

    const isProjected = point.dataKey === 'projected';

    return (
        <div className="rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md">
            <p className="text-muted-foreground">{formatDate(label ?? '')}</p>
            <p className="font-semibold text-popover-foreground">
                {point.value?.toFixed(1)}%{' '}
                {isProjected && (
                    <span className="font-normal text-muted-foreground">
                        (projected)
                    </span>
                )}
            </p>
        </div>
    );
}

function DatastoreCard({ datastore }: { datastore: DatastoreTrend }) {
    const { resolvedAppearance } = useAppearance();
    const seriesColor = SERIES_COLOR[resolvedAppearance];
    const gridColor = GRID_COLOR[resolvedAppearance];
    const axisColor = AXIS_COLOR[resolvedAppearance];

    const chartData = useMemo(
        () => buildChartData(datastore.history, datastore.projection),
        [datastore.history, datastore.projection],
    );

    const hasProjection = datastore.days_until_full !== null;

    return (
        <Card>
            <CardContent className="flex flex-col gap-4 pt-6 lg:flex-row lg:items-center">
                <div className="flex shrink-0 flex-col gap-2 lg:w-64">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <Database className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="min-w-0">
                            <p
                                className="truncate text-sm font-semibold"
                                title={datastore.name}
                            >
                                {datastore.name}
                            </p>
                            {datastore.type && (
                                <p className="text-xs text-muted-foreground">
                                    {datastore.type}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Badge className={usageBadgeClass(datastore.used_pct)}>
                            {datastore.used_pct}% used
                        </Badge>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        {formatBytes(datastore.used)} /{' '}
                        {formatBytes(datastore.capacity)}
                        <br />
                        {formatBytes(datastore.free_space)} free
                    </p>
                </div>

                <div className="h-40 min-w-0 flex-1">
                    <ResponsiveContainer width="100%" height="100%">
                        <ComposedChart
                            data={chartData}
                            margin={{ top: 8, right: 8, left: -16, bottom: 0 }}
                        >
                            <CartesianGrid
                                stroke={gridColor}
                                strokeWidth={1}
                                vertical={false}
                            />
                            <XAxis
                                dataKey="date"
                                tickFormatter={formatDate}
                                stroke={axisColor}
                                tickLine={false}
                                axisLine={{ stroke: axisColor }}
                                fontSize={11}
                                minTickGap={32}
                            />
                            <YAxis
                                domain={[0, 100]}
                                tickFormatter={(v) => `${v}%`}
                                stroke={axisColor}
                                tickLine={false}
                                axisLine={false}
                                fontSize={11}
                                width={34}
                            />
                            <ReferenceLine
                                y={100}
                                stroke={axisColor}
                                strokeDasharray="2 2"
                            />
                            <Tooltip content={<ChartTooltip />} />
                            <Area
                                type="monotone"
                                dataKey="actual"
                                stroke="none"
                                fill={seriesColor}
                                fillOpacity={0.1}
                                isAnimationActive={false}
                                connectNulls
                            />
                            <Line
                                type="monotone"
                                dataKey="actual"
                                name="Actual"
                                stroke={seriesColor}
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                                connectNulls
                            />
                            {hasProjection && (
                                <Line
                                    type="monotone"
                                    dataKey="projected"
                                    name="Projected"
                                    stroke={seriesColor}
                                    strokeWidth={2}
                                    strokeDasharray="4 4"
                                    dot={false}
                                    isAnimationActive={false}
                                    connectNulls
                                />
                            )}
                        </ComposedChart>
                    </ResponsiveContainer>
                    {hasProjection && (
                        <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                            <span className="inline-flex items-center gap-1">
                                <span className="h-0.5 w-4 bg-current opacity-70" />
                                Actual
                            </span>
                            <span className="inline-flex items-center gap-1">
                                <span
                                    className="h-0.5 w-4"
                                    style={{
                                        backgroundImage:
                                            'repeating-linear-gradient(90deg, currentColor 0 3px, transparent 3px 6px)',
                                        opacity: 0.7,
                                    }}
                                />
                                Projected
                            </span>
                        </div>
                    )}
                </div>

                <div className="shrink-0 lg:w-52">
                    {hasProjection ? (
                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                <Badge
                                    className={urgencyBadgeClass(
                                        datastore.days_until_full!,
                                    )}
                                >
                                    {datastore.days_until_full} days left
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Projected full around{' '}
                                <span className="font-medium text-foreground">
                                    {datastore.projected_full_date}
                                </span>
                            </p>
                            <p className="text-xs text-muted-foreground">
                                ~
                                {formatBytes(datastore.daily_growth_bytes ?? 0)}
                                /day
                            </p>
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            {datastore.daily_growth_bytes !== null &&
                            datastore.daily_growth_bytes <= 0
                                ? 'Usage is flat or decreasing — not projected to fill up.'
                                : 'Not enough history yet to project a fill-up date. Collecting one snapshot per day.'}
                        </p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Index() {
    const [datastores, setDatastores] = useState<DatastoreTrend[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);

    const fetchDatastores = useCallback(async (): Promise<DatastoreTrend[]> => {
        const res = await fetch('/api/vsphere/datastores/trends');

        if (!res.ok) {
            throw new Error('datastores request failed');
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
            const data = await fetchDatastores();
            setDatastores(data);
            setUpdatedAt(new Date().toLocaleTimeString());
        } catch {
            setError('ไม่สามารถโหลดข้อมูล Datastore จาก vCenter ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchDatastores]);

    useEffect(() => {
        let cancelled = false;

        fetchDatastores()
            .then((data) => {
                if (cancelled) {
                    return;
                }

                setDatastores(data);
                setUpdatedAt(new Date().toLocaleTimeString());
            })
            .catch(() => {
                if (!cancelled) {
                    setError('ไม่สามารถโหลดข้อมูล Datastore จาก vCenter ได้');
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
    }, [fetchDatastores]);

    return (
        <>
            <Head title="Datastore" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">Datastore</h1>
                        <p className="text-sm text-muted-foreground">
                            Capacity trend and projected fill-up date for every
                            datastore, based on daily usage snapshots.
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

                {loading && !datastores ? (
                    <div className="space-y-4">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i}>
                                <CardContent className="pt-6">
                                    <div className="h-32 animate-pulse rounded bg-muted" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    datastores && (
                        <div className="space-y-4">
                            {datastores.map((datastore) => (
                                <DatastoreCard
                                    key={datastore.name}
                                    datastore={datastore}
                                />
                            ))}
                        </div>
                    )
                )}
            </div>
        </>
    );
}

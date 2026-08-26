import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, Network, Plus, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Area, AreaChart, ResponsiveContainer, Tooltip, YAxis } from 'recharts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/hooks/use-appearance';

type MonitorStatus = 'up' | 'down' | 'pending' | 'paused';

interface Heartbeat {
    checked_at: string;
    status: 'up' | 'down';
    response_time_ms: number | null;
}

interface Monitor {
    id: number;
    name: string;
    category: string;
    category_label: string;
    type: string;
    type_label: string;
    target: string;
    port: number | null;
    is_active: boolean;
    status: MonitorStatus;
    last_checked_at: string | null;
    last_response_time_ms: number | null;
    last_message: string | null;
    uptime_24h_pct: number | null;
    heartbeats: Heartbeat[];
}

const STATUS_POLL_MS = 20_000;
const HEARTBEAT_SLOTS = 30;

// Fixed display order — matches the categories called out in the request
// (WAN, Gateway, Services, DNS, Switch); any category not in this list
// (there shouldn't be one, since the form only offers these) falls back to
// appearing after them in whatever order the API returned it.
const CATEGORY_ORDER = ['wan', 'gateway', 'services', 'dns', 'switch'];

const SERIES_COLOR = { light: '#2563eb', dark: '#60a5fa' };

function statusDotClass(status: MonitorStatus): string {
    switch (status) {
        case 'up':
            return 'bg-green-500';
        case 'down':
            return 'bg-red-500';
        case 'paused':
            return 'bg-gray-400';
        default:
            return 'bg-yellow-400';
    }
}

function statusBadgeClass(status: MonitorStatus): string {
    switch (status) {
        case 'up':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'down':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'paused':
            return 'bg-gray-100 text-gray-600 dark:bg-gray-800/60 dark:text-gray-400';
        default:
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    }
}

function statusLabel(status: MonitorStatus): string {
    switch (status) {
        case 'up':
            return 'Up';
        case 'down':
            return 'Down';
        case 'paused':
            return 'Paused';
        default:
            return 'Pending';
    }
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

// Pads the front with empty slots so every bar renders HEARTBEAT_SLOTS
// segments regardless of how much history exists yet (e.g. a monitor added
// minutes ago) — keeps every monitor's bar visually the same width.
function heartbeatSlots(heartbeats: Heartbeat[]): (Heartbeat | null)[] {
    const trimmed = heartbeats.slice(-HEARTBEAT_SLOTS);
    const padding = Array<null>(HEARTBEAT_SLOTS - trimmed.length).fill(null);

    return [...padding, ...trimmed];
}

function HeartbeatBar({ heartbeats }: { heartbeats: Heartbeat[] }) {
    return (
        <div className="flex items-center gap-0.5">
            {heartbeatSlots(heartbeats).map((beat, i) =>
                beat ? (
                    <div
                        key={i}
                        title={`${formatTime(beat.checked_at)} — ${beat.status === 'up' ? 'Up' : 'Down'}${beat.response_time_ms !== null ? ` (${beat.response_time_ms} ms)` : ''}`}
                        className={`h-6 w-1.5 rounded-sm ${beat.status === 'up' ? 'bg-green-500' : 'bg-red-500'}`}
                    />
                ) : (
                    <div
                        key={i}
                        className="h-6 w-1.5 rounded-sm bg-gray-200 dark:bg-gray-700"
                    />
                ),
            )}
        </div>
    );
}

function ResponseTimeChart({ heartbeats }: { heartbeats: Heartbeat[] }) {
    const { resolvedAppearance } = useAppearance();
    const color = SERIES_COLOR[resolvedAppearance];

    const data = useMemo(
        () =>
            heartbeats.map((beat) => ({
                time: formatTime(beat.checked_at),
                ms: beat.response_time_ms,
            })),
        [heartbeats],
    );

    if (data.length < 2) {
        return (
            <div className="flex h-14 items-center justify-center text-xs text-muted-foreground">
                Not enough history yet
            </div>
        );
    }

    return (
        <div className="h-14">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart
                    data={data}
                    margin={{ top: 4, right: 4, bottom: 0, left: 4 }}
                >
                    <YAxis hide domain={['dataMin', 'dataMax']} />
                    <Tooltip
                        content={({ active, payload, label }) => {
                            if (!active || !payload || payload.length === 0) {
                                return null;
                            }

                            const value = payload[0]?.value;

                            return (
                                <div className="rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-md">
                                    <p className="text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="font-semibold text-popover-foreground">
                                        {value === null || value === undefined
                                            ? 'timeout'
                                            : `${value} ms`}
                                    </p>
                                </div>
                            );
                        }}
                    />
                    <Area
                        type="monotone"
                        dataKey="ms"
                        stroke={color}
                        fill={color}
                        fillOpacity={0.15}
                        strokeWidth={1.5}
                        connectNulls
                        isAnimationActive={false}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

function MonitorCard({ monitor }: { monitor: Monitor }) {
    const handleDelete = () => {
        if (
            window.confirm(`Are you sure you want to delete "${monitor.name}"?`)
        ) {
            router.delete(`/network-monitors/${monitor.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Card>
            <CardContent className="flex flex-col gap-3 pt-6">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2">
                        <span
                            className={`h-2.5 w-2.5 shrink-0 rounded-full ${statusDotClass(monitor.status)}`}
                        />
                        <div className="min-w-0">
                            <p
                                className="truncate text-sm font-semibold"
                                title={monitor.name}
                            >
                                {monitor.name}
                            </p>
                            <p
                                className="truncate text-xs text-muted-foreground"
                                title={monitor.target}
                            >
                                {monitor.type_label} · {monitor.target}
                                {monitor.port ? `:${monitor.port}` : ''}
                            </p>
                        </div>
                    </div>
                    <Badge className={statusBadgeClass(monitor.status)}>
                        {statusLabel(monitor.status)}
                    </Badge>
                </div>

                <HeartbeatBar heartbeats={monitor.heartbeats} />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {monitor.last_response_time_ms !== null
                            ? `${monitor.last_response_time_ms} ms`
                            : '—'}
                    </span>
                    <span>
                        {monitor.uptime_24h_pct !== null
                            ? `${monitor.uptime_24h_pct}% uptime (24h)`
                            : 'No data yet'}
                    </span>
                </div>

                {monitor.status === 'down' && monitor.last_message && (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {monitor.last_message}
                    </p>
                )}

                <ResponseTimeChart heartbeats={monitor.heartbeats} />

                <div className="flex justify-end gap-2 pt-1">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/network-monitors/${monitor.id}/edit`}>
                            Edit
                        </Link>
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={handleDelete}
                    >
                        Delete
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Index({
    categories,
}: {
    categories: Record<string, string>;
    types: Record<string, string>;
}) {
    const [monitors, setMonitors] = useState<Monitor[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchStatus = useCallback(async (): Promise<Monitor[]> => {
        const res = await fetch('/api/network-monitors/status');

        if (!res.ok) {
            throw new Error('request failed');
        }

        const json = await res.json();

        return Array.isArray(json.data) ? json.data : [];
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            setMonitors(await fetchStatus());
        } catch {
            setError('ไม่สามารถโหลดสถานะ Network Infrastructure ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchStatus]);

    useEffect(() => {
        let cancelled = false;

        const poll = () => {
            fetchStatus()
                .then((data) => {
                    if (!cancelled) {
                        setMonitors(data);
                        setError(null);
                    }
                })
                .catch(() => {
                    if (!cancelled) {
                        setError(
                            'ไม่สามารถโหลดสถานะ Network Infrastructure ได้',
                        );
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setLoading(false);
                    }
                });
        };

        poll();
        const interval = setInterval(poll, STATUS_POLL_MS);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [fetchStatus]);

    const groupedByCategory = useMemo(() => {
        const map = new Map<string, Monitor[]>();

        for (const monitor of monitors) {
            if (!map.has(monitor.category)) {
                map.set(monitor.category, []);
            }

            map.get(monitor.category)!.push(monitor);
        }

        for (const list of map.values()) {
            list.sort((a, b) => a.name.localeCompare(b.name));
        }

        const orderedKeys = [
            ...CATEGORY_ORDER.filter((key) => map.has(key)),
            ...[...map.keys()].filter((key) => !CATEGORY_ORDER.includes(key)),
        ];

        return orderedKeys.map((key) => ({
            key,
            label: categories[key] ?? key,
            monitors: map.get(key)!,
        }));
    }, [monitors, categories]);

    const downCount = monitors.filter((m) => m.status === 'down').length;

    return (
        <>
            <Head title="Network Infrastructure" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-2 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Network Infrastructure ({monitors.length})
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {downCount > 0
                                ? `${downCount} monitor(s) currently down`
                                : 'All monitors are up'}
                        </p>
                    </div>
                    <div className="flex gap-2">
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
                        <Button size="sm" asChild>
                            <Link href="/network-monitors/create">
                                <Plus className="h-4 w-4" />
                                Add Monitor
                            </Link>
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

                {loading && monitors.length === 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i}>
                                <CardContent className="pt-6">
                                    <div className="h-32 animate-pulse rounded-lg bg-muted" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : monitors.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 pt-6 pb-8 text-center">
                            <Network className="h-8 w-8 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                ยังไม่มี Network Monitor — เพิ่มรายการแรกของคุณ
                            </p>
                            <Button size="sm" asChild>
                                <Link href="/network-monitors/create">
                                    <Plus className="h-4 w-4" />
                                    Add Monitor
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-col gap-6">
                        {groupedByCategory.map((group) => (
                            <div
                                key={group.key}
                                className="flex flex-col gap-3"
                            >
                                <CardHeader className="p-0">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        {group.label}
                                        <span className="text-xs font-normal text-muted-foreground">
                                            ({group.monitors.length})
                                        </span>
                                    </CardTitle>
                                </CardHeader>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {group.monitors.map((monitor) => (
                                        <MonitorCard
                                            key={monitor.id}
                                            monitor={monitor}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

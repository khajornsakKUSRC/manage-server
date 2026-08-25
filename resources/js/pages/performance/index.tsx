import { Head } from '@inertiajs/react';
import { AlertCircle, RefreshCw, Server, Monitor as MonitorIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppearance } from '@/hooks/use-appearance';

interface HostEntity {
    id: string;
    type: 'HostSystem';
    name: string;
    status: string | null;
    power_state: string | null;
}

interface VmEntity {
    id: string;
    type: 'VirtualMachine';
    name: string;
    host: string | null;
    power_state: string | null;
}

type ActiveEntity =
    | (HostEntity & { kind: 'host' })
    | (VmEntity & { kind: 'vm' });

interface SeriesPoint {
    time: string;
    value: number;
}

interface ChartMetric {
    label: string;
    unit: string;
    series: SeriesPoint[];
}

type Metrics = Record<'cpu' | 'memory' | 'memory_rate' | 'disk' | 'network', ChartMetric>;

const CHART_KEYS: (keyof Metrics)[] = ['cpu', 'memory', 'memory_rate', 'disk', 'network'];

const SERIES_COLOR = { light: '#2563eb', dark: '#60a5fa' };
const GRID_COLOR = { light: '#e5e7eb', dark: '#27272a' };
const AXIS_COLOR = { light: '#71717a', dark: '#a1a1aa' };

const REFRESH_MS = 30_000;

// Host view is the default for whichever host is selected — a null VM
// selection means "show the host itself" rather than one of its VMs.
const HOST_VIEW_VALUE = '__host__';

const GOOD_STATUSES = new Set(['CONNECTED', 'POWERED_ON']);

function formatTime(time: string): string {
    const date = new Date(time);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatStatus(status: string | null): string {
    if (!status) {
        return 'Unknown';
    }

    return status
        .toLowerCase()
        .split('_')
        .map((word) => word[0].toUpperCase() + word.slice(1))
        .join(' ');
}

function statusColorClass(status: string | null): string {
    if (!status) {
        return 'text-muted-foreground';
    }

    return GOOD_STATUSES.has(status)
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
}

function latestValue(metric: ChartMetric | undefined): string {
    if (!metric || metric.series.length === 0) {
        return '—';
    }

    const last = metric.series[metric.series.length - 1];

    return `${last.value}${metric.unit === '%' ? '%' : ` ${metric.unit}`}`;
}

function PerformanceChartCard({ metric }: { metric: ChartMetric }) {
    const { resolvedAppearance } = useAppearance();
    const seriesColor = SERIES_COLOR[resolvedAppearance];
    const gridColor = GRID_COLOR[resolvedAppearance];
    const axisColor = AXIS_COLOR[resolvedAppearance];

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">
                    {metric.label}{' '}
                    <span className="font-normal text-muted-foreground">
                        ({metric.unit})
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent className="h-56 pt-0">
                {metric.series.length === 0 ? (
                    <div className="flex h-full items-center justify-center text-xs text-muted-foreground">
                        No real-time data available
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart
                            data={metric.series}
                            margin={{ top: 8, right: 8, left: -16, bottom: 0 }}
                        >
                            <CartesianGrid
                                stroke={gridColor}
                                strokeWidth={1}
                                vertical={false}
                            />
                            <XAxis
                                dataKey="time"
                                tickFormatter={formatTime}
                                stroke={axisColor}
                                tickLine={false}
                                axisLine={{ stroke: axisColor }}
                                fontSize={11}
                                minTickGap={32}
                            />
                            <YAxis
                                stroke={axisColor}
                                tickLine={false}
                                axisLine={false}
                                fontSize={11}
                                width={40}
                            />
                            <Tooltip
                                labelFormatter={(label) => formatTime(String(label))}
                                formatter={(value) => [
                                    `${value} ${metric.unit}`,
                                    metric.label,
                                ]}
                                contentStyle={{ fontSize: 12 }}
                            />
                            <Line
                                type="monotone"
                                dataKey="value"
                                stroke={seriesColor}
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}

function HostCard({
    host,
    selected,
    onSelect,
}: {
    host: HostEntity;
    selected: boolean;
    onSelect: () => void;
}) {
    const healthy = GOOD_STATUSES.has(host.status ?? '');

    return (
        <button
            type="button"
            onClick={onSelect}
            className={`flex flex-col items-start gap-2 rounded-xl border bg-card p-3 text-left transition-colors ${
                selected
                    ? 'border-primary ring-2 ring-primary/20'
                    : 'border-border hover:border-primary/50'
            }`}
        >
            <div className="flex items-center gap-1.5 text-sm font-semibold">
                <Server className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span className="truncate" title={host.name}>
                    {host.name}
                </span>
            </div>
            <div className="text-xs text-muted-foreground">
                Host{selected ? ' · Selected' : ''}
            </div>
            <div
                className={`flex items-center gap-1.5 text-xs font-medium ${statusColorClass(host.status)}`}
            >
                <span
                    className={`h-2 w-2 rounded-full ${healthy ? 'bg-green-500' : 'bg-red-500'}`}
                />
                {formatStatus(host.status)}
            </div>
        </button>
    );
}

export default function Index() {
    const [hosts, setHosts] = useState<HostEntity[] | null>(null);
    const [vms, setVms] = useState<VmEntity[] | null>(null);
    const [selectedHostId, setSelectedHostId] = useState<string | null>(null);
    const [selectedVmId, setSelectedVmId] = useState<string | null>(null);
    const [metrics, setMetrics] = useState<Metrics | null>(null);
    const [loadingEntities, setLoadingEntities] = useState(true);
    const [loadingMetrics, setLoadingMetrics] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        fetch('/api/vsphere/performance/entities')
            .then((res) => (res.ok ? res.json() : Promise.reject()))
            .then((json) => {
                if (cancelled) {
                    return;
                }

                const hostList: HostEntity[] = json.data?.hosts ?? [];
                const vmList: VmEntity[] = json.data?.vms ?? [];

                setHosts(hostList);
                setVms(vmList);
                setSelectedHostId((current) => current ?? hostList[0]?.id ?? null);
            })
            .catch(() => {
                if (!cancelled) {
                    setError('ไม่สามารถโหลดรายการ Host/VM จาก vCenter ได้');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoadingEntities(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const selectedHost = useMemo(
        () => (hosts ?? []).find((h) => h.id === selectedHostId) ?? null,
        [hosts, selectedHostId],
    );

    const hostVms = useMemo(
        () =>
            selectedHost
                ? (vms ?? []).filter((vm) => vm.host === selectedHost.name)
                : [],
        [vms, selectedHost],
    );

    const selectedVm = useMemo(
        () =>
            selectedVmId
                ? (hostVms.find((vm) => vm.id === selectedVmId) ?? null)
                : null,
        [hostVms, selectedVmId],
    );

    const activeEntity: ActiveEntity | null = selectedVm
        ? { ...selectedVm, kind: 'vm' }
        : selectedHost
          ? { ...selectedHost, kind: 'host' }
          : null;

    const loadMetrics = useCallback(
        async (entity: ActiveEntity, showSpinner: boolean) => {
            if (showSpinner) {
                setLoadingMetrics(true);
            }

            setError(null);

            try {
                const params = new URLSearchParams({
                    id: entity.id,
                    type: entity.type,
                });

                const res = await fetch(
                    `/api/vsphere/performance/metrics?${params.toString()}`,
                );

                if (!res.ok) {
                    throw new Error('metrics request failed');
                }

                const json = await res.json();
                setMetrics(json.data ?? null);
                setUpdatedAt(new Date().toLocaleTimeString());
            } catch {
                setError('ไม่สามารถโหลดข้อมูล Performance จาก vCenter ได้');
            } finally {
                if (showSpinner) {
                    setLoadingMetrics(false);
                }
            }
        },
        [],
    );

    useEffect(() => {
        if (!activeEntity) {
            return;
        }

        // Deferred via setTimeout so the initial fetch's setState calls
        // don't run synchronously inside the effect body.
        const initial = setTimeout(() => loadMetrics(activeEntity, true), 0);
        const interval = setInterval(
            () => loadMetrics(activeEntity, false),
            REFRESH_MS,
        );

        return () => {
            clearTimeout(initial);
            clearInterval(interval);
        };
        // activeEntity is derived fresh each render from ids, so key off the
        // ids themselves to avoid re-triggering on unrelated re-renders.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeEntity?.id, activeEntity?.type, loadMetrics]);

    const selectHost = (host: HostEntity) => {
        if (host.id === selectedHostId) {
            return;
        }

        setSelectedHostId(host.id);
        setSelectedVmId(null);
        setMetrics(null);
    };

    const selectVm = (value: string) => {
        setSelectedVmId(value === HOST_VIEW_VALUE ? null : value);
        setMetrics(null);
    };

    return (
        <>
            <Head title="Performance" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">Performance</h1>
                        <p className="text-sm text-muted-foreground">
                            เลือก Host จากการ์ดด้านล่าง แล้วเลือก VM จาก
                            dropdown เพื่อดู Performance ย้อนหลัง 1 ชั่วโมง
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
                            onClick={() =>
                                activeEntity && loadMetrics(activeEntity, true)
                            }
                            disabled={!activeEntity || loadingMetrics}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${loadingMetrics ? 'animate-spin' : ''}`}
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
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    activeEntity && loadMetrics(activeEntity, true)
                                }
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {loadingEntities && !hosts ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {Array.from({ length: 6 }).map((_, i) => (
                            <div
                                key={i}
                                className="h-20 animate-pulse rounded-xl bg-muted"
                            />
                        ))}
                    </div>
                ) : hosts && hosts.length > 0 ? (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {hosts.map((host) => (
                            <HostCard
                                key={host.id}
                                host={host}
                                selected={host.id === selectedHostId}
                                onSelect={() => selectHost(host)}
                            />
                        ))}
                    </div>
                ) : (
                    !error && (
                        <p className="text-sm text-muted-foreground">
                            ไม่พบ Host จาก vCenter
                        </p>
                    )
                )}

                {selectedHost && (
                    <div className="flex items-center gap-2">
                        <MonitorIcon className="h-4 w-4 text-muted-foreground" />
                        <Select
                            value={selectedVmId ?? HOST_VIEW_VALUE}
                            onValueChange={selectVm}
                            disabled={hostVms.length === 0}
                        >
                            <SelectTrigger className="w-72">
                                <SelectValue
                                    placeholder={
                                        hostVms.length === 0
                                            ? 'ไม่มี VM บน Host นี้'
                                            : 'เลือก VM'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={HOST_VIEW_VALUE}>
                                    {selectedHost.name} (Host)
                                </SelectItem>
                                {hostVms.map((vm) => (
                                    <SelectItem key={vm.id} value={vm.id}>
                                        {vm.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {activeEntity && (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-xs text-muted-foreground">
                                        Selected
                                    </p>
                                    <p
                                        className="mt-1 truncate text-lg font-bold"
                                        title={activeEntity.name}
                                    >
                                        {activeEntity.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {activeEntity.kind === 'host'
                                            ? 'Host'
                                            : 'VM'}
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-xs text-muted-foreground">
                                        CPU Usage
                                    </p>
                                    <p className="mt-1 text-2xl font-bold">
                                        {latestValue(metrics?.cpu)}
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-xs text-muted-foreground">
                                        Memory Usage
                                    </p>
                                    <p className="mt-1 text-2xl font-bold">
                                        {latestValue(metrics?.memory)}
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-xs text-muted-foreground">
                                        Status
                                    </p>
                                    <p
                                        className={`mt-1 text-2xl font-bold ${statusColorClass(
                                            activeEntity.kind === 'host'
                                                ? activeEntity.status
                                                : activeEntity.power_state,
                                        )}`}
                                    >
                                        {formatStatus(
                                            activeEntity.kind === 'host'
                                                ? activeEntity.status
                                                : activeEntity.power_state,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {loadingMetrics && !metrics
                                ? CHART_KEYS.map((key) => (
                                      <Card key={key}>
                                          <CardContent className="pt-6">
                                              <div className="h-48 animate-pulse rounded bg-muted" />
                                          </CardContent>
                                      </Card>
                                  ))
                                : metrics &&
                                  CHART_KEYS.map((key) => (
                                      <PerformanceChartCard
                                          key={key}
                                          metric={metrics[key]}
                                      />
                                  ))}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

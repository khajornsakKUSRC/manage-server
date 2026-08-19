import { Head } from '@inertiajs/react';
import {
    Server,
    Monitor,
    RefreshCw,
    AlertCircle,
    HardDrive,
    Power,
    PowerOff,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';

interface VsphereVm {
    vm: string;
    name: string;
    power_state: string;
    cpu_count: number;
    memory_size_MiB: number;
}

interface VsphereHost {
    host: string;
    name: string;
    connection_state: string;
    power_state: string;
}

interface VsphereDatastore {
    datastore: string;
    name: string;
    type: string;
    free_space: number;
    capacity: number;
}

interface VsphereCluster {
    cluster: string;
    name: string;
}

interface VsphereData {
    vms: VsphereVm[];
    hosts: VsphereHost[];
    clusters: VsphereCluster[];
    datastores: VsphereDatastore[];
}

function formatBytes(bytes: number): string {
    const gb = bytes / 1024 ** 3;

    if (gb >= 1024) {
        return `${(gb / 1024).toFixed(1)} TB`;
    }

    return `${gb.toFixed(1)} GB`;
}

export default function Dashboard() {
    const [vsphere, setVsphere] = useState<VsphereData | null>(null);
    const [vsphereLoading, setVsphereLoading] = useState(true);
    const [vsphereError, setVsphereError] = useState<string | null>(null);
    const [vsphereUpdatedAt, setVsphereUpdatedAt] = useState<string | null>(
        null,
    );
    const [vmSearch, setVmSearch] = useState('');

    const fetchVsphereData = useCallback(async (): Promise<VsphereData> => {
        const responses = await Promise.all([
            fetch('/api/vsphere/vms'),
            fetch('/api/vsphere/hosts'),
            fetch('/api/vsphere/clusters'),
            fetch('/api/vsphere/datastores'),
        ]);

        if (responses.some((r) => !r.ok)) {
            throw new Error('vsphere request failed');
        }

        const [vmsRes, hostsRes, clustersRes, datastoresRes] =
            await Promise.all(responses.map((r) => r.json()));

        return {
            vms: vmsRes.data ?? [],
            hosts: hostsRes.data ?? [],
            clusters: clustersRes.data ?? [],
            datastores: datastoresRes.data ?? [],
        };
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const loadVsphere = useCallback(async () => {
        setVsphereLoading(true);
        setVsphereError(null);

        try {
            const data = await fetchVsphereData();
            setVsphere(data);
            setVsphereUpdatedAt(new Date().toLocaleTimeString());
        } catch {
            setVsphereError('ไม่สามารถโหลดข้อมูลจาก vCenter ได้');
        } finally {
            setVsphereLoading(false);
        }
    }, [fetchVsphereData]);

    useEffect(() => {
        let cancelled = false;

        fetchVsphereData()
            .then((data) => {
                if (cancelled) {
                    return;
                }

                setVsphere(data);
                setVsphereUpdatedAt(new Date().toLocaleTimeString());
            })
            .catch(() => {
                if (!cancelled) {
                    setVsphereError('ไม่สามารถโหลดข้อมูลจาก vCenter ได้');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setVsphereLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [fetchVsphereData]);

    const vspherePoweredOn = useMemo(
        () =>
            vsphere?.vms.filter((vm) => vm.power_state === 'POWERED_ON')
                .length ?? 0,
        [vsphere],
    );

    const vsphereStorage = useMemo(() => {
        const datastores = vsphere?.datastores ?? [];
        const capacity = datastores.reduce((sum, ds) => sum + ds.capacity, 0);
        const free = datastores.reduce((sum, ds) => sum + ds.free_space, 0);
        const used = capacity - free;
        const usedPct = capacity > 0 ? Math.round((used / capacity) * 100) : 0;

        return { capacity, free, used, usedPct };
    }, [vsphere]);

    const filteredVsphereVms = useMemo(() => {
        const vms = vsphere?.vms ?? [];

        if (!vmSearch.trim()) {
            return vms;
        }

        const query = vmSearch.trim().toLowerCase();

        return vms.filter((vm) => vm.name.toLowerCase().includes(query));
    }, [vsphere, vmSearch]);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold">
                                vCenter Live Overview
                            </h2>
                            {!vsphereLoading && !vsphereError && (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                                    Live
                                </span>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {vsphereUpdatedAt && (
                                <span className="text-xs text-muted-foreground">
                                    อัปเดตล่าสุด: {vsphereUpdatedAt}
                                </span>
                            )}
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={loadVsphere}
                                disabled={vsphereLoading}
                            >
                                <RefreshCw
                                    className={`h-4 w-4 ${vsphereLoading ? 'animate-spin' : ''}`}
                                />
                            </Button>
                        </div>
                    </div>

                    {vsphereError && (
                        <Card className="mb-4 border-l-4 border-l-red-500">
                            <CardContent className="flex items-center justify-between gap-4 pt-6">
                                <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    <p className="text-sm">{vsphereError}</p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={loadVsphere}
                                >
                                    ลองใหม่
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {vsphereLoading && !vsphere ? (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Card key={i}>
                                    <CardContent className="pt-6">
                                        <div className="h-6 w-16 animate-pulse rounded bg-muted" />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        vsphere && (
                            <>
                                <div className="mb-4 grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-5">
                                    <Card className="border-l-4 border-l-blue-500">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Total VMs
                                            </CardTitle>
                                            <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                                                <Monitor className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">
                                                {vsphere.vms.length}
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-l-4 border-l-green-500">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Powered On
                                            </CardTitle>
                                            <div className="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                                                <Power className="h-4 w-4 text-green-600 dark:text-green-400" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">
                                                {vspherePoweredOn}
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-l-4 border-l-slate-400">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Powered Off
                                            </CardTitle>
                                            <div className="rounded-lg bg-slate-100 p-2 dark:bg-slate-800">
                                                <PowerOff className="h-4 w-4 text-slate-600 dark:text-slate-400" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">
                                                {vsphere.vms.length -
                                                    vspherePoweredOn}
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-l-4 border-l-violet-500">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Hosts
                                            </CardTitle>
                                            <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                                                <Server className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">
                                                {vsphere.hosts.length}
                                            </div>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-l-4 border-l-amber-500">
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">
                                                Storage Used
                                            </CardTitle>
                                            <div className="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                                                <HardDrive className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">
                                                {vsphereStorage.usedPct}%
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {formatBytes(
                                                    vsphereStorage.used,
                                                )}{' '}
                                                /{' '}
                                                {formatBytes(
                                                    vsphereStorage.capacity,
                                                )}
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="mb-4 grid gap-4 lg:grid-cols-2">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                ESXi Hosts (
                                                {vsphere.hosts.length})
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {vsphere.hosts.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    ไม่มีข้อมูล Host
                                                </p>
                                            ) : (
                                                <div className="space-y-2">
                                                    {vsphere.hosts.map(
                                                        (host) => (
                                                            <div
                                                                key={
                                                                    host.host
                                                                }
                                                                className="flex items-center justify-between rounded-lg border p-3"
                                                            >
                                                                <div>
                                                                    <p className="text-sm font-medium">
                                                                        {
                                                                            host.name
                                                                        }
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {
                                                                            host.power_state
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <span
                                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                                        host.connection_state ===
                                                                        'CONNECTED'
                                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                                    }`}
                                                                >
                                                                    {
                                                                        host.connection_state
                                                                    }
                                                                </span>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Datastores (
                                                {vsphere.datastores.length})
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            {vsphere.datastores.length ===
                                            0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    ไม่มีข้อมูล Datastore
                                                </p>
                                            ) : (
                                                vsphere.datastores.map(
                                                    (ds) => {
                                                        const used =
                                                            ds.capacity -
                                                            ds.free_space;
                                                        const pct =
                                                            ds.capacity > 0
                                                                ? Math.round(
                                                                      (used /
                                                                          ds.capacity) *
                                                                          100,
                                                                  )
                                                                : 0;
                                                        const barColor =
                                                            pct >= 85
                                                                ? 'bg-red-500'
                                                                : pct >= 70
                                                                  ? 'bg-amber-500'
                                                                  : 'bg-green-500';

                                                        return (
                                                            <div
                                                                key={
                                                                    ds.datastore
                                                                }
                                                            >
                                                                <div className="mb-1 flex items-center justify-between text-xs">
                                                                    <span className="font-medium">
                                                                        {
                                                                            ds.name
                                                                        }{' '}
                                                                        <span className="text-muted-foreground">
                                                                            (
                                                                            {
                                                                                ds.type
                                                                            }

                                                                            )
                                                                        </span>
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        {formatBytes(
                                                                            used,
                                                                        )}{' '}
                                                                        /{' '}
                                                                        {formatBytes(
                                                                            ds.capacity,
                                                                        )}{' '}
                                                                        (
                                                                        {pct}
                                                                        %)
                                                                    </span>
                                                                </div>
                                                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                                    <div
                                                                        className={`h-full rounded-full ${barColor}`}
                                                                        style={{
                                                                            width: `${pct}%`,
                                                                        }}
                                                                    />
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )
                                            )}
                                        </CardContent>
                                    </Card>
                                </div>

                                <Card className="mb-4">
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                        <CardTitle>
                                            Virtual Machines (
                                            {filteredVsphereVms.length}/
                                            {vsphere.vms.length})
                                        </CardTitle>
                                        <Input
                                            placeholder="ค้นหา VM..."
                                            value={vmSearch}
                                            onChange={(e) =>
                                                setVmSearch(e.target.value)
                                            }
                                            className="w-48"
                                        />
                                    </CardHeader>
                                    <CardContent>
                                        {filteredVsphereVms.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                ไม่พบ VM ที่ตรงกับคำค้นหา
                                            </p>
                                        ) : (
                                            <div className="max-h-96 overflow-y-auto overflow-x-auto">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground uppercase backdrop-blur">
                                                        <tr>
                                                            <th className="px-4 py-2 font-medium">
                                                                Name
                                                            </th>
                                                            <th className="px-4 py-2 font-medium">
                                                                Power State
                                                            </th>
                                                            <th className="px-4 py-2 font-medium">
                                                                CPU
                                                            </th>
                                                            <th className="px-4 py-2 font-medium">
                                                                Memory
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y">
                                                        {filteredVsphereVms.map(
                                                            (vm) => (
                                                                <tr
                                                                    key={
                                                                        vm.vm
                                                                    }
                                                                    className="hover:bg-muted/30"
                                                                >
                                                                    <td className="px-4 py-3 font-medium">
                                                                        {
                                                                            vm.name
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <span
                                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                                vm.power_state ===
                                                                                'POWERED_ON'
                                                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                                            }`}
                                                                        >
                                                                            {
                                                                                vm.power_state
                                                                            }
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        {
                                                                            vm.cpu_count
                                                                        }{' '}
                                                                        Cores
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        {(
                                                                            vm.memory_size_MiB /
                                                                            1024
                                                                        ).toFixed(
                                                                            1,
                                                                        )}{' '}
                                                                        GB
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </>
                        )
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

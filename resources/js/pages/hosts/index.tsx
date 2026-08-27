import { Head } from '@inertiajs/react';
import { AlertCircle, Info, RefreshCw, Server } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface VsphereHost {
    host: string;
    name: string;
    connection_state: string;
    power_state: string;
}

interface VsphereVm {
    vm: string;
    name: string;
    power_state: string;
    host: string | null;
}

interface HostHardwareInfo {
    hypervisor: string | null;
    manufacturer: string | null;
    model: string | null;
    processor_type: string | null;
    cpu_cores: number | null;
    sockets: number | null;
    cores_per_socket: number | null;
    logical_processors: number | null;
    nics: number | null;
    memory_bytes: number | null;
}

function formatMemory(bytes: number | null): string {
    if (bytes === null) {
        return '-';
    }

    return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
}

function InfoRow({
    label,
    value,
}: {
    label: string;
    value: string | number | null;
}) {
    return (
        <div className="flex items-center justify-between border-b py-1.5 text-sm last:border-b-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value ?? '-'}</span>
        </div>
    );
}

export default function Index() {
    const [hosts, setHosts] = useState<VsphereHost[]>([]);
    const [vms, setVms] = useState<VsphereVm[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [infoHost, setInfoHost] = useState<VsphereHost | null>(null);
    const [hardwareInfo, setHardwareInfo] = useState<HostHardwareInfo | null>(
        null,
    );
    const [hardwareLoading, setHardwareLoading] = useState(false);
    const [hardwareError, setHardwareError] = useState<string | null>(null);

    const fetchHostsAndVms = useCallback(async () => {
        const [hostsRes, vmsRes] = await Promise.all([
            fetch('/api/vsphere/hosts'),
            fetch('/api/vsphere/vms'),
        ]);

        if (!hostsRes.ok || !vmsRes.ok) {
            throw new Error('vsphere request failed');
        }

        const [hostsJson, vmsJson] = await Promise.all([
            hostsRes.json(),
            vmsRes.json(),
        ]);

        return {
            hosts: hostsJson.data ?? [],
            vms: vmsJson.data ?? [],
        };
    }, []);

    // Used by the manual refresh button (a click handler, not an effect), so
    // setting state synchronously up front here is fine.
    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const { hosts, vms } = await fetchHostsAndVms();
            setHosts(hosts);
            setVms(vms);
        } catch {
            setError('ไม่สามารถโหลดข้อมูลจาก vCenter ได้');
        } finally {
            setLoading(false);
        }
    }, [fetchHostsAndVms]);

    useEffect(() => {
        let cancelled = false;

        fetchHostsAndVms()
            .then(({ hosts, vms }) => {
                if (cancelled) {
                    return;
                }

                setHosts(hosts);
                setVms(vms);
            })
            .catch(() => {
                if (!cancelled) {
                    setError('ไม่สามารถโหลดข้อมูลจาก vCenter ได้');
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
    }, [fetchHostsAndVms]);

    // Powered-on hosts first, so the ones actually serving workloads are
    // what you see without scrolling past disconnected/powered-off ones.
    const sortedHosts = useMemo(() => {
        return [...hosts].sort((a, b) => {
            const aOn = a.power_state === 'POWERED_ON' ? 0 : 1;
            const bOn = b.power_state === 'POWERED_ON' ? 0 : 1;

            return aOn - bOn;
        });
    }, [hosts]);

    const vmsByHost = useMemo(() => {
        const map = new Map<string, VsphereVm[]>();

        for (const vm of vms) {
            if (!vm.host) {
                continue;
            }

            if (!map.has(vm.host)) {
                map.set(vm.host, []);
            }

            map.get(vm.host)!.push(vm);
        }

        // Powered-on VMs first within each host, same reasoning as the
        // host cards themselves.
        for (const hostVms of map.values()) {
            hostVms.sort((a, b) => {
                const aOn = a.power_state === 'POWERED_ON' ? 0 : 1;
                const bOn = b.power_state === 'POWERED_ON' ? 0 : 1;

                return aOn - bOn;
            });
        }

        return map;
    }, [vms]);

    const openInfo = (host: VsphereHost) => {
        setInfoHost(host);
        setHardwareInfo(null);
        setHardwareError(null);
        setHardwareLoading(true);
    };

    // Fetches on open (rather than bulk-loading every host's hardware up
    // front) since it needs a separate SOAP call per host — same reasoning
    // as the existing per-host network-info endpoint. openInfo() above
    // (a click handler, not this effect) already set hardwareLoading true.
    useEffect(() => {
        if (!infoHost) {
            return;
        }

        let cancelled = false;

        fetch(`/api/vsphere/hosts/${infoHost.host}/hardware`)
            .then((res) => (res.ok ? res.json() : Promise.reject()))
            .then((json) => {
                if (!cancelled) {
                    setHardwareInfo(json.data ?? null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setHardwareError(
                        'ไม่สามารถโหลดข้อมูล Hardware ของ Host นี้ได้',
                    );
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setHardwareLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [infoHost]);

    const infoHostVmCount = infoHost
        ? (vmsByHost.get(infoHost.name)?.length ?? 0)
        : 0;

    return (
        <>
            <Head title="Manage Hosts" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">
                        Manage Hosts ({hosts.length})
                    </h1>
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

                {loading && hosts.length === 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Card key={i}>
                                <CardContent className="pt-6">
                                    <div className="h-24 animate-pulse rounded-lg bg-muted" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : hosts.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">
                                ไม่มีข้อมูล Host
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {sortedHosts.map((host) => {
                            const hostVms = vmsByHost.get(host.name) ?? [];

                            return (
                                <Card key={host.host}>
                                    <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                        <div className="flex items-center gap-2">
                                            <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                                                <Server className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                            </div>
                                            <div>
                                                <CardTitle className="flex items-center gap-1 text-base">
                                                    {host.name}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openInfo(host)
                                                        }
                                                        className="text-muted-foreground transition-colors hover:text-foreground"
                                                        title="View host information"
                                                    >
                                                        <Info className="h-3.5 w-3.5" />
                                                        <span className="sr-only">
                                                            View host
                                                            information
                                                        </span>
                                                    </button>
                                                </CardTitle>
                                                <p className="text-xs text-muted-foreground">
                                                    {host.power_state}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                                host.connection_state ===
                                                'CONNECTED'
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                            }`}
                                        >
                                            {host.connection_state}
                                        </span>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="mb-2 text-xs font-medium text-muted-foreground uppercase">
                                            Virtual Machines
                                            {hostVms.length > 0 &&
                                                ` (${hostVms.length})`}
                                        </p>
                                        {hostVms.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                ไม่มี VM
                                            </p>
                                        ) : (
                                            <div className="space-y-1">
                                                {hostVms.map((vm) => (
                                                    <div
                                                        key={vm.vm}
                                                        className="flex items-center justify-between rounded-md border px-3 py-1.5 text-sm"
                                                    >
                                                        <span>{vm.name}</span>
                                                        <span
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                vm.power_state ===
                                                                'POWERED_ON'
                                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                            }`}
                                                        >
                                                            {vm.power_state}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>

            <Dialog
                open={infoHost !== null}
                onOpenChange={(open) => !open && setInfoHost(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {infoHost?.name ?? 'Host Information'}
                        </DialogTitle>
                    </DialogHeader>

                    {hardwareLoading ? (
                        <div className="space-y-2">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <div
                                    key={i}
                                    className="h-6 animate-pulse rounded bg-muted"
                                />
                            ))}
                        </div>
                    ) : hardwareError ? (
                        <div className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            <p>{hardwareError}</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <h3 className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                    General
                                </h3>
                                <div>
                                    <InfoRow
                                        label="Hypervisor"
                                        value={hardwareInfo?.hypervisor ?? null}
                                    />
                                    <InfoRow
                                        label="Model"
                                        value={hardwareInfo?.model ?? null}
                                    />
                                    <InfoRow
                                        label="Processor Type"
                                        value={
                                            hardwareInfo?.processor_type ?? null
                                        }
                                    />
                                    <InfoRow
                                        label="Logical Processors"
                                        value={
                                            hardwareInfo?.logical_processors ??
                                            null
                                        }
                                    />
                                    <InfoRow
                                        label="NICs"
                                        value={hardwareInfo?.nics ?? null}
                                    />
                                    <InfoRow
                                        label="Virtual Machines"
                                        value={infoHostVmCount}
                                    />
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                    Hardware
                                </h3>
                                <div>
                                    <InfoRow
                                        label="Manufacturer"
                                        value={
                                            hardwareInfo?.manufacturer ?? null
                                        }
                                    />
                                    <InfoRow
                                        label="Model"
                                        value={hardwareInfo?.model ?? null}
                                    />
                                    <InfoRow
                                        label="CPU Cores"
                                        value={hardwareInfo?.cpu_cores ?? null}
                                    />
                                    <InfoRow
                                        label="Processor Type"
                                        value={
                                            hardwareInfo?.processor_type ?? null
                                        }
                                    />
                                    <InfoRow
                                        label="Sockets"
                                        value={hardwareInfo?.sockets ?? null}
                                    />
                                    <InfoRow
                                        label="Cores per Socket"
                                        value={
                                            hardwareInfo?.cores_per_socket ??
                                            null
                                        }
                                    />
                                    <InfoRow
                                        label="Logical Processors"
                                        value={
                                            hardwareInfo?.logical_processors ??
                                            null
                                        }
                                    />
                                    <InfoRow
                                        label="Memory"
                                        value={formatMemory(
                                            hardwareInfo?.memory_bytes ?? null,
                                        )}
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

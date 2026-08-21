import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { notifyError, notifySuccess } from '@/lib/swal';

interface VmRow {
    id: number;
    name: string;
    ip: string | null;
    dns: string | null;
    state: string | null;
    provisioned_space: string | null;
    used_space: string | null;
    memory_gb: number | null;
    cpu_cores: number | null;
    uptime_seconds: number | null;
    notes: string | null;
    is_active: boolean;
    host: { id: number; name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

function formatState(state: string | null): string {
    if (!state) {
        return 'Unknown';
    }

    return state
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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

function DetailField({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value}</p>
        </div>
    );
}

export default function Index({ vms }: { vms: Paginated<VmRow> }) {
    const [syncing, setSyncing] = useState(false);
    const [viewVm, setViewVm] = useState<VmRow | null>(null);

    const handleSync = () => {
        setSyncing(true);
        router.post(
            '/vms/sync',
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    notifySuccess('ซิงค์ข้อมูล VM จาก vCenter สำเร็จ', 'สำเร็จ'),
                onError: (formErrors) => {
                    const message =
                        Object.values(formErrors)[0] ??
                        'ไม่สามารถซิงค์ข้อมูลจาก vCenter ได้';
                    notifyError(message, 'ซิงค์ไม่สำเร็จ');
                },
                onFinish: () => setSyncing(false),
            },
        );
    };

    return (
        <>
            <Head title="Manage VMs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manage VMs</h1>
                    <Button onClick={handleSync} disabled={syncing}>
                        <RefreshCw
                            className={`mr-2 h-4 w-4 ${syncing ? 'animate-spin' : ''}`}
                        />
                        {syncing ? 'กำลังซิงค์...' : 'Sync from vCenter'}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Virtual Machines ({vms.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {vms.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No VMs found. Click "Sync from vCenter" to
                                get started.
                            </p>
                        ) : (
                            <table className="w-full table-fixed text-left text-sm">
                                <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Name
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            State
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Uptime
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Active
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Note
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {vms.data.map((vm) => (
                                        <tr
                                            key={vm.id}
                                            className="hover:bg-muted/30"
                                        >
                                            <td className="truncate px-3 py-2 font-medium">
                                                {vm.name}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${vm.state === 'POWERED_ON' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'}`}
                                                >
                                                    {formatState(vm.state)}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {formatUptime(
                                                    vm.uptime_seconds,
                                                )}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                <Badge
                                                    className={
                                                        vm.is_active
                                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                            : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                                                    }
                                                >
                                                    {vm.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td
                                                className="max-w-[200px] truncate px-3 py-2"
                                                title={vm.notes ?? undefined}
                                            >
                                                {vm.notes || '-'}
                                            </td>
                                            <td className="px-3 py-2 text-right whitespace-nowrap">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mr-2"
                                                    onClick={() =>
                                                        setViewVm(vm)
                                                    }
                                                >
                                                    View
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                    className="mr-2"
                                                >
                                                    <Link
                                                        href={`/vms/${vm.id}/edit`}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/vms/${vm.id}`}
                                                        method="delete"
                                                        as="button"
                                                        onClick={(e) => {
                                                            if (
                                                                !window.confirm(
                                                                    'Are you sure you want to delete this VM?',
                                                                )
                                                            ) {
                                                                e.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        Delete
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {vms.last_page > 1 && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    Showing {vms.from}–{vms.to} of{' '}
                                    {vms.total}
                                </p>
                                <div className="flex flex-wrap items-center gap-1">
                                    {vms.links.map((link, index) => {
                                        const isPrev =
                                            link.label.includes('Previous');
                                        const isNext =
                                            link.label.includes('Next');

                                        const content = isPrev ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : isNext ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            link.label
                                        );

                                        if (!link.url) {
                                            return (
                                                <span
                                                    key={index}
                                                    className="flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-muted-foreground opacity-50"
                                                >
                                                    {content}
                                                </span>
                                            );
                                        }

                                        return (
                                            <Button
                                                key={index}
                                                asChild
                                                size="sm"
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                className="h-8 min-w-8 px-2"
                                            >
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                >
                                                    {content}
                                                </Link>
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={viewVm !== null}
                onOpenChange={(open) => !open && setViewVm(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {viewVm?.name ?? 'VM Details'}
                        </DialogTitle>
                    </DialogHeader>
                    {viewVm && (
                        <div className="grid grid-cols-2 gap-4">
                            <DetailField
                                label="Host"
                                value={viewVm.host?.name || '-'}
                            />
                            <DetailField
                                label="IP Address"
                                value={viewVm.ip || '-'}
                            />
                            <DetailField
                                label="DNS"
                                value={viewVm.dns || '-'}
                            />
                            <DetailField
                                label="Provisioned Space"
                                value={viewVm.provisioned_space || '-'}
                            />
                            <DetailField
                                label="Used Space"
                                value={viewVm.used_space || '-'}
                            />
                            <DetailField
                                label="Memory (GB)"
                                value={
                                    viewVm.memory_gb !== null
                                        ? String(viewVm.memory_gb)
                                        : '-'
                                }
                            />
                            <DetailField
                                label="CPU Cores"
                                value={
                                    viewVm.cpu_cores !== null
                                        ? String(viewVm.cpu_cores)
                                        : '-'
                                }
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

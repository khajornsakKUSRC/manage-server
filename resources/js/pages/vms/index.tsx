import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Monitor,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyError, notifySuccess } from '@/lib/swal';

const ALL_STATES = '__all__';
const ALL_ACTIVE = '__all__';

const STATE_OPTIONS = [
    { value: 'POWERED_ON', label: 'Powered On' },
    { value: 'POWERED_OFF', label: 'Powered Off' },
    //{ value: 'SUSPENDED', label: 'Suspended' },
];

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
    certificate_exp: string | null;
    certificate_notify_days: number | null;
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

interface VmFilters {
    search: string;
    state: string;
    active: string;
}

interface VmCandidate {
    id: number;
    name: string;
    host: string | null;
    certificate_exp: string | null;
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

// Highlights certificates that are already expired or expiring soon, so
// they're noticeable while scanning the list rather than needing to open
// each VM to check. warningDays matches Settings → Monitoring Thresholds →
// Certificate Expiration Warning (days) — the same window that drives the
// Telegram notification, so the color here and the alert agree.
function certificateExpClass(
    certificateExp: string | null,
    warningDays: number,
): string {
    if (!certificateExp) {
        return 'text-muted-foreground';
    }

    const expiry = new Date(certificateExp);

    if (Number.isNaN(expiry.getTime())) {
        return '';
    }

    const daysUntil = Math.floor(
        (expiry.getTime() - Date.now()) / (24 * 60 * 60 * 1000),
    );

    if (daysUntil < 0) {
        return 'font-medium text-red-600 dark:text-red-400';
    }

    if (daysUntil <= warningDays) {
        return 'font-medium text-amber-600 dark:text-amber-400';
    }

    return '';
}

function DetailField({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value}</p>
        </div>
    );
}

export default function Index({
    vms,
    filters,
    certificateExpWarningDays,
}: {
    vms: Paginated<VmRow>;
    filters: VmFilters;
    certificateExpWarningDays: number;
}) {
    const [syncing, setSyncing] = useState(false);
    const [viewVm, setViewVm] = useState<VmRow | null>(null);
    const [search, setSearch] = useState(filters.search);

    const [importOpen, setImportOpen] = useState(false);
    const [importDate, setImportDate] = useState('');
    const [importCandidates, setImportCandidates] = useState<
        VmCandidate[] | null
    >(null);
    const [importLoading, setImportLoading] = useState(false);
    const [importError, setImportError] = useState<string | null>(null);
    const [importSearch, setImportSearch] = useState('');
    const [importSelected, setImportSelected] = useState<Set<number>>(
        new Set(),
    );
    const [importSaving, setImportSaving] = useState(false);

    const applyFilters = (next: Partial<VmFilters>) => {
        router.get(
            '/vms',
            {
                search: next.search ?? filters.search,
                state: next.state ?? filters.state,
                active: next.active ?? filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        // Guards against re-firing (and dropping the current page's `page`
        // query param — see applyFilters) whenever this effect merely gets
        // re-invoked without the user actually having typed anything new,
        // e.g. a pagination Link navigation re-rendering this component.
        // A one-shot "is this the first run" ref isn't reliable for that —
        // only a real value comparison against the URL's own search is.
        if (search === filters.search) {
            return;
        }

        const timeout = setTimeout(() => {
            applyFilters({ search });
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, filters.search]);

    const hasActiveFilters =
        filters.search !== '' || filters.state !== '' || filters.active !== '';

    const handleSync = () => {
        setSyncing(true);
        router.post(
            '/vms/sync',
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    notifySuccess(
                        'ซิงค์ข้อมูล VM จาก vCenter สำเร็จ',
                        'สำเร็จ',
                    ),
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

    // Fetches every VM matching the page's current search/state/active
    // filters (unpaginated) — called from the "Import Certificate Exp"
    // button's click handler rather than an effect, so it can select from
    // the whole filtered set, not just the visible page of 20.
    const loadImportCandidates = useCallback(() => {
        setImportLoading(true);
        setImportError(null);

        const params = new URLSearchParams({
            search: filters.search,
            state: filters.state,
            active: filters.active,
        });

        fetch(`/api/vms/certificate-candidates?${params.toString()}`)
            .then((res) => (res.ok ? res.json() : Promise.reject()))
            .then((json) => setImportCandidates(json.data ?? []))
            .catch(() => setImportError('ไม่สามารถโหลดรายการ VM ได้'))
            .finally(() => setImportLoading(false));
    }, [filters.search, filters.state, filters.active]);

    const openImportModal = () => {
        setImportOpen(true);
        setImportDate('');
        setImportSearch('');
        setImportSelected(new Set());
        setImportCandidates(null);
        loadImportCandidates();
    };

    const toggleImportSelected = (id: number) => {
        setImportSelected((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const filteredImportCandidates = (importCandidates ?? []).filter((vm) => {
        if (importSearch === '') {
            return true;
        }

        const q = importSearch.toLowerCase();

        return (
            vm.name.toLowerCase().includes(q) ||
            (vm.host ?? '').toLowerCase().includes(q)
        );
    });

    const allFilteredSelected =
        filteredImportCandidates.length > 0 &&
        filteredImportCandidates.every((vm) => importSelected.has(vm.id));

    const toggleSelectAllFiltered = () => {
        setImportSelected((current) => {
            const next = new Set(current);

            filteredImportCandidates.forEach((vm) => {
                if (allFilteredSelected) {
                    next.delete(vm.id);
                } else {
                    next.add(vm.id);
                }
            });

            return next;
        });
    };

    const handleImportSave = () => {
        setImportSaving(true);

        router.post(
            '/vms/certificate-exp',
            {
                certificate_exp: importDate,
                vm_ids: Array.from(importSelected),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    notifySuccess(
                        `ตั้งค่า Certificate Exp ให้ ${importSelected.size} VM สำเร็จ`,
                        'สำเร็จ',
                    );
                    setImportOpen(false);
                },
                onError: (formErrors) => {
                    const message =
                        Object.values(formErrors)[0] ??
                        'ไม่สามารถบันทึกข้อมูลได้';
                    notifyError(message, 'บันทึกไม่สำเร็จ');
                },
                onFinish: () => setImportSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Manage VMs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manage VMs</h1>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={openImportModal}>
                            <CalendarClock className="mr-2 h-4 w-4" />
                            Import Certificate Exp
                        </Button>
                        <Button onClick={handleSync} disabled={syncing}>
                            <RefreshCw
                                className={`mr-2 h-4 w-4 ${syncing ? 'animate-spin' : ''}`}
                            />
                            {syncing ? 'กำลังซิงค์...' : 'Sync from vCenter'}
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <Monitor className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <CardTitle>
                            All Virtual Machines ({vms.total})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <div className="relative min-w-[220px] flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name, IP, or host..."
                                    className="pl-8"
                                />
                            </div>

                            <Select
                                value={filters.state || ALL_STATES}
                                onValueChange={(value) =>
                                    applyFilters({
                                        state:
                                            value === ALL_STATES ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-[160px]">
                                    <SelectValue placeholder="State" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATES}>
                                        All States
                                    </SelectItem>
                                    {STATE_OPTIONS.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.active || ALL_ACTIVE}
                                onValueChange={(value) =>
                                    applyFilters({
                                        active:
                                            value === ALL_ACTIVE ? '' : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-[140px]">
                                    <SelectValue placeholder="Active" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_ACTIVE}>
                                        All
                                    </SelectItem>
                                    <SelectItem value="1">Active</SelectItem>
                                    <SelectItem value="0">Inactive</SelectItem>
                                </SelectContent>
                            </Select>

                            {hasActiveFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setSearch('');
                                        applyFilters({
                                            search: '',
                                            state: '',
                                            active: '',
                                        });
                                    }}
                                >
                                    Clear
                                </Button>
                            )}
                        </div>

                        {vms.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {hasActiveFilters
                                    ? 'No VMs match your search or filters.'
                                    : 'No VMs found. Click "Sync from vCenter" to get started.'}
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
                                            Certificate Exp
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
                                                className={`px-3 py-2 whitespace-nowrap ${certificateExpClass(vm.certificate_exp, vm.certificate_notify_days ?? certificateExpWarningDays)}`}
                                                title={
                                                    vm.certificate_notify_days !==
                                                    null
                                                        ? `Custom reminder: ${vm.certificate_notify_days} day(s) before expiration`
                                                        : undefined
                                                }
                                            >
                                                {vm.certificate_exp || '-'}
                                                {vm.certificate_notify_days !==
                                                    null && (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        (
                                                        {
                                                            vm.certificate_notify_days
                                                        }
                                                        d)
                                                    </span>
                                                )}
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
                                    Showing {vms.from}–{vms.to} of {vms.total}
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

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent className="max-w-[calc(100%-2rem)] sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Import Certificate Exp</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="import-cert-date">
                                Certificate Exp Date
                            </Label>
                            <Input
                                id="import-cert-date"
                                type="date"
                                className="max-w-xs"
                                value={importDate}
                                onChange={(e) => setImportDate(e.target.value)}
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <Label>
                                    เลือก VM ({importSelected.size} selected)
                                </Label>
                                <Input
                                    value={importSearch}
                                    onChange={(e) =>
                                        setImportSearch(e.target.value)
                                    }
                                    placeholder="Search VM or host..."
                                    className="max-w-[220px]"
                                />
                            </div>

                            <div className="max-h-72 overflow-y-auto rounded-md border">
                                {importLoading ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        กำลังโหลด...
                                    </p>
                                ) : importError ? (
                                    <p className="p-4 text-sm text-red-500">
                                        {importError}
                                    </p>
                                ) : filteredImportCandidates.length === 0 ? (
                                    <p className="p-4 text-sm text-muted-foreground">
                                        No VMs match.
                                    </p>
                                ) : (
                                    <table className="w-full text-left text-sm">
                                        <thead className="sticky top-0 bg-muted/80 text-xs text-muted-foreground uppercase backdrop-blur">
                                            <tr>
                                                <th className="w-10 px-3 py-2">
                                                    <Checkbox
                                                        checked={
                                                            allFilteredSelected
                                                        }
                                                        onCheckedChange={
                                                            toggleSelectAllFiltered
                                                        }
                                                    />
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Name
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Host
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Current Cert Exp
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {filteredImportCandidates.map(
                                                (vm) => (
                                                    <tr
                                                        key={vm.id}
                                                        className="cursor-pointer hover:bg-muted/30"
                                                        onClick={() =>
                                                            toggleImportSelected(
                                                                vm.id,
                                                            )
                                                        }
                                                    >
                                                        <td
                                                            className="px-3 py-2"
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                        >
                                                            <Checkbox
                                                                checked={importSelected.has(
                                                                    vm.id,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    toggleImportSelected(
                                                                        vm.id,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 font-medium">
                                                            {vm.name}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {vm.host || '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {vm.certificate_exp ||
                                                                '-'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setImportOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleImportSave}
                            disabled={
                                importSaving ||
                                !importDate ||
                                importSelected.size === 0
                            }
                        >
                            {importSaving
                                ? 'กำลังบันทึก...'
                                : `Save (${importSelected.size})`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

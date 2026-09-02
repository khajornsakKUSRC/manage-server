import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ChevronDown,
    ChevronRight,
    Loader2,
    Pencil,
    Plus,
    RefreshCw,
    ServerCog,
    Trash2,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ServiceRow {
    id: number;
    label: string;
    host: string;
    service_name: string;
    // Null until the first check (scheduled services:check or a manual
    // Refresh) has run for this service.
    status: string | null;
    healthy: boolean | null;
    detail: string | null;
    raw: string | null;
    checked_at: string | null;
}

interface Props {
    services: ServiceRow[];
}

interface FormState {
    label: string;
    host: string;
    service_name: string;
}

const EMPTY_FORM: FormState = { label: '', host: '', service_name: '' };

const STATUS_STYLES: Record<string, string> = {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'not-found': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    unreachable:
        'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    inactive: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
    activating:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    deactivating:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
};

function statusClass(status: string | null): string {
    return (
        (status ? STATUS_STYLES[status] : undefined) ??
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
    );
}

function formatTime(time: string | null): string {
    return time ? new Date(time).toLocaleString() : 'ยังไม่เคยตรวจสอบ';
}

export default function Index({ services }: Props) {
    // Seeded from the server-rendered prop, which carries the last-known
    // status persisted by services:check — no SSH happens on page load.
    // "Refresh" is the only thing here that triggers a live check.
    const [statuses, setStatuses] = useState<ServiceRow[]>(services);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<FormState>(EMPTY_FORM);

    // Follow prop changes from Inertia visits (add/edit/delete redirect
    // back to services.index with a fresh, still-SSH-free list) without an
    // effect — the React-docs pattern for resetting state on prop change.
    const [syncedServices, setSyncedServices] = useState(services);

    if (services !== syncedServices) {
        setSyncedServices(services);
        setStatuses(services);
    }

    // Live check: one SSH round-trip per host on the server, result
    // persisted so it also updates the cached view. Only ever called from
    // the Refresh button.
    const reload = useCallback(async () => {
        setRefreshing(true);
        setError(null);

        try {
            const res = await fetch('/api/services/statuses?refresh=1');

            if (!res.ok) {
                throw new Error('services status request failed');
            }

            const json = await res.json();
            setStatuses(json.data ?? []);
        } catch {
            setError('ไม่สามารถตรวจสอบสถานะ service ได้');
        } finally {
            setRefreshing(false);
        }
    }, []);

    const openCreate = () => {
        setEditingId(null);
        reset();
        clearErrors();
        setData(EMPTY_FORM);
        setDialogOpen(true);
    };

    const openEdit = (service: ServiceRow) => {
        setEditingId(service.id);
        clearErrors();
        setData({
            label: service.label,
            host: service.host,
            service_name: service.service_name,
        });
        setDialogOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            // No reload() here — the redirect back to services.index
            // re-renders this page with the updated (cached, SSH-free)
            // list. Live status is a manual Refresh away.
            onSuccess: () => setDialogOpen(false),
        };

        if (editingId) {
            put(`/services/${editingId}`, options);
        } else {
            post('/services', options);
        }
    };

    const handleDelete = (service: ServiceRow) => {
        if (window.confirm(`ต้องการลบ "${service.label}" ออกจากรายการที่ตรวจสอบใช่หรือไม่?`)) {
            router.delete(`/services/${service.id}`, {
                preserveScroll: true,
            });
        }
    };

    const toggleExpanded = (id: number) => {
        setExpanded((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const downCount = statuses.filter((s) => s.healthy === false).length;
    const checkedCount = statuses.filter((s) => s.status !== null).length;

    return (
        <>
            <Head title="Services" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Services</h1>
                        <p className="text-sm text-muted-foreground">
                            Last recorded systemd status from the scheduled
                            check. Hit Refresh to run a live check over SSH
                            now.
                            {checkedCount > 0 &&
                                (downCount > 0
                                    ? ` ${downCount} service(s) currently down.`
                                    : ' All checked services are active.')}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={reload}
                            disabled={refreshing}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`}
                            />
                            Refresh
                        </Button>
                        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                            <DialogTrigger asChild>
                                <Button size="sm" onClick={openCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Service
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={submit}>
                                    <DialogHeader>
                                        <DialogTitle>
                                            {editingId
                                                ? 'Edit Service'
                                                : 'Add Service'}
                                        </DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="service-label">
                                                Label
                                            </Label>
                                            <Input
                                                id="service-label"
                                                placeholder="e.g. KUWIN Radius"
                                                value={data.label}
                                                onChange={(e) =>
                                                    setData(
                                                        'label',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errors.label && (
                                                <p className="text-sm text-red-500">
                                                    {errors.label}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="service-host">
                                                Host / IP
                                            </Label>
                                            <Input
                                                id="service-host"
                                                placeholder="e.g. 158.108.96.18"
                                                value={data.host}
                                                onChange={(e) =>
                                                    setData(
                                                        'host',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errors.host && (
                                                <p className="text-sm text-red-500">
                                                    {errors.host}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="service-name">
                                                systemd service name
                                            </Label>
                                            <Input
                                                id="service-name"
                                                placeholder="e.g. radiusd"
                                                value={data.service_name}
                                                onChange={(e) =>
                                                    setData(
                                                        'service_name',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {errors.service_name && (
                                                <p className="text-sm text-red-500">
                                                    {errors.service_name}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Runs{' '}
                                                <code className="rounded bg-muted px-1 py-0.5">
                                                    systemctl status{' '}
                                                    {data.service_name ||
                                                        '<name>'}
                                                </code>{' '}
                                                over SSH using the same
                                                credentials as Smart
                                                Detection/ModSecurity.
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {editingId
                                                ? 'Save Changes'
                                                : 'Add Service'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
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
                                onClick={reload}
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {services.length === 0 ? (
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6 text-sm text-muted-foreground">
                            <ServerCog className="h-5 w-5" />
                            ยังไม่มี service ที่ตรวจสอบ กด "Add Service"
                            เพื่อเริ่มต้น
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {services.map((service) => {
                            const status = statuses?.find(
                                (s) => s.id === service.id,
                            );
                            const isExpanded = expanded[service.id] ?? false;

                            return (
                                <Card key={service.id}>
                                    <CardContent className="pt-6">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleExpanded(
                                                            service.id,
                                                        )
                                                    }
                                                    disabled={!status?.raw}
                                                    className="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                                >
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-4 w-4" />
                                                    ) : (
                                                        <ChevronRight className="h-4 w-4" />
                                                    )}
                                                </button>
                                                <div>
                                                    <p className="font-medium">
                                                        {service.label}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted-foreground">
                                                        {service.service_name}
                                                        @{service.host}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                {refreshing && !status ? (
                                                    <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                                ) : status ? (
                                                    <>
                                                        <div className="text-right">
                                                            <Badge
                                                                className={statusClass(
                                                                    status.status,
                                                                )}
                                                            >
                                                                {status.status
                                                                    ? status.status.toUpperCase()
                                                                    : 'NOT CHECKED'}
                                                            </Badge>
                                                            {status.detail && (
                                                                <p
                                                                    className="mt-1 max-w-xs truncate text-xs text-muted-foreground"
                                                                    title={
                                                                        status.detail
                                                                    }
                                                                >
                                                                    {
                                                                        status.detail
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                            {formatTime(
                                                                status.checked_at,
                                                            )}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        -
                                                    </span>
                                                )}
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        openEdit(service)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        handleDelete(service)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                </Button>
                                            </div>
                                        </div>
                                        {isExpanded && status?.raw && (
                                            <pre className="mt-4 max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">
                                                {status.raw}
                                            </pre>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

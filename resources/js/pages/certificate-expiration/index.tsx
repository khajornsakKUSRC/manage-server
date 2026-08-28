import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Pencil,
    Send,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type CertStatus = 'expired' | 'warning' | 'ok' | 'unknown';

interface CertificateVm {
    id: number;
    name: string;
    host: string | null;
    is_active: boolean;
    certificate_exp: string;
    days_until: number | null;
    warning_days: number;
    is_custom_warning_days: boolean;
    status: CertStatus;
}

function statusBadgeClass(status: CertStatus): string {
    switch (status) {
        case 'expired':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'warning':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'ok':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        default:
            return 'bg-gray-100 text-gray-600 dark:bg-gray-800/60 dark:text-gray-400';
    }
}

function statusLabel(status: CertStatus): string {
    switch (status) {
        case 'expired':
            return 'Expired';
        case 'warning':
            return 'Expiring Soon';
        case 'ok':
            return 'OK';
        default:
            return 'Unknown';
    }
}

function daysUntilLabel(daysUntil: number | null): string {
    if (daysUntil === null) {
        return '-';
    }

    if (daysUntil < 0) {
        return `Expired ${Math.abs(daysUntil)}d ago`;
    }

    if (daysUntil === 0) {
        return 'Expires today';
    }

    return `${daysUntil}d left`;
}

export default function Index({
    vms,
    defaultWarningDays,
}: {
    vms: CertificateVm[];
    defaultWarningDays: number;
}) {
    const counts = useMemo(
        () => ({
            expired: vms.filter((v) => v.status === 'expired').length,
            warning: vms.filter((v) => v.status === 'warning').length,
            ok: vms.filter((v) => v.status === 'ok').length,
        }),
        [vms],
    );

    return (
        <>
            <Head title="Certificate Expiration" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Certificate Expiration ({vms.length})
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Telegram alerts by default {defaultWarningDays} days
                            before a certificate expires (configurable per VM).
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/system-settings">
                            <Send className="h-4 w-4" />
                            Settings → Telegram Notifications
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <div className="rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                                <ShieldAlert className="h-4 w-4 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {counts.expired}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Expired
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <div className="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {counts.warning}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Expiring Soon
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6">
                            <div className="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                                <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {counts.ok}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    OK
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                            <ShieldCheck className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <CardTitle>VM Certificates</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {vms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No VMs have a Certificate Exp date set yet. Set
                                one from a VM's Edit page.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                VM
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Host
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Expires
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Time Left
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Reminder
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {vms.map((vm) => (
                                            <tr
                                                key={vm.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        className={statusBadgeClass(
                                                            vm.status,
                                                        )}
                                                    >
                                                        {statusLabel(vm.status)}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {vm.name}
                                                    {!vm.is_active && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            (inactive)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {vm.host ?? '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {vm.certificate_exp}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {daysUntilLabel(
                                                        vm.days_until,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {vm.warning_days} days
                                                    {vm.is_custom_warning_days && (
                                                        <span className="ml-1 text-xs">
                                                            (custom)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/vms/${vm.id}/edit`}
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

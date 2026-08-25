import { Head, Link, useForm } from '@inertiajs/react';
import { Monitor, PenLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface VmDetail {
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
    is_active: boolean;
    host: { id: number; name: string } | null;
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

function ReadOnlyField({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm font-medium">{value}</p>
        </div>
    );
}

export default function Edit({ vm }: { vm: VmDetail }) {
    const { data, setData, put, processing, errors } = useForm({
        notes: vm.notes || '',
        certificate_exp: vm.certificate_exp || '',
        is_active: vm.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/vms/${vm.id}`);
    };

    return (
        <>
            <Head title={`Edit VM: ${vm.name}`} />
            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Edit Virtual Machine</h1>
                    <Button variant="outline" asChild>
                        <Link href="/vms">Back to VMs</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <Monitor className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <CardTitle>ข้อมูลจากระบบ (vCenter)</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <ReadOnlyField label="Name" value={vm.name} />
                        <ReadOnlyField
                            label="Host"
                            value={vm.host?.name || '-'}
                        />
                        <ReadOnlyField
                            label="IP Address"
                            value={vm.ip || '-'}
                        />
                        <ReadOnlyField label="DNS" value={vm.dns || '-'} />
                        <ReadOnlyField
                            label="State"
                            value={formatState(vm.state)}
                        />
                        <ReadOnlyField
                            label="Uptime"
                            value={formatUptime(vm.uptime_seconds)}
                        />
                        <ReadOnlyField
                            label="Provisioned Space"
                            value={vm.provisioned_space || '-'}
                        />
                        <ReadOnlyField
                            label="Used Space"
                            value={vm.used_space || '-'}
                        />
                        <ReadOnlyField
                            label="Memory (GB)"
                            value={
                                vm.memory_gb !== null
                                    ? String(vm.memory_gb)
                                    : '-'
                            }
                        />
                        <ReadOnlyField
                            label="CPU Cores"
                            value={
                                vm.cpu_cores !== null
                                    ? String(vm.cpu_cores)
                                    : '-'
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                            <div className="rounded-lg bg-slate-100 p-2 dark:bg-slate-800">
                                <PenLine className="h-4 w-4 text-slate-600 dark:text-slate-400" />
                            </div>
                            <CardTitle>ข้อมูลที่กรอกด้วยตนเอง</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="certificate_exp">
                                    Certificate Exp
                                </Label>
                                <Input
                                    id="certificate_exp"
                                    type="date"
                                    value={data.certificate_exp}
                                    onChange={(e) =>
                                        setData(
                                            'certificate_exp',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.certificate_exp && (
                                    <p className="text-sm text-red-500">
                                        {errors.certificate_exp}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2 rounded-lg border p-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="font-normal"
                                >
                                    Active{' '}
                                    <span className="text-xs text-muted-foreground">
                                        (inactive VMs are excluded from the
                                        daily report's Availability count)
                                    </span>
                                </Label>
                            </div>

                            <div className="col-span-1 space-y-2 md:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <textarea
                                    id="notes"
                                    className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-500">
                                        {errors.notes}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                Update VM
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Edit({ vm, hosts }: { vm: any; hosts: any[] }) {
    const { data, setData, put, processing, errors } = useForm({
        host_id: vm.host_id || '',
        name: vm.name || '',
        ip: vm.ip || '',
        dns: vm.dns || '',
        state: vm.state || 'running',
        provisioned_space: vm.provisioned_space || '',
        used_space: vm.used_space || '',
        memory_gb: vm.memory_gb || '',
        cpu_cores: vm.cpu_cores || '',
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
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>VM Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="col-span-1 space-y-2 md:col-span-2">
                                <Label htmlFor="host_id">
                                    Parent Host{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <select
                                    id="host_id"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.host_id}
                                    onChange={(e) =>
                                        setData('host_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="" disabled>
                                        Select a Host...
                                    </option>
                                    {hosts.map((host) => (
                                        <option key={host.id} value={host.id}>
                                            {host.name} ({host.ip})
                                        </option>
                                    ))}
                                </select>
                                {errors.host_id && (
                                    <p className="text-sm text-red-500">
                                        {errors.host_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    VM Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="ip">IP Address</Label>
                                <Input
                                    id="ip"
                                    value={data.ip}
                                    onChange={(e) =>
                                        setData('ip', e.target.value)
                                    }
                                />
                                {errors.ip && (
                                    <p className="text-sm text-red-500">
                                        {errors.ip}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="dns">DNS</Label>
                                <Input
                                    id="dns"
                                    value={data.dns}
                                    onChange={(e) =>
                                        setData('dns', e.target.value)
                                    }
                                />
                                {errors.dns && (
                                    <p className="text-sm text-red-500">
                                        {errors.dns}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="state">State</Label>
                                <select
                                    id="state"
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.state}
                                    onChange={(e) =>
                                        setData('state', e.target.value)
                                    }
                                >
                                    <option value="running">Running</option>
                                    <option value="stopped">Stopped</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                                {errors.state && (
                                    <p className="text-sm text-red-500">
                                        {errors.state}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="memory_gb">Memory (GB)</Label>
                                <Input
                                    id="memory_gb"
                                    type="number"
                                    min="1"
                                    value={data.memory_gb}
                                    onChange={(e) =>
                                        setData('memory_gb', e.target.value)
                                    }
                                />
                                {errors.memory_gb && (
                                    <p className="text-sm text-red-500">
                                        {errors.memory_gb}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cpu_cores">CPU Cores</Label>
                                <Input
                                    id="cpu_cores"
                                    type="number"
                                    min="1"
                                    value={data.cpu_cores}
                                    onChange={(e) =>
                                        setData('cpu_cores', e.target.value)
                                    }
                                />
                                {errors.cpu_cores && (
                                    <p className="text-sm text-red-500">
                                        {errors.cpu_cores}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="provisioned_space">
                                    Provisioned Space
                                </Label>
                                <Input
                                    id="provisioned_space"
                                    placeholder="e.g. 100 GB"
                                    value={data.provisioned_space}
                                    onChange={(e) =>
                                        setData(
                                            'provisioned_space',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.provisioned_space && (
                                    <p className="text-sm text-red-500">
                                        {errors.provisioned_space}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="used_space">Used Space</Label>
                                <Input
                                    id="used_space"
                                    placeholder="e.g. 45 GB"
                                    value={data.used_space}
                                    onChange={(e) =>
                                        setData('used_space', e.target.value)
                                    }
                                />
                                {errors.used_space && (
                                    <p className="text-sm text-red-500">
                                        {errors.used_space}
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

import { Head, Link, useForm } from '@inertiajs/react';
import { Network } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface MonitorRecord {
    id: number;
    name: string;
    category: string;
    type: string;
    target: string;
    port: number | null;
    interval_seconds: number;
    timeout_ms: number;
    is_active: boolean;
}

export default function Edit({
    monitor,
    categories,
    types,
}: {
    monitor: MonitorRecord;
    categories: Record<string, string>;
    types: Record<string, string>;
}) {
    const { data, setData, put, transform, processing, errors } = useForm({
        name: monitor.name,
        category: monitor.category,
        type: monitor.type,
        target: monitor.target,
        port: monitor.port !== null ? String(monitor.port) : '',
        interval_seconds: String(monitor.interval_seconds),
        timeout_ms: String(monitor.timeout_ms),
        is_active: monitor.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // The port field is only shown (and only meaningful) for TCP
        // checks — an empty string would otherwise fail the backend's
        // `integer` rule for every other type.
        transform((formData) => ({
            ...formData,
            port: formData.port === '' ? null : formData.port,
        }));

        put(`/network-monitors/${monitor.id}`);
    };

    return (
        <>
            <Head title="Edit Network Monitor" />
            <div className="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">
                        Edit Monitor: {monitor.name}
                    </h1>
                    <Button variant="outline" asChild>
                        <Link href="/network-monitors">
                            Back to Network Infrastructure
                        </Link>
                    </Button>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                            <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                                <Network className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            </div>
                            <CardTitle>Monitor Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Name <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Main WAN Link"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="category">
                                        Category{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.category}
                                        onValueChange={(value) =>
                                            setData('category', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="category"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(categories).map(
                                                ([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {errors.category && (
                                        <p className="text-sm text-red-500">
                                            {errors.category}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="type">
                                        Check Type{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value) =>
                                            setData('type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="type"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(types).map(
                                                ([key, label]) => (
                                                    <SelectItem
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {errors.type && (
                                        <p className="text-sm text-red-500">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="target">
                                    Target{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="target"
                                    value={data.target}
                                    onChange={(e) =>
                                        setData('target', e.target.value)
                                    }
                                    placeholder={
                                        data.type === 'http'
                                            ? 'e.g. https://intranet.local/health'
                                            : 'e.g. 10.0.0.1 or gateway.local'
                                    }
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    {data.type === 'http'
                                        ? 'A full URL checked over HTTP(S)'
                                        : data.type === 'dns'
                                          ? 'A hostname to resolve'
                                          : 'An IP address or hostname'}
                                </p>
                                {errors.target && (
                                    <p className="text-sm text-red-500">
                                        {errors.target}
                                    </p>
                                )}
                            </div>

                            {data.type === 'tcp' && (
                                <div className="space-y-2">
                                    <Label htmlFor="port">
                                        Port{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        value={data.port}
                                        onChange={(e) =>
                                            setData('port', e.target.value)
                                        }
                                        placeholder="e.g. 443"
                                    />
                                    {errors.port && (
                                        <p className="text-sm text-red-500">
                                            {errors.port}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="interval_seconds">
                                        Check Interval (seconds)
                                    </Label>
                                    <Input
                                        id="interval_seconds"
                                        type="number"
                                        min={60}
                                        max={86400}
                                        value={data.interval_seconds}
                                        onChange={(e) =>
                                            setData(
                                                'interval_seconds',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.interval_seconds && (
                                        <p className="text-sm text-red-500">
                                            {errors.interval_seconds}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="timeout_ms">
                                        Timeout (ms)
                                    </Label>
                                    <Input
                                        id="timeout_ms"
                                        type="number"
                                        min={500}
                                        max={30000}
                                        value={data.timeout_ms}
                                        onChange={(e) =>
                                            setData(
                                                'timeout_ms',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.timeout_ms && (
                                        <p className="text-sm text-red-500">
                                            {errors.timeout_ms}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
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
                                    Actively monitor this item
                                </Label>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                Update Monitor
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

import { Head, Link, useForm } from '@inertiajs/react';
import { Server } from 'lucide-react';
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

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        ip: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hosts');
    };

    return (
        <>
            <Head title="Create Host" />
            <div className="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Create New Host</h1>
                    <Button variant="outline" asChild>
                        <Link href="/hosts">Back to Hosts</Link>
                    </Button>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                            <div className="rounded-lg bg-violet-100 p-2 dark:bg-violet-900/30">
                                <Server className="h-4 w-4 text-violet-600 dark:text-violet-400" />
                            </div>
                            <CardTitle>Host Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Host Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g. ESXi-Node-03"
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
                                    placeholder="e.g. 192.168.1.100"
                                />
                                {errors.ip && (
                                    <p className="text-sm text-red-500">
                                        {errors.ip}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                Save Host
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

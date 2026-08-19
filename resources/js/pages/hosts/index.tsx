import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Index({ hosts }: { hosts: any[] }) {
    return (
        <>
            <Head title="Manage Hosts" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manage Hosts</h1>
                    <Button asChild>
                        <Link href="/hosts/create">Add New Host</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Hosts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {hosts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hosts found. Add one to get started.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Name
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                IP Address
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                VMs
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {hosts.map((host) => (
                                            <tr
                                                key={host.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {host.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {host.ip || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                        {host.vms_count || 0}{' '}
                                                        VMs
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        className="mr-2"
                                                    >
                                                        <Link
                                                            href={`/hosts/${host.id}/edit`}
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
                                                            href={`/hosts/${host.id}`}
                                                            method="delete"
                                                            as="button"
                                                            onClick={(e) => {
                                                                if (
                                                                    !window.confirm(
                                                                        'Are you sure you want to delete this host? All associated VMs may also be affected.',
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
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

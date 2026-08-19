import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Index({ vms }: { vms: any[] }) {
    return (
        <>
            <Head title="Manage VMs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manage VMs</h1>
                    <Button asChild>
                        <Link href="/vms/create">Add New VM</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Virtual Machines</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {vms.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No VMs found. Add one to get started.
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
                                                Host
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                IP Address
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                State
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
                                                <td className="px-4 py-3 font-medium">
                                                    {vm.name}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {vm.host?.name || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {vm.ip || '-'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${vm.state === 'running' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'}`}
                                                    >
                                                        {vm.state || 'Unknown'}
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
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

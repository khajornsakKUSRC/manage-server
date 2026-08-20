import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    permissions: string[] | null;
    created_at: string;
}

export default function Index({
    users,
    pages,
    currentUserId,
}: {
    users: UserRow[];
    pages: Record<string, string>;
    currentUserId: number;
}) {
    return (
        <>
            <Head title="Manage Users" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manage Users</h1>
                    <Button asChild>
                        <Link href="/users/create">Add User</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Users ({users.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {users.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No users found.
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
                                                Email
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Access
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {users.map((user) => (
                                            <tr
                                                key={user.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {user.name}
                                                    {user.id ===
                                                        currentUserId && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            (you)
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.email}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.is_admin ? (
                                                        <span className="inline-flex items-center rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-800 dark:bg-violet-900/30 dark:text-violet-300">
                                                            Administrator
                                                        </span>
                                                    ) : (user.permissions ?? [])
                                                          .length === 0 ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            No access
                                                        </span>
                                                    ) : (
                                                        <div className="flex flex-wrap gap-1">
                                                            {(
                                                                user.permissions ??
                                                                []
                                                            ).map((key) => (
                                                                <span
                                                                    key={key}
                                                                    className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                                                                >
                                                                    {pages[
                                                                        key
                                                                    ] ?? key}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        className="mr-2"
                                                    >
                                                        <Link
                                                            href={`/users/${user.id}/edit`}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={
                                                            user.id ===
                                                            currentUserId
                                                        }
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    `Are you sure you want to delete ${user.name}?`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    `/users/${user.id}`,
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        Delete
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

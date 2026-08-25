import { Head, Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface UserRow {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    permissions: string[] | null;
    created_at: string;
    is_online: boolean;
}

const ONLINE_STATUS_POLL_MS = 15_000;

function OnlineStatusBadge({ online }: { online: boolean }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs font-medium">
            <span
                className={`h-2 w-2 rounded-full ${online ? 'bg-green-500' : 'bg-gray-400'}`}
            />
            <span
                className={
                    online
                        ? 'text-green-700 dark:text-green-400'
                        : 'text-muted-foreground'
                }
            >
                {online ? 'Online' : 'Offline'}
            </span>
        </span>
    );
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
    const [onlineIds, setOnlineIds] = useState<Set<number>>(
        () => new Set(users.filter((u) => u.is_online).map((u) => u.id)),
    );

    // Polls a lightweight endpoint (just ids, no full user payload) so the
    // Online/Offline column stays live without a full page reload.
    useEffect(() => {
        let cancelled = false;

        const load = () => {
            fetch('/users/online-status')
                .then((res) => (res.ok ? res.json() : Promise.reject()))
                .then((json) => {
                    if (!cancelled && Array.isArray(json.online_ids)) {
                        setOnlineIds(new Set(json.online_ids));
                    }
                })
                .catch(() => {
                    // Silently ignore — the column just keeps its last known state.
                });
        };

        const interval = setInterval(load, ONLINE_STATUS_POLL_MS);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, []);

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
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-teal-100 p-2 dark:bg-teal-900/30">
                            <Users className="h-4 w-4 text-teal-600 dark:text-teal-400" />
                        </div>
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
                                            <th className="px-4 py-2 font-medium">
                                                Status
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
                                                <td className="px-4 py-3">
                                                    <OnlineStatusBadge
                                                        online={onlineIds.has(
                                                            user.id,
                                                        )}
                                                    />
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

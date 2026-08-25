import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ActivityLogUser {
    id: number;
    name: string;
    email: string;
}

interface ActivityLogEntry {
    id: number;
    user: ActivityLogUser | null;
    action: string;
    subject_type: string | null;
    subject_label: string | null;
    description: string;
    ip_address: string | null;
    created_at: string;
}

const ACTION_STYLES: Record<string, string> = {
    login: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    created:
        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    updated:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    deleted: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    synced: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

function actionClass(action: string): string {
    return (
        ACTION_STYLES[action] ??
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
    );
}

function formatTime(time: string): string {
    return new Date(time).toLocaleString();
}

export default function Index({ logs }: { logs: ActivityLogEntry[] }) {
    return (
        <>
            <Head title="Activity Log" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-bold">Activity Log</h1>
                    <p className="text-sm text-muted-foreground">
                        Logins, and create/update/delete actions across the app
                        — most recent first.
                    </p>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-orange-100 p-2 dark:bg-orange-900/30">
                            <History className="h-4 w-4 text-orange-600 dark:text-orange-400" />
                        </div>
                        <CardTitle>Last {logs.length} Events</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {logs.length === 0 ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <History className="h-4 w-4" />
                                ยังไม่มีกิจกรรมที่บันทึกไว้
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Time
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                User
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Action
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Description
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                IP Address
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {logs.map((log) => (
                                            <tr
                                                key={log.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-2 whitespace-nowrap text-muted-foreground">
                                                    {formatTime(log.created_at)}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {log.user ? (
                                                        <div>
                                                            <p className="font-medium">
                                                                {log.user.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {log.user.email}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            System
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Badge
                                                        className={actionClass(
                                                            log.action,
                                                        )}
                                                    >
                                                        {log.action}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2">
                                                    {log.description}
                                                </td>
                                                <td className="px-4 py-2 font-mono text-xs whitespace-nowrap text-muted-foreground">
                                                    {log.ip_address ?? '-'}
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

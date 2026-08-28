import { Head, usePage } from '@inertiajs/react';
import { AlertCircle, FileDown, Filter, Loader2, Radio, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyError, notifyInfo } from '@/lib/swal';

const LIMIT_OPTIONS = [5, 20, 50, 100];
const DEFAULT_LIMIT = 5;

interface RadiusEntry {
    time: string;
    request_id: string;
    status: string;
    status_ok: boolean;
    username: string | null;
    auth_type: string | null;
    client: string | null;
    port: string | null;
    mac: string | null;
}

function formatTime(time: string): string {
    return new Date(time).toLocaleString();
}

// datetime-local inputs need "YYYY-MM-DDTHH:mm" in the *local* timezone —
// Date#toISOString() is UTC, which would silently shift the displayed
// default by the server's UTC offset.
function toDatetimeLocalValue(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}`
    );
}

export default function Index({ host }: { host: string }) {
    const [entries, setEntries] = useState<RadiusEntry[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const [username, setUsername] = useState('');
    const [mac, setMac] = useState('');
    const [client, setClient] = useState('');
    const [status, setStatus] = useState('');
    const [filterActive, setFilterActive] = useState(false);
    const [limit, setLimit] = useState(DEFAULT_LIMIT);

    const [exportOpen, setExportOpen] = useState(false);
    const [exportFrom, setExportFrom] = useState(() =>
        toDatetimeLocalValue(new Date(Date.now() - 60 * 60 * 1000)),
    );
    const [exportTo, setExportTo] = useState(() =>
        toDatetimeLocalValue(new Date()),
    );

    const { errors } = usePage().props as unknown as {
        errors?: Record<string, string>;
    };

    // A failed export redirects back with a flashed validation error via a
    // plain browser navigation (window.location.href, not an Inertia
    // visit), so the app remounts fresh with that error already in props —
    // a mount-only effect is the right place to surface it.
    useEffect(() => {
        if (errors?.from || errors?.to) {
            notifyError(errors.from ?? errors.to, 'ส่งออก Excel ไม่สำเร็จ');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleExport = () => {
        const params = new URLSearchParams({
            from: exportFrom,
            to: exportTo,
        });

        if (username) {
            params.set('username', username);
        }

        if (mac) {
            params.set('mac', mac);
        }

        if (client) {
            params.set('client', client);
        }

        if (status) {
            params.set('status', status);
        }

        notifyInfo(
            'กำลังสร้างไฟล์ Excel และเริ่มดาวน์โหลด...',
            'กำลังดำเนินการ',
        );
        window.location.href = `/kuwin-radius/export?${params.toString()}`;
        setExportOpen(false);
    };

    const fetchEntries = useCallback(
        async (
            activeUsername: string,
            activeMac: string,
            activeClient: string,
            activeStatus: string,
            activeLimit: number,
        ): Promise<RadiusEntry[]> => {
            const params = new URLSearchParams({ limit: String(activeLimit) });

            if (activeUsername) {
                params.set('username', activeUsername);
            }

            if (activeMac) {
                params.set('mac', activeMac);
            }

            if (activeClient) {
                params.set('client', activeClient);
            }

            if (activeStatus) {
                params.set('status', activeStatus);
            }

            const res = await fetch(
                `/api/kuwin-radius/logs?${params.toString()}`,
            );
            const json = await res.json();

            if (!res.ok) {
                throw new Error(json.message ?? 'radius log request failed');
            }

            return json.data ?? [];
        },
        [],
    );

    // Used by the Filter/Refresh actions (click handlers, not an effect),
    // so setting state synchronously up front here is fine.
    const load = useCallback(
        async (
            activeUsername: string,
            activeMac: string,
            activeClient: string,
            activeStatus: string,
            activeLimit: number,
        ) => {
            setLoading(true);
            setError(null);

            try {
                setEntries(
                    await fetchEntries(
                        activeUsername,
                        activeMac,
                        activeClient,
                        activeStatus,
                        activeLimit,
                    ),
                );
            } catch (err) {
                setEntries(null);
                setError(
                    err instanceof Error
                        ? err.message
                        : 'ไม่สามารถอ่าน log จาก Radius server ได้',
                );
            } finally {
                setLoading(false);
            }
        },
        [fetchEntries],
    );

    useEffect(() => {
        let cancelled = false;

        fetchEntries('', '', '', '', DEFAULT_LIMIT)
            .then((data) => {
                if (!cancelled) {
                    setEntries(data);
                }
            })
            .catch((err) => {
                if (!cancelled) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'ไม่สามารถอ่าน log จาก Radius server ได้',
                    );
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [fetchEntries]);

    const applyFilter = () => {
        setFilterActive(!!(username || mac || client || status));
        load(username, mac, client, status, limit);
    };

    const clearFilter = () => {
        setUsername('');
        setMac('');
        setClient('');
        setStatus('');
        setFilterActive(false);
        load('', '', '', '', limit);
    };

    const handleLimitChange = (value: string) => {
        const nextLimit = Number(value);
        setLimit(nextLimit);
        load(username, mac, client, status, nextLimit);
    };

    return (
        <>
            <Head title="KUWIN Radius" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">KUWIN Radius</h1>
                        <p className="text-sm text-muted-foreground">
                            Auth log entries, read live over SSH from{' '}
                            <span className="font-mono">{host}</span>.
                        </p>
                    </div>

                    <Dialog open={exportOpen} onOpenChange={setExportOpen}>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <FileDown className="mr-2 h-4 w-4" />
                                Export
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>
                                    Export KUWIN Radius Log เป็น Excel
                                </DialogTitle>
                            </DialogHeader>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="export-from">
                                        ตั้งแต่วันที่-เวลา
                                    </Label>
                                    <Input
                                        id="export-from"
                                        type="datetime-local"
                                        value={exportFrom}
                                        onChange={(e) =>
                                            setExportFrom(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="export-to">
                                        ถึงวันที่-เวลา
                                    </Label>
                                    <Input
                                        id="export-to"
                                        type="datetime-local"
                                        value={exportTo}
                                        onChange={(e) =>
                                            setExportTo(e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Up to 1 hour at a time — a wider range
                                overloads the server. Any Username/MAC/
                                Client/Status filter currently set above is
                                applied to the export too.
                            </p>
                            {(errors?.from || errors?.to) && (
                                <p className="text-sm text-red-500">
                                    {errors.from ?? errors.to}
                                </p>
                            )}
                            <DialogFooter>
                                <Button onClick={handleExport}>
                                    <FileDown className="mr-2 h-4 w-4" />
                                    ดาวน์โหลด Excel
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-4 pt-6">
                        <div className="space-y-2">
                            <Label>จำนวนรายการ</Label>
                            <Select
                                value={String(limit)}
                                onValueChange={handleLimitChange}
                            >
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {LIMIT_OPTIONS.map((option) => (
                                        <SelectItem
                                            key={option}
                                            value={String(option)}
                                        >
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="username-filter">Username</Label>
                            <Input
                                id="username-filter"
                                className="w-48"
                                placeholder="e.g. b630207576"
                                value={username}
                                onChange={(e) => setUsername(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilter()
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mac-filter">MAC Address</Label>
                            <Input
                                id="mac-filter"
                                className="w-48"
                                placeholder="e.g. 765db517e054"
                                value={mac}
                                onChange={(e) => setMac(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilter()
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="client-filter">Client (NAS)</Label>
                            <Input
                                id="client-filter"
                                className="w-48"
                                placeholder="e.g. wlc-c9800CL"
                                value={client}
                                onChange={(e) => setClient(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilter()
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="status-filter">Status</Label>
                            <Input
                                id="status-filter"
                                className="w-48"
                                placeholder="e.g. OK, incorrect, eap"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilter()
                                }
                            />
                        </div>
                        <Button
                            size="sm"
                            onClick={applyFilter}
                            disabled={loading}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            Filter
                        </Button>
                        {filterActive && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={clearFilter}
                                disabled={loading}
                            >
                                <X className="mr-2 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {loading ? (
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            {filterActive
                                ? `กำลังค้นหาทั้ง log จาก ${host}...`
                                : `กำลังเชื่อมต่อ SSH และอ่าน log จาก ${host}...`}
                        </CardContent>
                    </Card>
                ) : error ? (
                    <Card className="border-l-4 border-l-red-500">
                        <CardContent className="flex items-center justify-between gap-4 pt-6">
                            <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <AlertCircle className="h-4 w-4 shrink-0" />
                                <p className="text-sm">{error}</p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    load(username, mac, client, status, limit)
                                }
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                ) : entries && entries.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            {filterActive
                                ? 'ไม่พบรายการที่ตรงกับตัวกรอง'
                                : 'ไม่พบรายการ login ใน log'}
                        </CardContent>
                    </Card>
                ) : (
                    entries && (
                        <Card>
                            <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                                <div className="rounded-lg bg-indigo-100 p-2 dark:bg-indigo-900/30">
                                    <Radio className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <CardTitle>
                                    {filterActive
                                        ? 'Filtered Results'
                                        : `Last ${limit} Entries`}
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        {entries.length} รายการ
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-3 py-2 font-medium">
                                                    Time
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Username
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Auth Type
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Client
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Port
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    MAC
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {entries.map((entry) => (
                                                <tr
                                                    key={`${entry.request_id}-${entry.time}`}
                                                    className="hover:bg-muted/30"
                                                >
                                                    <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                        {formatTime(entry.time)}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Badge
                                                            className={
                                                                entry.status_ok
                                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                            }
                                                        >
                                                            {entry.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2 font-medium">
                                                        {entry.username ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {entry.auth_type ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {entry.client ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-muted-foreground">
                                                        {entry.port ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                                        {entry.mac ?? '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )
                )}
            </div>
        </>
    );
}

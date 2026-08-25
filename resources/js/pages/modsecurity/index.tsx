import { Head } from '@inertiajs/react';
import { AlertCircle, Filter, Loader2, ShieldAlert, X } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ModSecMessage {
    text: string;
    rule_id: string | null;
    severity: string | null;
}

interface ModSecEntry {
    id: string;
    time: string;
    source_ip: string;
    request: string | null;
    messages: ModSecMessage[];
}

interface Props {
    vms: string[];
}

const NONE = 'none';

const SEVERITY_STYLES: Record<string, string> = {
    CRITICAL: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    ERROR: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    WARNING:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    NOTICE: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
};

function severityClass(severity: string | null): string {
    if (!severity) {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }

    return (
        SEVERITY_STYLES[severity.toUpperCase()] ??
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
    );
}

function formatTime(time: string): string {
    return new Date(time).toLocaleString();
}

export default function Index({ vms }: Props) {
    const [vm, setVm] = useState(NONE);
    const [entries, setEntries] = useState<ModSecEntry[] | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState('');
    const [filterActive, setFilterActive] = useState(false);

    const load = useCallback(
        async (selectedVm: string, from: string, to: string) => {
            if (selectedVm === NONE) {
                return;
            }

            setLoading(true);
            setError(null);

            try {
                const params = new URLSearchParams({ vm: selectedVm });

                if (from) {
                    params.set('from', from);
                }

                if (to) {
                    params.set('to', to);
                }

                const res = await fetch(
                    `/api/modsecurity/logs?${params.toString()}`,
                );
                const json = await res.json();

                if (!res.ok) {
                    throw new Error(
                        json.message ?? 'modsecurity request failed',
                    );
                }

                setEntries(json.data ?? []);
            } catch (err) {
                setEntries(null);
                setError(
                    err instanceof Error
                        ? err.message
                        : 'ไม่สามารถอ่าน log จาก VM ได้',
                );
            } finally {
                setLoading(false);
            }
        },
        [],
    );

    const handleVmChange = (value: string) => {
        setVm(value);
        setFromDate('');
        setToDate('');
        setFilterActive(false);
        setEntries(null);

        if (value !== NONE) {
            load(value, '', '');
        }
    };

    const applyFilter = () => {
        setFilterActive(true);
        load(vm, fromDate, toDate);
    };

    const clearFilter = () => {
        setFromDate('');
        setToDate('');
        setFilterActive(false);
        load(vm, '', '');
    };

    return (
        <>
            <Head title="Mod Security" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-bold">Mod Security</h1>
                    <p className="text-sm text-muted-foreground">
                        ModSecurity audit log errors, read live over SSH from
                        the selected VM.
                    </p>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-4 pt-6">
                        <div className="space-y-2">
                            <Label>เลือก VM</Label>
                            <Select value={vm} onValueChange={handleVmChange}>
                                <SelectTrigger className="w-64">
                                    <SelectValue placeholder="เลือก VM" />
                                </SelectTrigger>
                                <SelectContent>
                                    {vms.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {vm !== NONE && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="from-date">
                                        ตั้งแต่วันที่
                                    </Label>
                                    <Input
                                        id="from-date"
                                        type="date"
                                        className="w-40"
                                        value={fromDate}
                                        onChange={(e) =>
                                            setFromDate(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="to-date">ถึงวันที่</Label>
                                    <Input
                                        id="to-date"
                                        type="date"
                                        className="w-40"
                                        value={toDate}
                                        onChange={(e) =>
                                            setToDate(e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    size="sm"
                                    onClick={applyFilter}
                                    disabled={loading || (!fromDate && !toDate)}
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
                            </>
                        )}
                    </CardContent>
                </Card>

                {vm === NONE ? (
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6 text-sm text-muted-foreground">
                            <ShieldAlert className="h-5 w-5" />
                            เลือก VM ด้านบนเพื่อดู ModSecurity error log
                        </CardContent>
                    </Card>
                ) : loading ? (
                    <Card>
                        <CardContent className="flex items-center gap-3 pt-6 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            กำลังเชื่อมต่อ SSH และอ่าน log จาก {vm}...
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
                                onClick={() => load(vm, fromDate, toDate)}
                            >
                                ลองใหม่
                            </Button>
                        </CardContent>
                    </Card>
                ) : entries && entries.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            {filterActive
                                ? 'ไม่พบ error ในช่วงวันที่ที่เลือก'
                                : 'ไม่พบ error ใน ModSecurity audit log'}
                        </CardContent>
                    </Card>
                ) : (
                    entries && (
                        <Card className="flex min-h-0 flex-1 flex-col">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-lg bg-rose-100 p-2 dark:bg-rose-900/30">
                                        <ShieldAlert className="h-4 w-4 text-rose-600 dark:text-rose-400" />
                                    </div>
                                    <CardTitle>
                                        {filterActive
                                            ? `Errors ในช่วงที่เลือก`
                                            : 'Last 5 Errors'}
                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                            {entries.length} รายการ
                                        </span>
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="min-h-0 flex-1 space-y-3 overflow-auto">
                                {entries.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="rounded-md border p-3"
                                    >
                                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">
                                                    {formatTime(entry.time)}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    from {entry.source_ip}
                                                </span>
                                            </div>
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {entry.id}
                                            </span>
                                        </div>

                                        {entry.request && (
                                            <p className="mb-2 truncate font-mono text-xs text-muted-foreground">
                                                {entry.request}
                                            </p>
                                        )}

                                        <div className="space-y-1.5">
                                            {entry.messages.map((msg, i) => (
                                                <div
                                                    key={i}
                                                    className="flex items-start gap-2 text-sm"
                                                >
                                                    <Badge
                                                        className={severityClass(
                                                            msg.severity,
                                                        )}
                                                    >
                                                        {msg.severity ?? '-'}
                                                    </Badge>
                                                    <span className="flex-1">
                                                        {msg.text}
                                                        {msg.rule_id && (
                                                            <span className="ml-1 text-xs text-muted-foreground">
                                                                (rule{' '}
                                                                {msg.rule_id})
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )
                )}
            </div>
        </>
    );
}

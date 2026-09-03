import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Lock, ScanLine } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface Item {
    id: number;
    asset_id: number;
    asset_code: string;
    name: string;
    category: string | null;
    location: string | null;
    department: string | null;
    counted: boolean;
    status: string | null;
    status_label: string | null;
    counted_by: string | null;
    counted_at: string | null;
}

interface Props {
    session: {
        id: number;
        name: string;
        status: string;
        status_label: string;
        scope_category: string | null;
        scope_location: string | null;
        note: string | null;
        started_by: string | null;
        started_at: string | null;
        closed_at: string | null;
    };
    items: Item[];
    progress: {
        total: number;
        counted: number;
        by_status: { key: string; label: string; count: number }[];
    };
    statuses: Record<string, string>;
}

const STATUS_BADGE: Record<string, string> = {
    normal: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    damaged:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    moved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    missing: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

export default function CountingShow({
    session,
    items,
    progress,
    statuses,
}: Props) {
    const [tab, setTab] = useState<'all' | 'todo' | 'done'>('todo');
    const [q, setQ] = useState('');
    const open = session.status === 'open';

    const shown = useMemo(() => {
        const needle = q.trim().toLowerCase();

        return items.filter((i) => {
            if (tab === 'todo' && i.counted) {
                return false;
            }

            if (tab === 'done' && !i.counted) {
                return false;
            }

            if (
                needle &&
                !`${i.asset_code} ${i.name} ${i.location ?? ''}`
                    .toLowerCase()
                    .includes(needle)
            ) {
                return false;
            }

            return true;
        });
    }, [items, tab, q]);

    const percent = progress.total
        ? Math.round((progress.counted / progress.total) * 100)
        : 0;

    const count = (assetId: number, status: string) => {
        router.post(
            `/it-asset-counting/${session.id}/count`,
            { it_asset_id: assetId, status },
            { preserveScroll: true },
        );
    };

    const close = () => {
        if (window.confirm('ปิดรอบตรวจนับนี้? จะไม่สามารถตรวจนับเพิ่มได้')) {
            router.post(
                `/it-asset-counting/${session.id}/close`,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title={session.name} />
            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/it-asset-counting">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-bold">
                                {session.name}
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                {[
                                    session.scope_category,
                                    session.scope_location,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') ||
                                    'ทุกหมวดหมู่ / ทุกสถานที่'}{' '}
                                · {session.status_label}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {open && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href="/it-assets/scan">
                                        <ScanLine className="mr-2 h-4 w-4" />
                                        สแกน QR
                                    </Link>
                                </Button>
                                <Button variant="outline" onClick={close}>
                                    <Lock className="mr-2 h-4 w-4" />
                                    ปิดรอบ
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* progress */}
                <Card>
                    <CardContent className="space-y-3 p-4">
                        <div className="flex items-center gap-3">
                            <div className="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                            <span className="text-sm font-semibold tabular-nums">
                                {progress.counted}/{progress.total} ({percent}%)
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2 text-xs">
                            {progress.by_status.map((s) => (
                                <span
                                    key={s.key}
                                    className={`rounded-full px-2 py-0.5 font-medium ${STATUS_BADGE[s.key] ?? 'bg-muted'}`}
                                >
                                    {s.label}: {s.count}
                                </span>
                            ))}
                            <span className="rounded-full bg-muted px-2 py-0.5 font-medium">
                                ยังไม่ตรวจนับ:{' '}
                                {progress.total - progress.counted}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-3 space-y-0">
                        <div className="flex flex-wrap items-center gap-2">
                            {(['todo', 'done', 'all'] as const).map((t) => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => setTab(t)}
                                    className={`rounded-md px-3 py-1 text-sm font-medium ${
                                        tab === t
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {t === 'todo'
                                        ? 'ยังไม่ตรวจนับ'
                                        : t === 'done'
                                          ? 'ตรวจนับแล้ว'
                                          : 'ทั้งหมด'}
                                </button>
                            ))}
                            <Input
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="ค้นหารหัส/ชื่อ/สถานที่"
                                className="ml-auto max-w-xs"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {shown.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                ไม่มีรายการ
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {shown.map((i) => (
                                    <li
                                        key={i.id}
                                        className="flex flex-wrap items-center gap-3 py-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={`/it-assets/${i.asset_id}`}
                                                    className="font-mono text-sm font-medium hover:underline"
                                                >
                                                    {i.asset_code}
                                                </Link>
                                                {i.counted && (
                                                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                                                )}
                                                {i.status_label && (
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_BADGE[i.status ?? ''] ?? 'bg-muted'}`}
                                                    >
                                                        {i.status_label}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="truncate text-sm">
                                                {i.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {[i.location, i.department]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                                {i.counted && i.counted_by
                                                    ? ` · นับโดย ${i.counted_by}`
                                                    : ''}
                                            </p>
                                        </div>
                                        {open && (
                                            <div className="flex flex-wrap gap-1">
                                                {Object.entries(statuses).map(
                                                    ([key, label]) => (
                                                        <button
                                                            key={key}
                                                            type="button"
                                                            onClick={() =>
                                                                count(
                                                                    i.asset_id,
                                                                    key,
                                                                )
                                                            }
                                                            className={`rounded-md border px-2 py-1 text-xs font-medium transition-colors ${
                                                                i.status === key
                                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                                    : 'hover:bg-muted'
                                                            }`}
                                                        >
                                                            {label}
                                                        </button>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

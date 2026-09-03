import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Boxes, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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

interface SessionRow {
    id: number;
    name: string;
    status: string;
    status_label: string;
    scope_category: string | null;
    scope_location: string | null;
    total: number;
    counted: number;
    started_by: string | null;
    started_at: string | null;
    closed_at: string | null;
}

interface Props {
    sessions: SessionRow[];
    categories: { id: number; name: string }[];
    locations: string[];
}

function pct(counted: number, total: number): number {
    return total ? Math.round((counted / total) * 100) : 0;
}
function dt(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString('th-TH') : '—';
}

export default function CountingIndex({
    sessions,
    categories,
    locations,
}: Props) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [cat, setCat] = useState('');
    const [loc, setLoc] = useState('');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        router.post(
            '/it-asset-counting',
            {
                name,
                scope_category_id: cat || null,
                scope_location: loc || null,
                note,
            },
            {
                onSuccess: () => setOpen(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="รอบตรวจนับครุภัณฑ์" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/it-assets">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                รอบตรวจนับครุภัณฑ์
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                เปิดรอบ แล้วสแกน QR หรือกดตรวจนับทีละรายการ —
                                ความคืบหน้าอัปเดตอัตโนมัติ
                            </p>
                        </div>
                    </div>
                    <Button onClick={() => setOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        เปิดรอบตรวจนับ
                    </Button>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Boxes className="h-4 w-4" />
                        </div>
                        <CardTitle>ทั้งหมด ({sessions.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {sessions.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                ยังไม่มีรอบตรวจนับ
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {sessions.map((s) => (
                                    <li key={s.id} className="py-3">
                                        <Link
                                            href={`/it-asset-counting/${s.id}`}
                                            className="block space-y-1 rounded-lg p-2 hover:bg-muted/40"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-medium">
                                                    {s.name}
                                                </span>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        s.status === 'open'
                                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {s.status_label}
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {[
                                                    s.scope_category,
                                                    s.scope_location,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'ทุกหมวดหมู่ / ทุกสถานที่'}{' '}
                                                · เริ่ม {dt(s.started_at)}
                                                {s.started_by
                                                    ? ` โดย ${s.started_by}`
                                                    : ''}
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full bg-primary"
                                                        style={{
                                                            width: `${pct(s.counted, s.total)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="text-xs font-medium tabular-nums">
                                                    {s.counted}/{s.total} (
                                                    {pct(s.counted, s.total)}%)
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เปิดรอบตรวจนับใหม่</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-3">
                        <div className="space-y-1">
                            <Label>ชื่อรอบ *</Label>
                            <Input
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="เช่น ตรวจนับประจำปี 2569 - ชั้น 3"
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label>ขอบเขต: หมวดหมู่</Label>
                            <Select
                                value={cat || 'all'}
                                onValueChange={(v) =>
                                    setCat(v === 'all' ? '' : v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="ทุกหมวดหมู่" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        ทุกหมวดหมู่
                                    </SelectItem>
                                    {categories.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>ขอบเขต: สถานที่</Label>
                            <Select
                                value={loc || 'all'}
                                onValueChange={(v) =>
                                    setLoc(v === 'all' ? '' : v)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="ทุกสถานที่" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        ทุกสถานที่
                                    </SelectItem>
                                    {locations.map((l) => (
                                        <SelectItem key={l} value={l}>
                                            {l}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>หมายเหตุ</Label>
                            <Input
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            ระบบจะดึงครุภัณฑ์ที่อยู่ในขอบเขต
                            (ไม่รวมที่จำหน่าย/สูญหาย) มาเป็นรายการตรวจนับทันที
                        </p>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                ยกเลิก
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? 'กำลังเปิดรอบ…' : 'เปิดรอบ'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

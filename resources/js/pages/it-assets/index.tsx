import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import {
    Boxes,
    Download,
    FileSpreadsheet,
    Pencil,
    Plus,
    QrCode as QrCodeIcon,
    ScanLine,
    Search,
    Tags,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { QrCode } from '@/components/qr-code';
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
import { notifyError, notifySuccess } from '@/lib/swal';

interface AssetRow {
    id: number;
    asset_code: string;
    name: string;
    category: string | null;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    status: string;
    status_label: string;
    department: string | null;
    location: string | null;
    assigned_to: string | null;
    last_inspected_at: string | null;
    last_inspection_status: string | null;
    photo_url: string | null;
    public_url: string;
    category_id: number | null;
    purchased_at: string | null;
    price: string | number | null;
    warranty_until: string | null;
    notes: string | null;
}

interface Category {
    id: number;
    name: string;
    code_prefix: string | null;
    assets_count: number;
}

interface Filters {
    q: string | null;
    category: number | null;
    status: string | null;
    location: string | null;
    department: string | null;
}

interface StatBucket {
    key: string;
    label: string;
    count: number;
}

interface Props {
    assets: AssetRow[];
    filters: Filters;
    categories: Category[];
    statuses: Record<string, string>;
    locations: string[];
    departments: string[];
    stats: {
        total: number;
        by_status: StatBucket[];
        never_inspected: number;
        inspected_6m: number;
        damaged_or_missing: number;
    };
    canManage: boolean;
}

const INSPECTION_BADGE: Record<string, string> = {
    normal: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    damaged:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    moved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    missing: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};
const INSPECTION_LABEL: Record<string, string> = {
    normal: 'พบ/ปกติ',
    damaged: 'ชำรุด',
    moved: 'ย้าย',
    missing: 'ไม่พบ',
};

function prettyDateTime(iso: string | null): string {
    if (!iso) {
        return 'ยังไม่เคยตรวจ';
    }

    return new Date(iso).toLocaleString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const BLANK = {
    asset_code: '',
    name: '',
    it_asset_category_id: '',
    brand: '',
    model: '',
    serial_number: '',
    status: 'in_use',
    department: '',
    location: '',
    assigned_to: '',
    purchased_at: '',
    price: '',
    warranty_until: '',
    notes: '',
};
type FormState = typeof BLANK;

function toForm(a: AssetRow): FormState {
    return {
        asset_code: a.asset_code,
        name: a.name,
        it_asset_category_id: a.category_id ? String(a.category_id) : '',
        brand: a.brand ?? '',
        model: a.model ?? '',
        serial_number: a.serial_number ?? '',
        status: a.status,
        department: a.department ?? '',
        location: a.location ?? '',
        assigned_to: a.assigned_to ?? '',
        purchased_at: a.purchased_at ?? '',
        price: a.price != null ? String(a.price) : '',
        warranty_until: a.warranty_until ?? '',
        notes: a.notes ?? '',
    };
}

export default function Index({
    assets,
    filters,
    categories,
    statuses,
    locations,
    departments,
    stats,
    canManage,
}: Props) {
    // Deep-link from the detail page: /it-assets?edit=<id> opens straight
    // into that row's edit dialog. Resolved once, during first render.
    const [initialEdit] = useState<AssetRow | null>(() => {
        if (typeof window === 'undefined') {
            return null;
        }

        const id = Number(
            new URLSearchParams(window.location.search).get('edit'),
        );

        return id ? (assets.find((a) => a.id === id) ?? null) : null;
    });

    const [q, setQ] = useState(filters.q ?? '');
    const [editing, setEditing] = useState<AssetRow | null>(initialEdit);
    const [open, setOpen] = useState(!!initialEdit);
    const [catOpen, setCatOpen] = useState(false);
    const [qrAsset, setQrAsset] = useState<AssetRow | null>(null);
    const [form, setForm] = useState<FormState>(
        initialEdit ? toForm(initialEdit) : BLANK,
    );
    const [photo, setPhoto] = useState<File | null>(null);
    const [removePhoto, setRemovePhoto] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const photoRef = useRef<HTMLInputElement>(null);

    const applyFilters = useCallback(
        (patch: Record<string, string | undefined>) => {
            const merged: Record<string, string | undefined> = {
                q: filters.q ?? undefined,
                category: filters.category
                    ? String(filters.category)
                    : undefined,
                status: filters.status ?? undefined,
                location: filters.location ?? undefined,
                department: filters.department ?? undefined,
                ...patch,
            };

            const next: Record<string, string> = {};

            for (const [k, v] of Object.entries(merged)) {
                if (v) {
                    next[k] = v;
                }
            }

            router.get('/it-assets', next, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [filters],
    );

    const openCreate = () => {
        setEditing(null);
        setForm(BLANK);
        setPhoto(null);
        setRemovePhoto(false);
        setErrors({});
        setOpen(true);
    };

    const openEdit = (a: AssetRow) => {
        setEditing(a);
        setErrors({});
        setPhoto(null);
        setRemovePhoto(false);
        setForm(toForm(a));
        setOpen(true);
    };

    // Debounced search — update the query string, let the server filter,
    // same as the other list pages.
    useEffect(() => {
        const t = setTimeout(() => {
            if ((filters.q ?? '') === q.trim()) {
                return;
            }

            applyFilters({ q: q.trim() || undefined });
        }, 350);

        return () => clearTimeout(t);
    }, [q, filters.q, applyFilters]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload: Record<string, unknown> = { ...form };

        if (photo) {
            payload.photo = photo;
        }

        if (removePhoto) {
            payload.remove_photo = true;
        }

        if (editing) {
            payload._method = 'put';
        }

        router.post(
            editing ? `/it-assets/${editing.id}` : '/it-assets',
            payload as RequestPayload,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    notifySuccess('บันทึกครุภัณฑ์เรียบร้อยแล้ว', 'สำเร็จ');
                },
                onError: (err) => {
                    setErrors(err as Record<string, string>);
                    notifyError(
                        Object.values(err)[0] ?? 'กรุณาตรวจสอบข้อมูล',
                        'บันทึกไม่สำเร็จ',
                    );
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const remove = (a: AssetRow) => {
        if (
            window.confirm(
                `ลบครุภัณฑ์ ${a.asset_code} (${a.name})? ประวัติการตรวจสอบทั้งหมดจะถูกลบด้วย`,
            )
        ) {
            router.delete(`/it-assets/${a.id}`, { preserveScroll: true });
        }
    };

    const set = (k: keyof FormState, v: string) =>
        setForm((f) => ({ ...f, [k]: v }));

    return (
        <>
            <Head title="ครุภัณฑ์ไอที" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">ครุภัณฑ์ไอที</h1>
                        <p className="text-sm text-muted-foreground">
                            ทะเบียนครุภัณฑ์ การตรวจสอบ QR Code และรอบตรวจนับ
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/it-assets/scan">
                                <ScanLine className="mr-2 h-4 w-4" />
                                สแกน QR
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/it-asset-counting">
                                <Boxes className="mr-2 h-4 w-4" />
                                รอบตรวจนับ
                            </Link>
                        </Button>
                        {canManage && (
                            <Button
                                variant="outline"
                                onClick={() => setCatOpen(true)}
                            >
                                <Tags className="mr-2 h-4 w-4" />
                                หมวดหมู่
                            </Button>
                        )}
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            เพิ่มครุภัณฑ์
                        </Button>
                    </div>
                </div>

                {/* mini dashboard */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="ครุภัณฑ์ทั้งหมด" value={stats.total} />
                    <StatCard
                        label="ตรวจสอบใน 6 เดือน"
                        value={stats.inspected_6m}
                        tone="green"
                    />
                    <StatCard
                        label="ยังไม่เคยตรวจ"
                        value={stats.never_inspected}
                        tone="amber"
                    />
                    <StatCard
                        label="ชำรุด / ไม่พบ"
                        value={stats.damaged_or_missing}
                        tone="red"
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Boxes className="h-4 w-4" />
                        </div>
                        <CardTitle>รายการครุภัณฑ์ ({assets.length})</CardTitle>
                        <div className="ml-auto flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/it-assets/export?${new URLSearchParams(cleanFilters(filters)).toString()}`}
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Excel
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={`/it-assets/export?format=pdf&${new URLSearchParams(cleanFilters(filters)).toString()}`}
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    PDF
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* filters */}
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="ค้นหา รหัส/ชื่อ/Serial/ยี่ห้อ"
                                    className="pl-8"
                                />
                            </div>
                            <FilterSelect
                                value={
                                    filters.category
                                        ? String(filters.category)
                                        : ''
                                }
                                onChange={(v) =>
                                    applyFilters({ category: v || undefined })
                                }
                                placeholder="ทุกหมวดหมู่"
                                options={categories.map((c) => ({
                                    value: String(c.id),
                                    label: c.name,
                                }))}
                            />
                            <FilterSelect
                                value={filters.status ?? ''}
                                onChange={(v) =>
                                    applyFilters({ status: v || undefined })
                                }
                                placeholder="ทุกสถานะ"
                                options={Object.entries(statuses).map(
                                    ([value, label]) => ({ value, label }),
                                )}
                            />
                            <FilterSelect
                                value={filters.location ?? ''}
                                onChange={(v) =>
                                    applyFilters({ location: v || undefined })
                                }
                                placeholder="ทุกสถานที่"
                                options={locations.map((l) => ({
                                    value: l,
                                    label: l,
                                }))}
                            />
                            <FilterSelect
                                value={filters.department ?? ''}
                                onChange={(v) =>
                                    applyFilters({ department: v || undefined })
                                }
                                placeholder="ทุกหน่วยงาน"
                                options={departments.map((d) => ({
                                    value: d,
                                    label: d,
                                }))}
                            />
                        </div>

                        {assets.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                ไม่พบครุภัณฑ์ตามเงื่อนไข
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                รหัส
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                ชื่อ / หมวดหมู่
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                สถานะ
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                สถานที่ / หน่วยงาน
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                ตรวจสอบล่าสุด
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                จัดการ
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {assets.map((a) => (
                                            <tr
                                                key={a.id}
                                                className="align-top hover:bg-muted/30"
                                            >
                                                <td className="px-3 py-3 font-mono font-medium">
                                                    <Link
                                                        href={`/it-assets/${a.id}`}
                                                        className="hover:underline"
                                                    >
                                                        {a.asset_code}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="font-medium">
                                                        {a.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {[
                                                            a.category,
                                                            a.brand,
                                                            a.model,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <span className="inline-block rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                                        {a.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3 text-sm">
                                                    {a.location ?? '—'}
                                                    <div className="text-xs text-muted-foreground">
                                                        {a.department ?? ''}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 text-xs">
                                                    {prettyDateTime(
                                                        a.last_inspected_at,
                                                    )}
                                                    {a.last_inspection_status && (
                                                        <span
                                                            className={`ml-1 inline-block rounded-full px-1.5 py-0.5 ${
                                                                INSPECTION_BADGE[
                                                                    a
                                                                        .last_inspection_status
                                                                ] ?? 'bg-muted'
                                                            }`}
                                                        >
                                                            {
                                                                INSPECTION_LABEL[
                                                                    a
                                                                        .last_inspection_status
                                                                ]
                                                            }
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="QR Code"
                                                            onClick={() =>
                                                                setQrAsset(a)
                                                            }
                                                        >
                                                            <QrCodeIcon className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="แก้ไข"
                                                            onClick={() =>
                                                                openEdit(a)
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="ลบ"
                                                            onClick={() =>
                                                                remove(a)
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
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

            {/* Create / edit */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `แก้ไขครุภัณฑ์ ${editing.asset_code}`
                                : 'เพิ่มครุภัณฑ์ใหม่'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field label="รหัสครุภัณฑ์" error={errors.asset_code}>
                            <Input
                                value={form.asset_code}
                                onChange={(e) =>
                                    set('asset_code', e.target.value)
                                }
                                placeholder="เว้นว่างให้ระบบออกให้อัตโนมัติ"
                            />
                        </Field>
                        <Field label="ชื่อครุภัณฑ์ *" error={errors.name}>
                            <Input
                                value={form.name}
                                onChange={(e) => set('name', e.target.value)}
                                required
                            />
                        </Field>
                        <Field
                            label="หมวดหมู่"
                            error={errors.it_asset_category_id}
                        >
                            <Select
                                value={form.it_asset_category_id || 'none'}
                                onValueChange={(v) =>
                                    set(
                                        'it_asset_category_id',
                                        v === 'none' ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="— ไม่ระบุ —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        — ไม่ระบุ —
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
                        </Field>
                        <Field label="สถานะ" error={errors.status}>
                            <Select
                                value={form.status}
                                onValueChange={(v) => set('status', v)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(statuses).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field label="ยี่ห้อ" error={errors.brand}>
                            <Input
                                value={form.brand}
                                onChange={(e) => set('brand', e.target.value)}
                            />
                        </Field>
                        <Field label="รุ่น" error={errors.model}>
                            <Input
                                value={form.model}
                                onChange={(e) => set('model', e.target.value)}
                            />
                        </Field>
                        <Field
                            label="หมายเลขเครื่อง (Serial)"
                            error={errors.serial_number}
                        >
                            <Input
                                value={form.serial_number}
                                onChange={(e) =>
                                    set('serial_number', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="ผู้ครอบครอง" error={errors.assigned_to}>
                            <Input
                                value={form.assigned_to}
                                onChange={(e) =>
                                    set('assigned_to', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="หน่วยงาน" error={errors.department}>
                            <Input
                                value={form.department}
                                onChange={(e) =>
                                    set('department', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="สถานที่ติดตั้ง" error={errors.location}>
                            <Input
                                value={form.location}
                                onChange={(e) =>
                                    set('location', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="วันที่ได้มา" error={errors.purchased_at}>
                            <Input
                                type="date"
                                value={form.purchased_at}
                                onChange={(e) =>
                                    set('purchased_at', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="ราคา (บาท)" error={errors.price}>
                            <Input
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.price}
                                onChange={(e) => set('price', e.target.value)}
                            />
                        </Field>
                        <Field
                            label="รับประกันถึง"
                            error={errors.warranty_until}
                        >
                            <Input
                                type="date"
                                value={form.warranty_until}
                                onChange={(e) =>
                                    set('warranty_until', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="รูปภาพ" error={errors.photo}>
                            <Input
                                ref={photoRef}
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    setPhoto(e.target.files?.[0] ?? null);
                                    setRemovePhoto(false);
                                }}
                            />
                        </Field>
                        <div className="sm:col-span-2">
                            <Label>หมายเหตุ</Label>
                            <textarea
                                className="mt-1 flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.notes}
                                onChange={(e) => set('notes', e.target.value)}
                            />
                            {errors.notes && (
                                <p className="text-xs text-red-500">
                                    {errors.notes}
                                </p>
                            )}
                        </div>
                        <DialogFooter className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                ยกเลิก
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? 'กำลังบันทึก…' : 'บันทึก'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* QR preview */}
            <Dialog
                open={!!qrAsset}
                onOpenChange={(v) => !v && setQrAsset(null)}
            >
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            QR Code — {qrAsset?.asset_code}
                        </DialogTitle>
                    </DialogHeader>
                    {qrAsset && (
                        <div className="flex flex-col items-center gap-3">
                            <QrCode value={qrAsset.public_url} size={200} />
                            <p className="text-center text-sm text-muted-foreground">
                                {qrAsset.name}
                            </p>
                            <Button asChild className="w-full">
                                <a
                                    href={`/it-assets/${qrAsset.id}/label`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <QrCodeIcon className="mr-2 h-4 w-4" />
                                    พิมพ์ป้ายครุภัณฑ์
                                </a>
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {canManage && (
                <CategoryManager
                    open={catOpen}
                    onOpenChange={setCatOpen}
                    categories={categories}
                />
            )}
        </>
    );
}

function cleanFilters(f: Filters): Record<string, string> {
    const out: Record<string, string> = {};

    if (f.q) {
        out.q = f.q;
    }

    if (f.category) {
        out.category = String(f.category);
    }

    if (f.status) {
        out.status = f.status;
    }

    if (f.location) {
        out.location = f.location;
    }

    if (f.department) {
        out.department = f.department;
    }

    return out;
}

function StatCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'green' | 'amber' | 'red';
}) {
    const toneClass =
        tone === 'green'
            ? 'text-green-600 dark:text-green-400'
            : tone === 'amber'
              ? 'text-amber-600 dark:text-amber-400'
              : tone === 'red'
                ? 'text-red-600 dark:text-red-400'
                : '';

    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className={`text-2xl font-bold ${toneClass}`}>{value}</p>
            </CardContent>
        </Card>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

function FilterSelect({
    value,
    onChange,
    placeholder,
    options,
}: {
    value: string;
    onChange: (v: string) => void;
    placeholder: string;
    options: { value: string; label: string }[];
}) {
    return (
        <Select
            value={value || 'all'}
            onValueChange={(v) => onChange(v === 'all' ? '' : v)}
        >
            <SelectTrigger>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{placeholder}</SelectItem>
                {options.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                        {o.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function CategoryManager({
    open,
    onOpenChange,
    categories,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    categories: Category[];
}) {
    const [name, setName] = useState('');
    const [prefix, setPrefix] = useState('');

    const add = (e: FormEvent) => {
        e.preventDefault();
        router.post(
            '/it-asset-categories',
            { name, code_prefix: prefix },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setPrefix('');
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>หมวดหมู่ครุภัณฑ์</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    <ul className="divide-y rounded-lg border">
                        {categories.map((c) => (
                            <li
                                key={c.id}
                                className="flex items-center justify-between gap-2 p-2 text-sm"
                            >
                                <span>
                                    {c.name}
                                    {c.code_prefix && (
                                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                                            {c.code_prefix}
                                        </span>
                                    )}
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        ({c.assets_count})
                                    </span>
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `ลบหมวดหมู่ "${c.name}"?`,
                                            )
                                        ) {
                                            router.delete(
                                                `/it-asset-categories/${c.id}`,
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <form onSubmit={add} className="flex gap-2">
                        <Input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="ชื่อหมวดหมู่"
                            required
                        />
                        <Input
                            value={prefix}
                            onChange={(e) => setPrefix(e.target.value)}
                            placeholder="Prefix"
                            className="max-w-[7rem]"
                        />
                        <Button type="submit">
                            <Plus className="h-4 w-4" />
                        </Button>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

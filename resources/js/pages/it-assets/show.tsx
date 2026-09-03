import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ClipboardCheck,
    Pencil,
    Printer,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { QrCode } from '@/components/qr-code';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notifyError, notifySuccess } from '@/lib/swal';

interface Inspection {
    id: number;
    status: string;
    status_label: string;
    note: string | null;
    source: string;
    inspector: string | null;
    created_at: string;
    photos: string[];
}

interface Asset {
    id: number;
    asset_code: string;
    name: string;
    category: string | null;
    brand: string | null;
    model: string | null;
    serial_number: string | null;
    status_label: string;
    department: string | null;
    location: string | null;
    assigned_to: string | null;
    purchased_at: string | null;
    price: string | number | null;
    warranty_until: string | null;
    notes: string | null;
    photo_url: string | null;
    last_inspected_at: string | null;
    created_by: string | null;
    created_at: string | null;
    inspections: Inspection[];
    maintenances: {
        id: number;
        type: string;
        title: string;
        vendor: string | null;
        cost: string | number | null;
        status: string;
        performed_at: string | null;
        by: string | null;
    }[];
    assignments: {
        id: number;
        assignee_name: string;
        department: string | null;
        location: string | null;
        assigned_at: string | null;
        returned_at: string | null;
        note: string | null;
    }[];
    software: {
        id: number;
        name: string;
        version: string | null;
        license_type: string | null;
        seats: number | null;
        expires_at: string | null;
    }[];
}

interface Props {
    asset: Asset;
    inspectionStatuses: Record<string, string>;
    publicUrl: string;
}

const SOURCE_LABEL: Record<string, string> = {
    staff: 'เจ้าหน้าที่',
    public: 'หน้าสาธารณะ',
    counting: 'รอบตรวจนับ',
};
const STATUS_DOT: Record<string, string> = {
    normal: 'bg-green-500',
    damaged: 'bg-amber-500',
    moved: 'bg-blue-500',
    missing: 'bg-red-500',
};

function dt(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('th-TH', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Show({ asset, inspectionStatuses, publicUrl }: Props) {
    const [status, setStatus] = useState('');
    const [note, setNote] = useState('');
    const [photos, setPhotos] = useState<FileList | null>(null);
    const [saving, setSaving] = useState(false);

    const submitInspection = (e: FormEvent) => {
        e.preventDefault();

        if (!status) {
            return;
        }

        setSaving(true);
        const payload: Record<string, unknown> = { status, note };

        if (photos) {
            payload.photos = Array.from(photos);
        }

        router.post(
            `/it-assets/${asset.id}/inspections`,
            payload as RequestPayload,
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setStatus('');
                    setNote('');
                    setPhotos(null);
                    notifySuccess('บันทึกผลการตรวจสอบแล้ว', 'สำเร็จ');
                },
                onError: (err) =>
                    notifyError(
                        Object.values(err)[0] ?? 'บันทึกไม่สำเร็จ',
                        'ผิดพลาด',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={`${asset.asset_code} — ${asset.name}`} />
            <div className="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="icon" asChild>
                            <Link href="/it-assets">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="font-mono text-xl font-bold">
                                {asset.asset_code}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {asset.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={`/it-assets/${asset.id}/label`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Printer className="mr-2 h-4 w-4" />
                                พิมพ์ป้าย
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/it-assets?edit=${asset.id}`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                แก้ไขข้อมูล
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* info */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>ข้อมูลครุภัณฑ์</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {asset.photo_url && (
                                <img
                                    src={asset.photo_url}
                                    alt={asset.name}
                                    className="col-span-full max-h-56 rounded-lg border object-contain"
                                />
                            )}
                            <Info label="หมวดหมู่" value={asset.category} />
                            <Info label="สถานะ" value={asset.status_label} />
                            <Info label="ยี่ห้อ" value={asset.brand} />
                            <Info label="รุ่น" value={asset.model} />
                            <Info
                                label="หมายเลขเครื่อง"
                                value={asset.serial_number}
                            />
                            <Info
                                label="ผู้ครอบครอง"
                                value={asset.assigned_to}
                            />
                            <Info label="หน่วยงาน" value={asset.department} />
                            <Info label="สถานที่" value={asset.location} />
                            <Info
                                label="วันที่ได้มา"
                                value={asset.purchased_at}
                            />
                            <Info
                                label="ราคา"
                                value={
                                    asset.price != null
                                        ? `${Number(asset.price).toLocaleString('th-TH')} บาท`
                                        : null
                                }
                            />
                            <Info
                                label="รับประกันถึง"
                                value={asset.warranty_until}
                            />
                            <Info
                                label="ตรวจสอบล่าสุด"
                                value={dt(asset.last_inspected_at)}
                            />
                            {asset.notes && (
                                <div className="col-span-full">
                                    <p className="text-xs text-muted-foreground">
                                        หมายเหตุ
                                    </p>
                                    <p className="text-sm whitespace-pre-wrap">
                                        {asset.notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* QR */}
                    <Card>
                        <CardHeader>
                            <CardTitle>QR Code</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-3">
                            <QrCode value={publicUrl} size={180} />
                            <p className="text-center text-xs break-all text-muted-foreground">
                                {publicUrl}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* record an inspection */}
                <Card>
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                        <ClipboardCheck className="h-4 w-4 text-primary" />
                        <CardTitle>บันทึกการตรวจสอบ</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submitInspection}
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            <div className="sm:col-span-2">
                                <Label>ผลการตรวจสอบ</Label>
                                <div className="mt-1 flex flex-wrap gap-2">
                                    {Object.entries(inspectionStatuses).map(
                                        ([key, label]) => (
                                            <button
                                                key={key}
                                                type="button"
                                                onClick={() => setStatus(key)}
                                                className={`rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors ${
                                                    status === key
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'hover:bg-muted'
                                                }`}
                                            >
                                                {label}
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="note">หมายเหตุ</Label>
                                <textarea
                                    id="note"
                                    className="mt-1 flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="photos">
                                    รูปถ่าย (สูงสุด 5)
                                </Label>
                                <Input
                                    id="photos"
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    onChange={(e) => setPhotos(e.target.files)}
                                />
                            </div>
                            <div>
                                <Button
                                    type="submit"
                                    disabled={!status || saving}
                                >
                                    {saving
                                        ? 'กำลังบันทึก…'
                                        : 'บันทึกการตรวจสอบ'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* history */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            ประวัติการตรวจสอบ ({asset.inspections.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {asset.inspections.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                ยังไม่มีประวัติการตรวจสอบ
                            </p>
                        ) : (
                            <ol className="relative space-y-4 border-l pl-5">
                                {asset.inspections.map((i) => (
                                    <li key={i.id} className="relative">
                                        <span
                                            className={`absolute top-1.5 -left-[1.4rem] h-3 w-3 rounded-full ${STATUS_DOT[i.status] ?? 'bg-muted'}`}
                                        />
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <span className="font-medium">
                                                {i.status_label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {dt(i.created_at)}
                                            </span>
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                                                {SOURCE_LABEL[i.source] ??
                                                    i.source}
                                            </span>
                                            {i.inspector && (
                                                <span className="text-xs text-muted-foreground">
                                                    โดย {i.inspector}
                                                </span>
                                            )}
                                        </div>
                                        {i.note && (
                                            <p className="mt-1 text-sm whitespace-pre-wrap">
                                                {i.note}
                                            </p>
                                        )}
                                        {i.photos.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {i.photos.map((p) => (
                                                    <a
                                                        key={p}
                                                        href={p}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={p}
                                                            alt="inspection"
                                                            className="h-16 w-16 rounded border object-cover"
                                                        />
                                                    </a>
                                                ))}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>

                {/* lifecycle — read-only in phase 1 */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <MiniList
                        icon={<Wrench className="h-4 w-4" />}
                        title="ซ่อม / บำรุงรักษา"
                        empty="ยังไม่มีรายการ"
                        rows={asset.maintenances.map((m) => ({
                            key: m.id,
                            primary: m.title,
                            secondary: `${m.performed_at ?? ''} · ${m.status}`,
                        }))}
                    />
                    <MiniList
                        title="ประวัติผู้ครอบครอง / สถานที่"
                        empty="ยังไม่มีรายการ"
                        rows={asset.assignments.map((a) => ({
                            key: a.id,
                            primary: a.assignee_name,
                            secondary: [a.department, a.location, a.assigned_at]
                                .filter(Boolean)
                                .join(' · '),
                        }))}
                    />
                    <MiniList
                        title="ซอฟต์แวร์ / ลิขสิทธิ์"
                        empty="ยังไม่มีรายการ"
                        rows={asset.software.map((s) => ({
                            key: s.id,
                            primary: `${s.name}${s.version ? ` ${s.version}` : ''}`,
                            secondary: [
                                s.license_type,
                                s.seats ? `${s.seats} เครื่อง` : null,
                                s.expires_at ? `หมดอายุ ${s.expires_at}` : null,
                            ]
                                .filter(Boolean)
                                .join(' · '),
                        }))}
                    />
                </div>
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-sm">{value || '—'}</p>
        </div>
    );
}

function MiniList({
    icon,
    title,
    empty,
    rows,
}: {
    icon?: React.ReactNode;
    title: string;
    empty: string;
    rows: { key: number; primary: string; secondary: string }[];
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center gap-2 space-y-0">
                {icon}
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <ul className="divide-y text-sm">
                        {rows.map((r) => (
                            <li key={r.key} className="py-2">
                                <p className="font-medium">{r.primary}</p>
                                <p className="text-xs text-muted-foreground">
                                    {r.secondary}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

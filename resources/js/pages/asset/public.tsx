import { Head } from '@inertiajs/react';
import { CheckCircle2, MapPin, PackageSearch } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

interface AssetInfo {
    asset_code: string;
    name: string;
    category: string | null;
    brand: string | null;
    model: string | null;
    department: string | null;
    location: string | null;
    status_label: string;
    photo_url: string | null;
    last_inspected_at: string | null;
    last_inspection_status: string | null;
}

interface Props {
    asset: AssetInfo;
    token: string;
    statuses: Record<string, string>; // normal|damaged|moved|missing => Thai
    currentUserName: string | null;
}

function csrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

function prettyDateTime(iso: string): string {
    return new Date(iso).toLocaleString('th-TH', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const STATUS_HINT: Record<string, string> = {
    normal: 'อยู่ในที่ตั้ง สภาพใช้งานได้',
    damaged: 'ชำรุด/เสียหาย ใช้งานไม่ได้',
    moved: 'ถูกย้ายไปที่อื่น',
    missing: 'หาไม่พบในจุดที่ควรอยู่',
};

export default function PublicAsset({
    asset,
    token,
    statuses,
    currentUserName,
}: Props) {
    const [status, setStatus] = useState('');
    const [note, setNote] = useState('');
    const [inspectorName, setInspectorName] = useState(currentUserName ?? '');
    const [photos, setPhotos] = useState<FileList | null>(null);
    const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(
        null,
    );
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [done, setDone] = useState<{
        status_label: string;
        saved_at: string;
        inspector: string | null;
    } | null>(null);
    const photoRef = useRef<HTMLInputElement>(null);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (p) =>
                setCoords({
                    lat: p.coords.latitude,
                    lng: p.coords.longitude,
                }),
            () => setError('ไม่สามารถอ่านตำแหน่งได้'),
            { enableHighAccuracy: true, timeout: 8000 },
        );
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();

        if (!status) {
            return;
        }

        setSaving(true);
        setError(null);

        const fd = new FormData();
        fd.append('status', status);
        fd.append('note', note);
        fd.append('inspector_name', inspectorName);

        if (coords) {
            fd.append('latitude', String(coords.lat));
            fd.append('longitude', String(coords.lng));
        }

        Array.from(photos ?? []).forEach((f) => fd.append('photos[]', f));

        try {
            const res = await fetch(
                `/asset/${encodeURIComponent(token)}/inspect`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    body: fd,
                },
            );

            if (res.ok) {
                setDone(await res.json());

                return;
            }

            if (res.status === 422) {
                setError('กรุณาเลือกสถานะและตรวจสอบข้อมูลอีกครั้ง');
            } else {
                setError('บันทึกไม่สำเร็จ กรุณาลองใหม่');
            }
        } catch {
            setError('บันทึกไม่สำเร็จ กรุณาลองใหม่');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={`ครุภัณฑ์ ${asset.asset_code}`} />

            <header className="border-b px-4 py-4">
                <div className="mx-auto flex max-w-xl items-center gap-3">
                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                        <PackageSearch className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-lg font-bold">ตรวจสอบครุภัณฑ์</h1>
                        <p className="text-xs text-muted-foreground">
                            ระบบครุภัณฑ์ไอที — ไม่ต้องเข้าสู่ระบบ
                        </p>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-xl flex-1 space-y-4 px-4 py-6">
                {/* basic info */}
                <div className="rounded-xl border bg-card p-4">
                    {asset.photo_url && (
                        <img
                            src={asset.photo_url}
                            alt={asset.name}
                            className="mb-3 max-h-48 w-full rounded-lg border object-contain"
                        />
                    )}
                    <p className="font-mono text-sm font-semibold text-muted-foreground">
                        {asset.asset_code}
                    </p>
                    <h2 className="text-xl font-bold">{asset.name}</h2>
                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <Row label="หมวดหมู่" value={asset.category} />
                        <Row label="สถานะ" value={asset.status_label} />
                        <Row
                            label="ยี่ห้อ / รุ่น"
                            value={
                                [asset.brand, asset.model]
                                    .filter(Boolean)
                                    .join(' ') || null
                            }
                        />
                        <Row label="หน่วยงาน" value={asset.department} />
                        <Row label="สถานที่ติดตั้ง" value={asset.location} />
                        <Row
                            label="ตรวจสอบล่าสุด"
                            value={
                                asset.last_inspected_at
                                    ? prettyDateTime(asset.last_inspected_at)
                                    : 'ยังไม่เคยตรวจ'
                            }
                        />
                    </dl>
                </div>

                {done ? (
                    <div className="flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="font-semibold">
                                บันทึกผลการตรวจสอบเรียบร้อยแล้ว
                            </p>
                            <p>สถานะ: {done.status_label}</p>
                            <p>เวลา: {prettyDateTime(done.saved_at)}</p>
                            {done.inspector && (
                                <p>ผู้ตรวจสอบ: {done.inspector}</p>
                            )}
                        </div>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-xl border bg-card p-4"
                    >
                        <div>
                            <p className="mb-2 text-sm font-medium">
                                ยืนยันสถานะครุภัณฑ์
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                {Object.entries(statuses).map(
                                    ([key, label]) => (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => setStatus(key)}
                                            className={`rounded-lg border p-3 text-left transition-colors ${
                                                status === key
                                                    ? 'border-primary bg-primary/10'
                                                    : 'hover:bg-muted'
                                            }`}
                                        >
                                            <span className="block text-sm font-semibold">
                                                {label}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {STATUS_HINT[key]}
                                            </span>
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="inspector"
                                className="text-sm font-medium"
                            >
                                ชื่อผู้ตรวจสอบ
                            </label>
                            <input
                                id="inspector"
                                value={inspectorName}
                                onChange={(e) =>
                                    setInspectorName(e.target.value)
                                }
                                placeholder="ชื่อ-นามสกุล"
                                className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="note"
                                className="text-sm font-medium"
                            >
                                หมายเหตุ
                            </label>
                            <textarea
                                id="note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                className="mt-1 flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </div>

                        <div>
                            <label
                                htmlFor="photos"
                                className="text-sm font-medium"
                            >
                                รูปถ่าย (ถ่ายจากกล้องได้)
                            </label>
                            <input
                                id="photos"
                                ref={photoRef}
                                type="file"
                                accept="image/*"
                                capture="environment"
                                multiple
                                onChange={(e) => setPhotos(e.target.files)}
                                className="mt-1 block w-full text-sm"
                            />
                        </div>

                        <button
                            type="button"
                            onClick={useMyLocation}
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground underline"
                        >
                            <MapPin className="h-3.5 w-3.5" />
                            {coords
                                ? `บันทึกพิกัดแล้ว (${coords.lat.toFixed(5)}, ${coords.lng.toFixed(5)})`
                                : 'แนบพิกัดที่ตั้งปัจจุบัน'}
                        </button>

                        {error && (
                            <p className="text-sm text-red-500">{error}</p>
                        )}

                        <button
                            type="submit"
                            disabled={!status || saving}
                            className="w-full rounded-md bg-primary py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                        >
                            {saving ? 'กำลังบันทึก…' : 'บันทึกผลการตรวจสอบ'}
                        </button>
                    </form>
                )}
            </main>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value || '—'}</dd>
        </div>
    );
}

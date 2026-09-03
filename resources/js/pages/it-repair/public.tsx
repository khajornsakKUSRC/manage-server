import { Head, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronDown,
    ClipboardList,
    Loader2,
    Lock,
    Search,
    Star as StarIcon,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { StarRating } from '@/components/star-rating';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface ServiceType {
    id: number;
    name: string;
    provider_name: string | null;
}

interface Criterion {
    id: number;
    name: string;
}

interface Props {
    serviceTypes: ServiceType[];
    criteria: Criterion[];
    submittedId: number | null;
    /**
     * When the page is opened via a "track your request" link (?t=token),
     * the matching request is resolved server-side and passed here so the
     * tracker shows it straight away — no email to type.
     */
    linkedRequest: TrackedRequest | null;
    trackToken: string | null;
}

interface FormState {
    recipient_email: string;
    full_name: string;
    contact_number: string;
    requested_at: string;
    service_type: string;
    details: string;
}

interface TrackedEvaluation {
    evaluated_at: string;
    comment: string | null;
    scores: Record<number, number>;
    average: number;
}

interface TrackedRequest {
    id: number;
    full_name: string;
    requested_at: string;
    service_type: string;
    provider_name: string | null;
    details: string;
    status: string;
    status_label: string;
    updated_at: string | null;
    evaluation: TrackedEvaluation | null;
}

function csrfToken(): string {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    in_progress:
        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    on_hold:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    resolved:
        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    closed: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

function nowLocalInput(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function prettyDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Public({
    serviceTypes,
    criteria,
    submittedId,
    linkedRequest,
    trackToken,
}: Props) {
    const page = usePage().props;
    const footer = (
        page.siteSettings as { footer_text?: string | null } | undefined
    )?.footer_text;

    const [view, setView] = useState<'submit' | 'track'>(
        submittedId || linkedRequest ? 'track' : 'submit',
    );

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title="IT Repair Request" />

            <header className="border-b px-4 py-5 sm:px-6 md:px-10">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-primary/10 p-2 text-primary">
                            <Wrench className="h-5 w-5" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold">
                                IT Repair Request
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                ระบบแจ้งซ่อมออนไลน์
                            </p>
                        </div>
                    </div>
                    <div className="flex w-full rounded-lg border p-1 sm:inline-flex sm:w-auto">
                        <button
                            type="button"
                            onClick={() => setView('submit')}
                            className={`flex-1 rounded-md px-4 py-1.5 text-sm font-medium whitespace-nowrap transition-colors sm:flex-none ${view === 'submit'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            แจ้งซ่อม
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('track')}
                            className={`flex-1 rounded-md px-4 py-1.5 text-sm font-medium whitespace-nowrap transition-colors sm:flex-none ${view === 'track'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            ตรวจสอบสถานะ
                        </button>
                    </div>
                </div>
            </header>

            <main className="flex-1 px-4 py-6 sm:px-6 sm:py-8 md:px-10">
                {view === 'submit' ? (
                    <SubmitForm
                        serviceTypes={serviceTypes}
                        submittedId={submittedId}
                        onTracked={() => setView('track')}
                    />
                ) : (
                    <TrackPanel
                        initialSubmittedId={submittedId}
                        criteria={criteria}
                        linkedRequest={linkedRequest}
                        linkToken={trackToken}
                    />
                )}
            </main>

            {footer && (
                <footer className="border-t px-6 py-4 text-center text-xs text-muted-foreground md:px-10">
                    {footer}
                </footer>
            )}
        </div>
    );
}

function SubmitForm({
    serviceTypes,
    submittedId,
    onTracked,
}: {
    serviceTypes: ServiceType[];
    submittedId: number | null;
    onTracked: () => void;
}) {
    const { data, setData, post, processing, errors, reset } =
        useForm<FormState>({
            recipient_email: '',
            full_name: '',
            contact_number: '',
            requested_at: nowLocalInput(),
            service_type: serviceTypes[0]?.name ?? '',
            details: '',
        });

    const assignedTo =
        serviceTypes.find((t) => t.name === data.service_type)?.provider_name ??
        null;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/it-repair/new', {
            preserveScroll: true,
            onSuccess: () =>
                reset(
                    'recipient_email',
                    'full_name',
                    'contact_number',
                    'details',
                ),
        });
    };

    return (
        <form onSubmit={submit} className="w-full space-y-6">
            {submittedId && (
                <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300">
                    <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p className="font-medium">
                            ส่งรายการแจ้งซ่อมแล้ว เลขที่แจ้งซ่อมของคุณคือ #
                            {submittedId}.
                        </p>
                        <p>
                            ทีม IT จะดำเนินการตรวจสอบและอัปเดตสถานะ คุณสามารถ{' '}
                            <button
                                type="button"
                                onClick={onTracked}
                                className="font-medium underline underline-offset-2"
                            >
                                ตรวจสอบสถานะ
                            </button>{' '}
                            หรือส่งรายการแจ้งซ่อมใหม่อีกครั้งด้านล่าง
                        </p>
                    </div>
                </div>
            )}

            <div className="grid gap-5 rounded-xl border bg-card p-6 md:grid-cols-2 lg:grid-cols-3">
                <div className="space-y-2 md:col-span-2 lg:col-span-1">
                    <Label htmlFor="email">Email (@ku.th)</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.recipient_email}
                        onChange={(e) =>
                            setData('recipient_email', e.target.value)
                        }
                        required
                    />
                    {errors.recipient_email && (
                        <p className="text-sm text-red-500">
                            {errors.recipient_email}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="name">ชื่อ - นามสกุล</Label>
                    <Input
                        id="name"
                        value={data.full_name}
                        onChange={(e) => setData('full_name', e.target.value)}
                        required
                    />
                    {errors.full_name && (
                        <p className="text-sm text-red-500">
                            {errors.full_name}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="contact">เบอร์ติดต่อ </Label>
                    <Input
                        id="contact"
                        placeholder="เบอร์มือถือ หรือ เบอร์ภายใน 666XXX"
                        value={data.contact_number}
                        onChange={(e) =>
                            setData('contact_number', e.target.value)
                        }
                        required
                    />
                    {errors.contact_number && (
                        <p className="text-sm text-red-500">
                            {errors.contact_number}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="when">วันและเวลาที่แจ้งซ่อม</Label>
                    <Input
                        id="when"
                        type="datetime-local"
                        value={data.requested_at}
                        onChange={(e) =>
                            setData('requested_at', e.target.value)
                        }
                        required
                    />
                    {errors.requested_at && (
                        <p className="text-sm text-red-500">
                            {errors.requested_at}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="type">ประเภทงาน </Label>
                    <Select
                        value={data.service_type}
                        onValueChange={(v) => setData('service_type', v)}
                    >
                        <SelectTrigger id="type" className="w-full">
                            <SelectValue placeholder="Select a type" />
                        </SelectTrigger>
                        <SelectContent>
                            {serviceTypes.map((t) => (
                                <SelectItem key={t.id} value={t.name}>
                                    {t.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {assignedTo && (
                        <p className="text-xs text-muted-foreground">
                            ผู้รับผิดชอบ{' '}
                            <span className="font-medium text-foreground">
                                {assignedTo}
                            </span>
                            .
                        </p>
                    )}
                    {errors.service_type && (
                        <p className="text-sm text-red-500">
                            {errors.service_type}
                        </p>
                    )}
                </div>

                <div className="space-y-2 md:col-span-2 lg:col-span-3">
                    <Label htmlFor="details">รายละเอียด</Label>
                    <textarea
                        id="details"
                        className="flex min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="แจ้งรายละเอียดของปัญหา, สถานที่, อุปกรณ์"
                        value={data.details}
                        onChange={(e) => setData('details', e.target.value)}
                        required
                    />
                    {errors.details && (
                        <p className="text-sm text-red-500">{errors.details}</p>
                    )}
                </div>
            </div>

            <div className="flex justify-end">
                <Button type="submit" size="lg" disabled={processing}>
                    {processing ? 'Submitting…' : 'Submit Request'}
                </Button>
            </div>
        </form>
    );
}

function TrackPanel({
    initialSubmittedId,
    criteria,
    linkedRequest,
    linkToken,
}: {
    initialSubmittedId: number | null;
    criteria: Criterion[];
    linkedRequest: TrackedRequest | null;
    linkToken: string | null;
}) {
    // The request's own secret from the tracking link (?t=...). While it's
    // set, the tracker acts on that one request without an email.
    const token = linkToken ?? '';
    const [email, setEmail] = useState('');
    const [searchedEmail, setSearchedEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [results, setResults] = useState<TrackedRequest[] | null>(
        linkedRequest ? [linkedRequest] : null,
    );
    const [expanded, setExpanded] = useState<Record<number, boolean>>(
        linkedRequest ? { [linkedRequest.id]: true } : {},
    );

    const runSearch = async (by: { email?: string; token?: string }) => {
        setLoading(true);
        setError(null);

        const query = by.token
            ? `token=${encodeURIComponent(by.token)}`
            : `email=${encodeURIComponent(by.email ?? '')}`;

        try {
            const res = await fetch(`/it-repair/track?${query}`, {
                headers: { Accept: 'application/json' },
            });

            if (res.status === 422) {
                setError('กรุณากรอกอีเมลให้ถูกต้อง');
                setResults(null);

                return;
            }

            if (!res.ok) {
                throw new Error('request failed');
            }

            const json = await res.json();
            setResults(json.data ?? []);

            if (by.email) {
                setSearchedEmail(by.email);
            }
        } catch {
            setError('ไม่สามารถค้นหาได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
            setResults(null);
        } finally {
            setLoading(false);
        }
    };

    const search = (e?: FormEvent) => {
        e?.preventDefault();
        const value = email.trim();

        if (value) {
            void runSearch({ email: value });
        }
    };

    // After a rating is saved, re-pull the same view — by link token if we
    // came in via the link, otherwise by the email that was searched.
    const refresh = () => {
        if (token) {
            void runSearch({ token });
        } else if (searchedEmail) {
            void runSearch({ email: searchedEmail });
        }
    };

    return (
        <div className="w-full space-y-6">
            <div className="rounded-xl border bg-card p-6">
                <h2 className="text-lg font-semibold">
                    ติดตามการแจ้งซ่อมและประเมินผล
                </h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    {token
                        ? 'กำลังแสดงรายการแจ้งซ่อมจากลิงก์ของคุณด้านล่าง — หรือค้นหารายการอื่นด้วยอีเมลที่ใช้แจ้งซ่อม'
                        : 'กรุณาใส่ Email address ที่ใช้ในการแจ้งซ่อม'}
                    {!token && initialSubmittedId
                        ? ` (คุณได้ทำการแจ้งซ่อมแล้วหมายเลขอ้างอิง #${initialSubmittedId})`
                        : ''}
                </p>
                <form
                    onSubmit={search}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        type="email"
                        placeholder="Email@ku.th"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="sm:max-w-sm"
                    />
                    <Button type="submit" disabled={loading || !email.trim()}>
                        {loading ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Search className="mr-2 h-4 w-4" />
                        )}
                        ค้นหา
                    </Button>
                </form>
                {error && <p className="mt-2 text-sm text-red-500">{error}</p>}
            </div>

            {results !== null && (
                <div className="overflow-hidden rounded-xl border bg-card">
                    {results.length === 0 ? (
                        <div className="flex items-center gap-3 p-6 text-sm text-muted-foreground">
                            <ClipboardList className="h-5 w-5 shrink-0" />
                            ไม่พบรายการแจ้งซ่อมที่ตรงกับ Email
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {results.map((r) => {
                                const open = expanded[r.id] ?? false;
                                const canRate =
                                    r.status === 'resolved' ||
                                    r.status === 'closed';

                                return (
                                    <li key={r.id} className="p-4 sm:p-5">
                                        <button
                                            type="button"
                                            aria-expanded={open}
                                            onClick={() =>
                                                setExpanded((p) => ({
                                                    ...p,
                                                    [r.id]: !open,
                                                }))
                                            }
                                            className="flex w-full items-start justify-between gap-3 text-left"
                                        >
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-sm font-medium">
                                                        #{r.id}
                                                    </span>
                                                    <span
                                                        className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[
                                                            r.status
                                                            ] ??
                                                            STATUS_STYLES.pending
                                                            }`}
                                                    >
                                                        {r.status_label}
                                                    </span>
                                                </div>
                                                <p className="font-medium break-words">
                                                    {r.service_type}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {r.provider_name ?? '—'}
                                                </p>
                                            </div>
                                            <ChevronDown
                                                className={`mt-0.5 h-4 w-4 shrink-0 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''
                                                    }`}
                                            />
                                        </button>

                                        <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                                            <div className="min-w-0">
                                                <dt className="text-xs text-muted-foreground">
                                                    Requested
                                                </dt>
                                                <dd>
                                                    {prettyDateTime(
                                                        r.requested_at,
                                                    )}
                                                </dd>
                                            </div>
                                            <div className="min-w-0">
                                                <dt className="text-xs text-muted-foreground">
                                                    Last updated
                                                </dt>
                                                <dd className="text-muted-foreground">
                                                    {prettyDateTime(
                                                        r.updated_at,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>

                                        {open && (
                                            <div className="mt-3 rounded-lg bg-muted/40 p-3">
                                                <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                                    Details
                                                </p>
                                                <p className="text-sm break-words whitespace-pre-wrap">
                                                    {r.details}
                                                </p>
                                            </div>
                                        )}

                                        {canRate && (
                                            <div className="mt-3 rounded-lg border border-green-200 bg-green-50/60 p-3 dark:border-green-900/40 dark:bg-green-900/10">
                                                <PublicEvaluation
                                                    request={r}
                                                    criteria={criteria}
                                                    email={searchedEmail}
                                                    token={token}
                                                    onSaved={refresh}
                                                />
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}

function PublicEvaluation({
    request,
    criteria,
    email,
    token,
    onSaved,
}: {
    request: TrackedRequest;
    criteria: Criterion[];
    email: string;
    /** Link token — used in place of the email when the page was opened via
     * the tracking link. */
    token: string;
    onSaved: () => void;
}) {
    const existing = request.evaluation;
    const isClosed = request.status === 'closed';
    const [editing, setEditing] = useState(!existing);
    const [scores, setScores] = useState<Record<number, number>>({
        ...(existing?.scores ?? {}),
    });
    const [comment, setComment] = useState(existing?.comment ?? '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (isClosed) {
        return (
            <div className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-semibold text-teal-700 dark:text-teal-300">
                    <Lock className="h-4 w-4" />
                    ปิดงานแล้ว
                    {existing
                        ? ` — คะแนนการให้บริการ ${existing.average.toFixed(1)}/5`
                        : '.'}
                </p>
                {existing && (
                    <div className="flex max-w-md flex-col gap-1">
                        {criteria.map((c) => (
                            <div
                                key={c.id}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span className="text-muted-foreground">
                                    {c.name}
                                </span>
                                <StarRating
                                    value={existing.scores[c.id] ?? 0}
                                    readOnly
                                    size={14}
                                />
                            </div>
                        ))}
                    </div>
                )}
                {existing?.comment && (
                    <p className="text-sm text-muted-foreground">
                        “{existing.comment}”
                    </p>
                )}
            </div>
        );
    }

    if (criteria.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                This request is resolved. A rating form will appear here once
                evaluation criteria are set up.
            </p>
        );
    }

    if (existing && !editing) {
        return (
            <div className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                    <StarIcon className="h-4 w-4 fill-current" />
                    ขอบคุณที่ให้คะแนนบริการ {existing.average.toFixed(1)}/5.
                </p>
                <div className="grid gap-1 sm:grid-cols-2">
                    {criteria.map((c) => (
                        <div
                            key={c.id}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span className="text-muted-foreground">
                                {c.name}
                            </span>
                            <StarRating
                                value={existing.scores[c.id] ?? 0}
                                readOnly
                                size={14}
                            />
                        </div>
                    ))}
                </div>
                {existing.comment && (
                    <p className="text-sm text-muted-foreground">
                        “{existing.comment}”
                    </p>
                )}
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setEditing(true)}
                >
                    แก้ไขคะแนน
                </Button>
            </div>
        );
    }

    const allScored = criteria.every((c) => (scores[c.id] ?? 0) >= 1);

    const submit = async () => {
        setSaving(true);
        setError(null);

        try {
            const res = await fetch(
                `/it-repair/track/${request.id}/evaluation`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        scores,
                        comment,
                        ...(token ? { token } : { email }),
                    }),
                },
            );

            if (res.ok) {
                onSaved();

                return;
            }

            if (res.status === 403) {
                setError('This link or email does not match this request.');
            } else if (res.status === 422) {
                setError('Please give every item a star rating.');
            } else {
                setError('Could not save your rating. Try again.');
            }
        } catch {
            setError('Could not save your rating. Try again.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3">
            <p className="text-sm font-medium">
                Rate the service on request #{request.id}
            </p>
            <div className="flex max-w-md flex-col gap-2">
                {criteria.map((c) => (
                    <div
                        key={c.id}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-sm">{c.name}</span>
                        <StarRating
                            value={scores[c.id] ?? 0}
                            onChange={(v) =>
                                setScores((p) => ({ ...p, [c.id]: v }))
                            }
                        />
                    </div>
                ))}
            </div>
            <textarea
                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                placeholder="Any comments for the team? (optional)"
                value={comment}
                onChange={(e) => setComment(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">
                5 ดาว = มากสุด, 1 ดาว = น้อยสุด
            </p>
            {error && <p className="text-sm text-red-500">{error}</p>}
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={submit}
                    disabled={saving || !allScored}
                >
                    {saving ? 'Saving…' : 'ส่ง'}
                </Button>
                {existing && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setEditing(false)}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </div>
    );
}

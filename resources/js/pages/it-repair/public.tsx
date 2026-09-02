import { Head, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardList,
    Loader2,
    Lock,
    Search,
    Star as StarIcon,
    Wrench,
} from 'lucide-react';
import { Fragment, useState } from 'react';
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
    closed:
        'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
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

export default function Public({ serviceTypes, criteria, submittedId }: Props) {
    const page = usePage().props;
    const appName = (page.name as string) ?? 'IT Repair';
    const footer = (
        page.siteSettings as { footer_text?: string | null } | undefined
    )?.footer_text;

    const [view, setView] = useState<'submit' | 'track'>(
        submittedId ? 'track' : 'submit',
    );

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title="IT Repair Request" />

            <header className="border-b px-6 py-5 md:px-10">
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
                                {appName} — no sign-in required.
                            </p>
                        </div>
                    </div>
                    <div className="inline-flex rounded-lg border p-1">
                        <button
                            type="button"
                            onClick={() => setView('submit')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${view === 'submit'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            Submit a request
                        </button>
                        <button
                            type="button"
                            onClick={() => setView('track')}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${view === 'track'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            Track my requests
                        </button>
                    </div>
                </div>
            </header>

            <main className="flex-1 px-6 py-8 md:px-10">
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
                            Request submitted — reference #{submittedId}.
                        </p>
                        <p>
                            The IT team will review it and update the status.
                            You can{' '}
                            <button
                                type="button"
                                onClick={onTracked}
                                className="font-medium underline underline-offset-2"
                            >
                                track it here
                            </button>{' '}
                            or submit another below.
                        </p>
                    </div>
                </div>
            )}

            <div className="grid gap-5 rounded-xl border bg-card p-6 md:grid-cols-2 lg:grid-cols-3">
                <div className="space-y-2 lg:col-span-1 md:col-span-2">
                    <Label htmlFor="email">Service recipient (email)</Label>
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
                    <Label htmlFor="name">Full name</Label>
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
                    <Label htmlFor="contact">Contact number</Label>
                    <Input
                        id="contact"
                        placeholder="phone or internal ext, e.g. 666710"
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
                    <Label htmlFor="when">Date &amp; time of request</Label>
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
                    <Label htmlFor="type">Service type</Label>
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
                            Handled by{' '}
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
                    <Label htmlFor="details">Details</Label>
                    <textarea
                        id="details"
                        className="flex min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="Describe the problem, location, device, etc."
                        value={data.details}
                        onChange={(e) => setData('details', e.target.value)}
                        required
                    />
                    {errors.details && (
                        <p className="text-sm text-red-500">
                            {errors.details}
                        </p>
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
}: {
    initialSubmittedId: number | null;
    criteria: Criterion[];
}) {
    const [email, setEmail] = useState('');
    const [searchedEmail, setSearchedEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [results, setResults] = useState<TrackedRequest[] | null>(null);
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});

    const runSearch = async (value: string) => {
        setLoading(true);
        setError(null);

        try {
            const res = await fetch(
                `/it-repair/track?email=${encodeURIComponent(value)}`,
                { headers: { Accept: 'application/json' } },
            );

            if (res.status === 422) {
                setError('Please enter a valid email address.');
                setResults(null);

                return;
            }

            if (!res.ok) {
                throw new Error('request failed');
            }

            const json = await res.json();
            setResults(json.data ?? []);
            setSearchedEmail(value);
        } catch {
            setError('Could not look up requests right now. Try again.');
            setResults(null);
        } finally {
            setLoading(false);
        }
    };

    const search = (e?: FormEvent) => {
        e?.preventDefault();
        const value = email.trim();

        if (value) {
            void runSearch(value);
        }
    };

    const refresh = () => {
        if (searchedEmail) {
            void runSearch(searchedEmail);
        }
    };

    return (
        <div className="w-full space-y-6">
            <div className="rounded-xl border bg-card p-6">
                <h2 className="text-lg font-semibold">
                    Track your repair requests
                </h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    Enter the email address you filed the request with
                    {initialSubmittedId
                        ? ` (you just submitted reference #${initialSubmittedId})`
                        : ''}
                    .
                </p>
                <form
                    onSubmit={search}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        type="email"
                        placeholder="you@example.com"
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
                        Search
                    </Button>
                </form>
                {error && (
                    <p className="mt-2 text-sm text-red-500">{error}</p>
                )}
            </div>

            {results !== null && (
                <div className="rounded-xl border bg-card">
                    {results.length === 0 ? (
                        <div className="flex items-center gap-3 p-6 text-sm text-muted-foreground">
                            <ClipboardList className="h-5 w-5" />
                            No repair requests found for that email address.
                        </div>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Ref
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Requested
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Type
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Provider
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Last updated
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {results.map((r) => {
                                    const open = expanded[r.id] ?? false;

                                    return (
                                        <Fragment key={r.id}>
                                            <tr
                                                className="cursor-pointer align-top hover:bg-muted/30"
                                                onClick={() =>
                                                    setExpanded((p) => ({
                                                        ...p,
                                                        [r.id]: !open,
                                                    }))
                                                }
                                            >
                                                <td className="px-4 py-3 font-mono">
                                                    #{r.id}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {prettyDateTime(
                                                        r.requested_at,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {r.service_type}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {r.provider_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[
                                                            r.status
                                                            ] ??
                                                            STATUS_STYLES.pending
                                                            }`}
                                                    >
                                                        {r.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                    {prettyDateTime(
                                                        r.updated_at,
                                                    )}
                                                </td>
                                            </tr>
                                            {open && (
                                                <tr className="bg-muted/20">
                                                    <td
                                                        colSpan={6}
                                                        className="px-4 py-3"
                                                    >
                                                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                                            Details
                                                        </p>
                                                        <p className="whitespace-pre-wrap text-sm">
                                                            {r.details}
                                                        </p>
                                                    </td>
                                                </tr>
                                            )}
                                            {(r.status === 'resolved' ||
                                                r.status === 'closed') && (
                                                    <tr className="bg-green-50/50 dark:bg-green-900/10">
                                                        <td
                                                            colSpan={6}
                                                            className="px-4 py-4"
                                                        >
                                                            <PublicEvaluation
                                                                request={r}
                                                                criteria={criteria}
                                                                email={
                                                                    searchedEmail
                                                                }
                                                                onSaved={refresh}
                                                            />
                                                        </td>
                                                    </tr>
                                                )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
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
    onSaved,
}: {
    request: TrackedRequest;
    criteria: Criterion[];
    email: string;
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
                    Job Closed
                    {existing
                        ? ` — you rated this ${existing.average.toFixed(1)}/5. The rating is final.`
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
                    Thanks — you rated this service{' '}
                    {existing.average.toFixed(1)}/5.
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
                {/* <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setEditing(true)}
                >
                    Change rating
                </Button> */}
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
                    body: JSON.stringify({ email, scores, comment }),
                },
            );

            if (res.ok) {
                onSaved();

                return;
            }

            if (res.status === 403) {
                setError(
                    'This request belongs to a different email address.',
                );
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
                5 stars = highest, 1 star = lowest.
            </p>
            {error && <p className="text-sm text-red-500">{error}</p>}
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={submit}
                    disabled={saving || !allScored}
                >
                    {saving ? 'Saving…' : 'Submit rating'}
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

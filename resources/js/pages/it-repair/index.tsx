import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    ExternalLink,
    Mail,
    Pencil,
    Plus,
    Star as StarIcon,
    Trash2,
    Wrench,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import type { FormEvent } from 'react';
import { StarRating } from '@/components/star-rating';
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

interface EvaluationData {
    evaluated_at: string;
    comment: string | null;
    scores: Record<number, number>;
    average: number;
}

interface RepairRow {
    id: number;
    recipient_email: string;
    full_name: string;
    contact_number: string;
    requested_at: string;
    service_type: string;
    provider_name: string | null;
    details: string;
    status: string;
    status_label: string;
    created_by: string | null;
    evaluation: EvaluationData | null;
}

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
    requests: RepairRow[];
    serviceTypes: ServiceType[];
    statuses: Record<string, string>;
    criteria: Criterion[];
    canManage: boolean;
}

interface FormState {
    recipient_email: string;
    full_name: string;
    contact_number: string;
    requested_at: string;
    service_type: string;
    provider_name: string;
    details: string;
    status: string;
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

function isoToLocalInput(iso: string): string {
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function prettyDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Index({
    requests,
    serviceTypes,
    statuses,
    criteria,
    canManage,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [expanded, setExpanded] = useState<Record<number, boolean>>({});

    const { data, setData, put, processing, errors, clearErrors } =
        useForm<FormState>({
            recipient_email: '',
            full_name: '',
            contact_number: '',
            requested_at: nowLocalInput(),
            service_type: serviceTypes[0]?.name ?? '',
            provider_name: serviceTypes[0]?.provider_name ?? '',
            details: '',
            status: 'pending',
        });

    // --- provider auto-fill: pick a service type, its assigned person
    // drops into the provider field (still editable afterwards).
    const applyServiceType = (name: string) => {
        const match = serviceTypes.find((t) => t.name === name);
        setData((prev) => ({
            ...prev,
            service_type: name,
            provider_name: match?.provider_name ?? '',
        }));
    };

    const setStatus = (row: RepairRow, status: string) => {
        router.patch(
            `/it-repair/${row.id}/status`,
            { status },
            { preserveScroll: true },
        );
    };

    const openEdit = (row: RepairRow) => {
        setEditingId(row.id);
        clearErrors();
        setData({
            recipient_email: row.recipient_email,
            full_name: row.full_name,
            contact_number: row.contact_number,
            requested_at: isoToLocalInput(row.requested_at),
            service_type: row.service_type,
            provider_name: row.provider_name ?? '',
            details: row.details,
            status: row.status,
        });
        setDialogOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (!editingId) {
            return;
        }

        put(`/it-repair/${editingId}`, {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    };

    const remove = (row: RepairRow) => {
        if (window.confirm(`ลบคำขอแจ้งซ่อมของ "${row.full_name}" ใช่หรือไม่?`)) {
            router.delete(`/it-repair/${row.id}`, { preserveScroll: true });
        }
    };

    const [sendingEmailId, setSendingEmailId] = useState<number | null>(null);

    const sendEmail = (row: RepairRow) => {
        setSendingEmailId(row.id);
        router.post(
            `/it-repair/${row.id}/send-email`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSendingEmailId(null),
            },
        );
    };

    // --- evaluation dialog ---------------------------------------------
    const [evalRow, setEvalRow] = useState<RepairRow | null>(null);
    const [evalScores, setEvalScores] = useState<Record<number, number>>({});
    const [evalComment, setEvalComment] = useState('');
    const [evalSaving, setEvalSaving] = useState(false);

    const openEvaluate = (row: RepairRow) => {
        setEvalRow(row);
        setEvalScores({ ...(row.evaluation?.scores ?? {}) });
        setEvalComment(row.evaluation?.comment ?? '');
    };

    const submitEvaluation = () => {
        if (!evalRow) {
            return;
        }

        setEvalSaving(true);
        router.post(
            `/it-repair/${evalRow.id}/evaluation`,
            { scores: evalScores, comment: evalComment },
            {
                preserveScroll: true,
                onSuccess: () => setEvalRow(null),
                onFinish: () => setEvalSaving(false),
            },
        );
    };

    const allCriteriaScored =
        criteria.length > 0 &&
        criteria.every((c) => (evalScores[c.id] ?? 0) >= 1);

    return (
        <>
            <Head title="IT Repair" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">IT Repair</h1>
                        <p className="text-sm text-muted-foreground">
                            Requests come in through the public form. Set
                            each one&apos;s status here as it&apos;s worked.
                        </p>
                    </div>
                    <Button size="sm" variant="outline" asChild>
                        <a
                            href="/it-repair/new"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="mr-2 h-4 w-4" />
                            คลิ๊กเพื่อเปิดฟอร์มแจ้งซ่อม
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Requested
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Recipient
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        Contact
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
                                        Rating
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {requests.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-10 text-center text-muted-foreground"
                                        >
                                            <Wrench className="mx-auto mb-2 h-5 w-5" />
                                            No repair requests yet.
                                        </td>
                                    </tr>
                                )}
                                {requests.map((row) => {
                                    const open = expanded[row.id] ?? false;

                                    return (
                                        <Fragment key={row.id}>
                                            <tr>
                                                <td className="px-4 py-3 align-top whitespace-nowrap">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setExpanded(
                                                                (p) => ({
                                                                    ...p,
                                                                    [row.id]:
                                                                        !open,
                                                                }),
                                                            )
                                                        }
                                                        className="flex items-center gap-1 text-left hover:underline"
                                                    >
                                                        {open ? (
                                                            <ChevronDown className="h-3 w-3" />
                                                        ) : (
                                                            <ChevronRight className="h-3 w-3" />
                                                        )}
                                                        {prettyDateTime(
                                                            row.requested_at,
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <div className="font-medium">
                                                        {row.full_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {row.recipient_email}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 align-top font-mono text-xs">
                                                    {row.contact_number}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    {row.service_type}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    {row.provider_name ?? (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    <Select
                                                        value={row.status}
                                                        onValueChange={(v) =>
                                                            setStatus(row, v)
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className={`h-7 w-36 border-0 text-xs font-medium ${STATUS_STYLES[
                                                                row.status
                                                            ] ??
                                                                STATUS_STYLES.pending
                                                                }`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {Object.entries(
                                                                statuses,
                                                            ).map(
                                                                ([
                                                                    value,
                                                                    label,
                                                                ]) => (
                                                                    <SelectItem
                                                                        key={
                                                                            value
                                                                        }
                                                                        value={
                                                                            value
                                                                        }
                                                                    >
                                                                        {label}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="px-4 py-3 align-top">
                                                    {row.evaluation ? (
                                                        <span className="flex items-center gap-1 whitespace-nowrap">
                                                            <StarRating
                                                                value={
                                                                    row
                                                                        .evaluation
                                                                        .average
                                                                }
                                                                readOnly
                                                                size={14}
                                                            />
                                                            <span className="text-xs text-muted-foreground">
                                                                {row.evaluation.average.toFixed(
                                                                    1,
                                                                )}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            not rated
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right align-top whitespace-nowrap">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            openEvaluate(row)
                                                        }
                                                        disabled={
                                                            criteria.length ===
                                                            0
                                                        }
                                                        title={
                                                            criteria.length ===
                                                                0
                                                                ? 'No active criteria — add them on the Service Evaluation page'
                                                                : undefined
                                                        }
                                                    >
                                                        <StarIcon className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        title={`ส่งอีเมลแจ้งเตือนไปที่ ${row.recipient_email}`}
                                                        disabled={
                                                            sendingEmailId ===
                                                            row.id
                                                        }
                                                        onClick={() =>
                                                            sendEmail(row)
                                                        }
                                                    >
                                                        <Mail className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            openEdit(row)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            remove(row)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                </td>
                                            </tr>
                                            {open && (
                                                <tr className="bg-muted/30">
                                                    <td
                                                        colSpan={8}
                                                        className="px-4 py-3"
                                                    >
                                                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                                            Details
                                                        </p>
                                                        <p className="whitespace-pre-wrap">
                                                            {row.details}
                                                        </p>
                                                        {row.evaluation
                                                            ?.comment && (
                                                                <p className="mt-2 text-sm text-muted-foreground">
                                                                    <span className="font-medium">
                                                                        Feedback:
                                                                    </span>{' '}
                                                                    {
                                                                        row
                                                                            .evaluation
                                                                            .comment
                                                                    }
                                                                </p>
                                                            )}
                                                        {row.created_by && (
                                                            <p className="mt-2 text-xs text-muted-foreground">
                                                                Logged by{' '}
                                                                {row.created_by}
                                                            </p>
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {canManage && (
                    <ServiceTypesPanel serviceTypes={serviceTypes} />
                )}
            </div>

            {/* Edit request */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Edit Repair Request</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-4 py-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="r-email">
                                    Service recipient (email)
                                </Label>
                                <Input
                                    id="r-email"
                                    type="email"
                                    value={data.recipient_email}
                                    onChange={(e) =>
                                        setData(
                                            'recipient_email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.recipient_email && (
                                    <p className="text-sm text-red-500">
                                        {errors.recipient_email}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-name">Full name</Label>
                                <Input
                                    id="r-name"
                                    value={data.full_name}
                                    onChange={(e) =>
                                        setData('full_name', e.target.value)
                                    }
                                />
                                {errors.full_name && (
                                    <p className="text-sm text-red-500">
                                        {errors.full_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-contact">
                                    Contact number
                                </Label>
                                <Input
                                    id="r-contact"
                                    placeholder="phone or internal ext, e.g. 666710"
                                    value={data.contact_number}
                                    onChange={(e) =>
                                        setData(
                                            'contact_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.contact_number && (
                                    <p className="text-sm text-red-500">
                                        {errors.contact_number}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-when">
                                    Date &amp; time of request
                                </Label>
                                <Input
                                    id="r-when"
                                    type="datetime-local"
                                    value={data.requested_at}
                                    onChange={(e) =>
                                        setData('requested_at', e.target.value)
                                    }
                                />
                                {errors.requested_at && (
                                    <p className="text-sm text-red-500">
                                        {errors.requested_at}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-type">Service type</Label>
                                <Select
                                    value={data.service_type}
                                    onValueChange={applyServiceType}
                                >
                                    <SelectTrigger
                                        id="r-type"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {serviceTypes.map((t) => (
                                            <SelectItem
                                                key={t.id}
                                                value={t.name}
                                            >
                                                {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.service_type && (
                                    <p className="text-sm text-red-500">
                                        {errors.service_type}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-provider">
                                    Service provider
                                </Label>
                                <Input
                                    id="r-provider"
                                    placeholder="auto-filled from service type"
                                    value={data.provider_name}
                                    onChange={(e) =>
                                        setData('provider_name', e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Person performing the repair — pre-filled
                                    from the type, editable.
                                </p>
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="r-details">Details</Label>
                                <textarea
                                    id="r-details"
                                    className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    value={data.details}
                                    onChange={(e) =>
                                        setData('details', e.target.value)
                                    }
                                />
                                {errors.details && (
                                    <p className="text-sm text-red-500">
                                        {errors.details}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="r-status">Repair status</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(v) => setData('status', v)}
                                >
                                    <SelectTrigger
                                        id="r-status"
                                        className="w-full"
                                    >
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
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Evaluate */}
            <Dialog
                open={evalRow !== null}
                onOpenChange={(o) => !o && setEvalRow(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Service Evaluation
                            {evalRow ? ` — ${evalRow.full_name}` : ''}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        {criteria.map((c) => (
                            <div
                                key={c.id}
                                className="flex items-center justify-between gap-4"
                            >
                                <span className="text-sm">{c.name}</span>
                                <StarRating
                                    value={evalScores[c.id] ?? 0}
                                    onChange={(v) =>
                                        setEvalScores((p) => ({
                                            ...p,
                                            [c.id]: v,
                                        }))
                                    }
                                />
                            </div>
                        ))}
                        <div className="space-y-2">
                            <Label htmlFor="eval-comment">
                                Comment (optional)
                            </Label>
                            <textarea
                                id="eval-comment"
                                className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={evalComment}
                                onChange={(e) =>
                                    setEvalComment(e.target.value)
                                }
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            5 stars = highest, 1 star = lowest.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={submitEvaluation}
                            disabled={evalSaving || !allCriteriaScored}
                        >
                            Save Evaluation
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ServiceTypesPanel({
    serviceTypes,
}: {
    serviceTypes: ServiceType[];
}) {
    const [name, setName] = useState('');
    const [provider, setProvider] = useState('');
    const [editing, setEditing] = useState<Record<number, ServiceType>>({});

    const add = () => {
        if (!name.trim()) {
            return;
        }

        router.post(
            '/it-repair/service-types',
            { name, provider_name: provider },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setName('');
                    setProvider('');
                },
            },
        );
    };

    const save = (t: ServiceType) => {
        const draft = editing[t.id];
        router.put(
            `/it-repair/service-types/${t.id}`,
            { name: draft.name, provider_name: draft.provider_name ?? '' },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setEditing((p) => {
                        const next = { ...p };
                        delete next[t.id];

                        return next;
                    }),
            },
        );
    };

    const remove = (t: ServiceType) => {
        if (window.confirm(`ลบประเภทงาน "${t.name}" ใช่หรือไม่?`)) {
            router.delete(`/it-repair/service-types/${t.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    Service types &amp; providers
                </CardTitle>
                <p className="text-xs text-muted-foreground">
                    Admin only. The provider set here is what auto-fills on
                    the request form when its type is chosen.
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                <ul className="divide-y rounded-md border">
                    {serviceTypes.map((t) => {
                        const draft = editing[t.id];

                        return (
                            <li
                                key={t.id}
                                className="flex flex-wrap items-center gap-2 px-3 py-2"
                            >
                                {draft ? (
                                    <>
                                        <Input
                                            className="h-8 w-40"
                                            value={draft.name}
                                            onChange={(e) =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [t.id]: {
                                                        ...draft,
                                                        name: e.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                        <Input
                                            className="h-8 w-48"
                                            placeholder="provider name"
                                            value={draft.provider_name ?? ''}
                                            onChange={(e) =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [t.id]: {
                                                        ...draft,
                                                        provider_name:
                                                            e.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                        <Button
                                            size="sm"
                                            onClick={() => save(t)}
                                        >
                                            Save
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setEditing((p) => {
                                                    const next = { ...p };
                                                    delete next[t.id];

                                                    return next;
                                                })
                                            }
                                        >
                                            Cancel
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <span className="w-40 font-medium">
                                            {t.name}
                                        </span>
                                        <span className="w-48 text-sm text-muted-foreground">
                                            {t.provider_name || 'no provider'}
                                        </span>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [t.id]: { ...t },
                                                }))
                                            }
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => remove(t)}
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </>
                                )}
                            </li>
                        );
                    })}
                </ul>
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        className="h-8 w-40"
                        placeholder="new type"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <Input
                        className="h-8 w-48"
                        placeholder="provider name"
                        value={provider}
                        onChange={(e) => setProvider(e.target.value)}
                    />
                    <Button size="sm" variant="outline" onClick={add}>
                        <Plus className="mr-1 h-4 w-4" />
                        Add
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

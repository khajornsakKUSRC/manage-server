import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Clock,
    Pencil,
    Plus,
    Trash2,
    User as UserIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

interface NoticeRow {
    id: number;
    title: string;
    message: string;
    notice_date: string;
    remind_at: string | null;
    reminded_at: string | null;
    type: string;
    type_label: string;
    created_by: string | null;
    created_at: string | null;
}

interface Props {
    notices: NoticeRow[];
    types: Record<string, string>;
}

interface FormState {
    title: string;
    message: string;
    notice_date: string;
    remind_at: string;
    type: string;
}

const TYPE_STYLES: Record<string, { dot: string; badge: string }> = {
    server: {
        dot: 'bg-blue-500',
        badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    },
    network: {
        dot: 'bg-violet-500',
        badge: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    },
    website: {
        dot: 'bg-amber-500',
        badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    },
};

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function ymd(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function parseYmd(value: string): Date {
    const [y, m, d] = value.split('-').map(Number);

    return new Date(y, m - 1, d);
}

function prettyDate(value: string): string {
    return parseYmd(value).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
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

// ISO (with offset) -> the "YYYY-MM-DDTHH:MM" a datetime-local input wants,
// in the viewer's local time.
function isoToLocalInput(iso: string): string {
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function typeStyle(type: string) {
    return (
        TYPE_STYLES[type] ?? {
            dot: 'bg-gray-400',
            badge: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        }
    );
}

const TODAY = ymd(new Date());

export default function Index({ notices, types }: Props) {
    const currentUser = usePage().props.auth.user;

    const [viewMonth, setViewMonth] = useState(() => {
        const now = new Date();

        return new Date(now.getFullYear(), now.getMonth(), 1);
    });
    const [selectedDate, setSelectedDate] = useState<string | null>(TODAY);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormState>({
            title: '',
            message: '',
            notice_date: TODAY,
            remind_at: '',
            type: 'server',
        });

    const noticesByDate = useMemo(() => {
        const map = new Map<string, NoticeRow[]>();

        for (const notice of notices) {
            const list = map.get(notice.notice_date) ?? [];
            list.push(notice);
            map.set(notice.notice_date, list);
        }

        return map;
    }, [notices]);

    // 6 weeks × 7 days, starting from the Sunday on or before the 1st.
    const cells = useMemo(() => {
        const first = new Date(
            viewMonth.getFullYear(),
            viewMonth.getMonth(),
            1,
        );
        const offset = first.getDay();

        return Array.from({ length: 42 }, (_, i) => {
            const date = new Date(
                first.getFullYear(),
                first.getMonth(),
                1 - offset + i,
            );

            return {
                key: ymd(date),
                day: date.getDate(),
                inMonth: date.getMonth() === viewMonth.getMonth(),
            };
        });
    }, [viewMonth]);

    const selectedNotices = selectedDate
        ? (noticesByDate.get(selectedDate) ?? [])
        : [];

    const upcoming = useMemo(
        () => notices.filter((n) => n.notice_date >= TODAY).slice(0, 12),
        [notices],
    );

    const shiftMonth = (delta: number) => {
        setViewMonth(
            (prev) =>
                new Date(prev.getFullYear(), prev.getMonth() + delta, 1),
        );
    };

    const goToday = () => {
        const now = new Date();
        setViewMonth(new Date(now.getFullYear(), now.getMonth(), 1));
        setSelectedDate(TODAY);
    };

    const openCreate = (date?: string) => {
        const noticeDate = date ?? selectedDate ?? TODAY;
        setEditingId(null);
        clearErrors();
        reset();
        setData({
            title: '',
            message: '',
            notice_date: noticeDate,
            remind_at: '',
            type: 'server',
        });
        setDialogOpen(true);
    };

    const openEdit = (notice: NoticeRow) => {
        setEditingId(notice.id);
        clearErrors();
        setData({
            title: notice.title,
            message: notice.message,
            notice_date: notice.notice_date,
            remind_at: notice.remind_at
                ? isoToLocalInput(notice.remind_at)
                : '',
            type: notice.type,
        });
        setDialogOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        };

        if (editingId) {
            put(`/calendar-notice/${editingId}`, options);
        } else {
            post('/calendar-notice', options);
        }
    };

    const handleDelete = (notice: NoticeRow) => {
        if (window.confirm(`ลบการแจ้งเตือน "${notice.title}" ใช่หรือไม่?`)) {
            router.delete(`/calendar-notice/${notice.id}`, {
                preserveScroll: true,
            });
        }
    };

    const editingNotice = notices.find((n) => n.id === editingId) ?? null;

    return (
        <>
            <Head title="Calendar Notice" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Calendar Notice</h1>
                        <p className="text-sm text-muted-foreground">
                            Pick a day, then add a reminder — title, message,
                            a reminder date, and its type.
                        </p>
                    </div>
                    <Button size="sm" onClick={() => openCreate()}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Notice
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="mb-4 flex items-center justify-between gap-2">
                                <div className="flex items-center gap-1">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => shiftMonth(-1)}
                                        aria-label="Previous month"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span className="min-w-40 text-center font-semibold">
                                        {viewMonth.toLocaleDateString(
                                            undefined,
                                            {
                                                month: 'long',
                                                year: 'numeric',
                                            },
                                        )}
                                    </span>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => shiftMonth(1)}
                                        aria-label="Next month"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={goToday}
                                >
                                    Today
                                </Button>
                            </div>

                            <div className="grid grid-cols-7 gap-1">
                                {WEEKDAYS.map((day) => (
                                    <div
                                        key={day}
                                        className="pb-1 text-center text-xs font-medium text-muted-foreground"
                                    >
                                        {day}
                                    </div>
                                ))}
                                {cells.map((cell) => {
                                    const dayNotices =
                                        noticesByDate.get(cell.key) ?? [];
                                    const isToday = cell.key === TODAY;
                                    const isSelected =
                                        cell.key === selectedDate;

                                    return (
                                        <button
                                            type="button"
                                            key={cell.key}
                                            onClick={() =>
                                                setSelectedDate(cell.key)
                                            }
                                            onDoubleClick={() =>
                                                openCreate(cell.key)
                                            }
                                            className={`min-h-20 rounded-md border p-1 text-left align-top transition-colors hover:bg-accent ${
                                                isSelected
                                                    ? 'border-primary ring-1 ring-primary'
                                                    : 'border-transparent'
                                            } ${cell.inMonth ? '' : 'opacity-40'}`}
                                        >
                                            <span
                                                className={`inline-flex h-5 w-5 items-center justify-center rounded-full text-xs ${
                                                    isToday
                                                        ? 'bg-primary font-semibold text-primary-foreground'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {cell.day}
                                            </span>
                                            <div className="mt-1 space-y-0.5">
                                                {dayNotices
                                                    .slice(0, 3)
                                                    .map((notice) => (
                                                        <span
                                                            key={notice.id}
                                                            className="flex items-center gap-1 truncate text-[11px]"
                                                            title={notice.title}
                                                        >
                                                            <span
                                                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${typeStyle(notice.type).dot}`}
                                                            />
                                                            <span className="truncate">
                                                                {notice.title}
                                                            </span>
                                                        </span>
                                                    ))}
                                                {dayNotices.length > 3 && (
                                                    <span className="text-[11px] text-muted-foreground">
                                                        +{dayNotices.length - 3}{' '}
                                                        more
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>

                            <div className="mt-4 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                {Object.entries(types).map(([value, label]) => (
                                    <span
                                        key={value}
                                        className="flex items-center gap-1.5"
                                    >
                                        <span
                                            className={`h-2 w-2 rounded-full ${typeStyle(value).dot}`}
                                        />
                                        {label}
                                    </span>
                                ))}
                                <span className="text-muted-foreground/70">
                                    Double-click a day to add a notice.
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-3 pt-6">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold">
                                    {selectedDate
                                        ? prettyDate(selectedDate)
                                        : 'Upcoming'}
                                </p>
                                {selectedDate && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            openCreate(selectedDate)
                                        }
                                    >
                                        <Plus className="mr-1 h-4 w-4" />
                                        Add
                                    </Button>
                                )}
                            </div>

                            {(selectedDate ? selectedNotices : upcoming)
                                .length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">
                                    {selectedDate
                                        ? 'No notices on this day.'
                                        : 'No upcoming notices.'}
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {(selectedDate
                                        ? selectedNotices
                                        : upcoming
                                    ).map((notice) => (
                                        <li
                                            key={notice.id}
                                            className="rounded-md border p-3"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">
                                                        {notice.title}
                                                    </p>
                                                    <Badge
                                                        className={`mt-1 ${typeStyle(notice.type).badge}`}
                                                    >
                                                        {notice.type_label}
                                                    </Badge>
                                                </div>
                                                <div className="flex shrink-0 gap-1">
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            openEdit(notice)
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            handleDelete(
                                                                notice,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <p className="mt-2 line-clamp-3 text-sm text-muted-foreground">
                                                {notice.message}
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                                {!selectedDate && (
                                                    <span className="flex items-center gap-1">
                                                        <CalendarClock className="h-3 w-3" />
                                                        {prettyDate(
                                                            notice.notice_date,
                                                        )}
                                                    </span>
                                                )}
                                                {notice.remind_at && (
                                                    <span
                                                        className="flex items-center gap-1"
                                                        title={
                                                            notice.reminded_at
                                                                ? `Reminder sent ${prettyDateTime(notice.reminded_at)}`
                                                                : 'Reminder pending'
                                                        }
                                                    >
                                                        <Clock className="h-3 w-3" />
                                                        Remind{' '}
                                                        {prettyDateTime(
                                                            notice.remind_at,
                                                        )}
                                                        {notice.reminded_at &&
                                                            ' ✓'}
                                                    </span>
                                                )}
                                                <span className="flex items-center gap-1">
                                                    <UserIcon className="h-3 w-3" />
                                                    {notice.created_by ??
                                                        'Unknown'}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editingId
                                    ? 'Edit Calendar Notice'
                                    : 'Add Calendar Notice'}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="notice-title">Title</Label>
                                <Input
                                    id="notice-title"
                                    placeholder="e.g. Renew web01 TLS certificate"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                />
                                {errors.title && (
                                    <p className="text-sm text-red-500">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="notice-date">Date</Label>
                                    <Input
                                        id="notice-date"
                                        type="date"
                                        value={data.notice_date}
                                        onChange={(e) =>
                                            setData(
                                                'notice_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.notice_date && (
                                        <p className="text-sm text-red-500">
                                            {errors.notice_date}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="notice-type">Type</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value) =>
                                            setData('type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            id="notice-type"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(types).map(
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
                                    {errors.type && (
                                        <p className="text-sm text-red-500">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notice-remind">
                                    Reminder date &amp; time
                                </Label>
                                <Input
                                    id="notice-remind"
                                    type="datetime-local"
                                    value={data.remind_at}
                                    onChange={(e) =>
                                        setData('remind_at', e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Optional — a Telegram reminder is sent
                                    at this date and time (within a minute).
                                </p>
                                {errors.remind_at && (
                                    <p className="text-sm text-red-500">
                                        {errors.remind_at}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notice-message">Message</Label>
                                <textarea
                                    id="notice-message"
                                    className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="What is this reminder about?"
                                    value={data.message}
                                    onChange={(e) =>
                                        setData('message', e.target.value)
                                    }
                                />
                                {errors.message && (
                                    <p className="text-sm text-red-500">
                                        {errors.message}
                                    </p>
                                )}
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Created by{' '}
                                <span className="font-medium text-foreground">
                                    {editingId
                                        ? (editingNotice?.created_by ??
                                          'Unknown')
                                        : currentUser.name}
                                </span>
                            </p>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                {editingId ? 'Save Changes' : 'Add Notice'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

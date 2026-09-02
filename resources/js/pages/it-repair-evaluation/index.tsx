import { Head, router } from '@inertiajs/react';
import { Download, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

interface Criterion {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
}

interface SummaryCriterion {
    id: number;
    name: string;
    is_active: boolean;
}

interface TypeRow {
    service_type: string;
    evaluations: number;
    by_criterion: Record<number, number | null>;
    overall: number | null;
}

interface Total {
    by_criterion: Record<number, number | null>;
    overall: number | null;
    evaluations: number;
}

interface Filters {
    year: number;
    month: number | null;
    service_type: string | null;
}

interface Props {
    filters: Filters;
    availableYears: number[];
    serviceTypes: string[];
    criteria: Criterion[];
    summaryCriteria: SummaryCriterion[];
    rows: TypeRow[];
    total: Total;
    canManage: boolean;
}

const THAI_MONTHS = [
    'มกราคม',
    'กุมภาพันธ์',
    'มีนาคม',
    'เมษายน',
    'พฤษภาคม',
    'มิถุนายน',
    'กรกฎาคม',
    'สิงหาคม',
    'กันยายน',
    'ตุลาคม',
    'พฤศจิกายน',
    'ธันวาคม',
];

const ALL = 'all';

function scoreTint(value: number | null): string {
    if (value === null) {
        return 'text-muted-foreground';
    }

    if (value >= 4) {
        return 'text-green-600 dark:text-green-400';
    }

    if (value >= 3) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-red-600 dark:text-red-400';
}

function Cell({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={`inline-flex items-center gap-1 font-medium ${scoreTint(value)}`}
        >
            {value.toFixed(2)}
            <StarRating value={value} readOnly size={12} />
        </span>
    );
}

export default function Index({
    filters,
    availableYears,
    serviceTypes,
    criteria,
    summaryCriteria,
    rows,
    total,
    canManage,
}: Props) {
    const [previewOpen, setPreviewOpen] = useState(false);

    const applyFilters = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = { year: String(merged.year) };

        if (merged.month) {
            query.month = String(merged.month);
        }

        if (merged.service_type) {
            query.service_type = merged.service_type;
        }

        router.get('/it-repair-evaluation', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportQuery = new URLSearchParams({ year: String(filters.year) });

    if (filters.month) {
        exportQuery.set('month', String(filters.month));
    }

    if (filters.service_type) {
        exportQuery.set('service_type', filters.service_type);
    }

    const pdfUrl = `/it-repair-evaluation/export?${exportQuery.toString()}`;
    const previewUrl = `${pdfUrl}&format=html`;

    return (
        <>
            <Head title="สรุปผลการประเมินการบริการ" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">
                            สรุปผลการประเมินการบริการ
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            คะแนนดาวเฉลี่ยจากการประเมินงานซ่อม IT
                            แยกตามประเภทงานและเกณฑ์การประเมิน
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => setPreviewOpen(true)}
                    >
                        <FileText className="mr-2 h-4 w-4" />
                        พรีวิว &amp; Export PDF
                    </Button>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-end gap-4 pt-6">
                        <div className="space-y-1">
                            <Label className="text-xs">ปี</Label>
                            <Select
                                value={String(filters.year)}
                                onValueChange={(v) =>
                                    applyFilters({ year: Number(v) })
                                }
                            >
                                <SelectTrigger className="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableYears.map((y) => (
                                        <SelectItem key={y} value={String(y)}>
                                            {y}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">เดือน</Label>
                            <Select
                                value={
                                    filters.month
                                        ? String(filters.month)
                                        : ALL
                                }
                                onValueChange={(v) =>
                                    applyFilters({
                                        month: v === ALL ? null : Number(v),
                                    })
                                }
                            >
                                <SelectTrigger className="w-36">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>ทั้งปี</SelectItem>
                                    {THAI_MONTHS.map((label, i) => (
                                        <SelectItem
                                            key={label}
                                            value={String(i + 1)}
                                        >
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">ประเภทงาน</Label>
                            <Select
                                value={filters.service_type ?? ALL}
                                onValueChange={(v) =>
                                    applyFilters({
                                        service_type: v === ALL ? null : v,
                                    })
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        ทุกประเภท
                                    </SelectItem>
                                    {serviceTypes.map((t) => (
                                        <SelectItem key={t} value={t}>
                                            {t}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        ประเภทงาน
                                    </th>
                                    {summaryCriteria.map((c) => (
                                        <th
                                            key={c.id}
                                            className="px-4 py-2 font-medium"
                                        >
                                            {c.name}
                                            {!c.is_active && (
                                                <span className="ml-1 text-muted-foreground/60">
                                                    (ปิดใช้งาน)
                                                </span>
                                            )}
                                        </th>
                                    ))}
                                    <th className="px-4 py-2 font-medium">
                                        คะแนนรวมเฉลี่ย
                                    </th>
                                    <th className="px-4 py-2 font-medium">
                                        จำนวนการประเมิน
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={
                                                summaryCriteria.length + 3
                                            }
                                            className="px-4 py-10 text-center text-muted-foreground"
                                        >
                                            ยังไม่มีข้อมูลการประเมินตามเงื่อนไขที่เลือก
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((r) => (
                                        <tr
                                            key={r.service_type}
                                            className={
                                                r.evaluations === 0
                                                    ? 'text-muted-foreground'
                                                    : ''
                                            }
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {r.service_type}
                                            </td>
                                            {summaryCriteria.map((c) => (
                                                <td
                                                    key={c.id}
                                                    className="px-4 py-3"
                                                >
                                                    <Cell
                                                        value={
                                                            r.by_criterion[
                                                                c.id
                                                            ] ?? null
                                                        }
                                                    />
                                                </td>
                                            ))}
                                            <td className="px-4 py-3">
                                                <Cell value={r.overall} />
                                            </td>
                                            <td className="px-4 py-3">
                                                {r.evaluations}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            {rows.length > 0 && (
                                <tfoot className="border-t-2 bg-muted/30 font-medium">
                                    <tr>
                                        <td className="px-4 py-3">
                                            รวมทั้งหมด
                                        </td>
                                        {summaryCriteria.map((c) => (
                                            <td
                                                key={c.id}
                                                className="px-4 py-3"
                                            >
                                                <Cell
                                                    value={
                                                        total.by_criterion[
                                                            c.id
                                                        ] ?? null
                                                    }
                                                />
                                            </td>
                                        ))}
                                        <td className="px-4 py-3">
                                            <Cell value={total.overall} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {total.evaluations}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </CardContent>
                </Card>

                {canManage && <CriteriaPanel criteria={criteria} />}
            </div>

            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent className="max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>ตัวอย่างก่อนดาวน์โหลด PDF</DialogTitle>
                    </DialogHeader>
                    <iframe
                        title="ตัวอย่างรายงาน"
                        src={previewUrl}
                        className="h-[65vh] w-full rounded-md border bg-white"
                    />
                    <DialogFooter>
                        <Button asChild>
                            <a href={pdfUrl}>
                                <Download className="mr-2 h-4 w-4" />
                                ดาวน์โหลด PDF
                            </a>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function CriteriaPanel({ criteria }: { criteria: Criterion[] }) {
    const [name, setName] = useState('');
    const [editing, setEditing] = useState<Record<number, Criterion>>({});

    const add = () => {
        if (!name.trim()) {
            return;
        }

        router.post(
            '/it-repair-evaluation/criteria',
            { name },
            { preserveScroll: true, onSuccess: () => setName('') },
        );
    };

    const save = (c: Criterion) => {
        const d = editing[c.id];
        router.put(
            `/it-repair-evaluation/criteria/${c.id}`,
            {
                name: d.name,
                sort_order: d.sort_order,
                is_active: d.is_active,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setEditing((p) => {
                        const next = { ...p };
                        delete next[c.id];

                        return next;
                    }),
            },
        );
    };

    const remove = (c: Criterion) => {
        if (window.confirm(`ลบเกณฑ์ "${c.name}" ใช่หรือไม่?`)) {
            router.delete(`/it-repair-evaluation/criteria/${c.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">เกณฑ์การประเมิน</CardTitle>
                <p className="text-xs text-muted-foreground">
                    เฉพาะผู้ดูแลระบบ — เกณฑ์ที่ใช้งานจะปรากฏในแบบประเมินของงานซ่อม
                    (มาตรวัด 5 ดาว, 5 ดีที่สุด)
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                <ul className="divide-y rounded-md border">
                    {criteria.map((c) => {
                        const d = editing[c.id];

                        return (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-center gap-2 px-3 py-2"
                            >
                                {d ? (
                                    <>
                                        <Input
                                            className="h-8 w-56"
                                            value={d.name}
                                            onChange={(e) =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [c.id]: {
                                                        ...d,
                                                        name: e.target.value,
                                                    },
                                                }))
                                            }
                                        />
                                        <Input
                                            type="number"
                                            className="h-8 w-20"
                                            value={d.sort_order}
                                            onChange={(e) =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [c.id]: {
                                                        ...d,
                                                        sort_order: Number(
                                                            e.target.value,
                                                        ),
                                                    },
                                                }))
                                            }
                                        />
                                        <label className="flex items-center gap-1 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={d.is_active}
                                                onChange={(e) =>
                                                    setEditing((p) => ({
                                                        ...p,
                                                        [c.id]: {
                                                            ...d,
                                                            is_active:
                                                                e.target
                                                                    .checked,
                                                        },
                                                    }))
                                                }
                                            />
                                            ใช้งาน
                                        </label>
                                        <Button
                                            size="sm"
                                            onClick={() => save(c)}
                                        >
                                            บันทึก
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setEditing((p) => {
                                                    const next = { ...p };
                                                    delete next[c.id];

                                                    return next;
                                                })
                                            }
                                        >
                                            ยกเลิก
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <span className="w-8 text-xs text-muted-foreground">
                                            {c.sort_order}
                                        </span>
                                        <span className="w-56 font-medium">
                                            {c.name}
                                        </span>
                                        {!c.is_active && (
                                            <span className="text-xs text-muted-foreground">
                                                ปิดใช้งาน
                                            </span>
                                        )}
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() =>
                                                setEditing((p) => ({
                                                    ...p,
                                                    [c.id]: { ...c },
                                                }))
                                            }
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => remove(c)}
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </>
                                )}
                            </li>
                        );
                    })}
                </ul>
                <div className="flex items-center gap-2">
                    <Input
                        className="h-8 w-56"
                        placeholder="ชื่อเกณฑ์ใหม่"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <Button size="sm" variant="outline" onClick={add}>
                        <Plus className="mr-1 h-4 w-4" />
                        เพิ่ม
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

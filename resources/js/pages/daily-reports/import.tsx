import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Import() {
    const today = new Date().toISOString().slice(0, 10);

    const { data, setData, post, processing, errors } = useForm<{
        report_date: string;
        file: File | null;
    }>({
        report_date: today,
        file: null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/daily-reports/import', { forceFormData: true });
    };

    return (
        <>
            <Head title="Import Daily Report" />
            <div className="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Import Daily Report</h1>
                    <Button variant="outline" asChild>
                        <Link href="/daily-reports">Back to Daily Report</Link>
                    </Button>
                </div>

                <Card>
                    <form onSubmit={submit}>
                        <CardHeader>
                            <CardTitle>อัปโหลดไฟล์ Excel</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="report_date">
                                    วันที่ของรายงาน{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="report_date"
                                    type="date"
                                    value={data.report_date}
                                    onChange={(e) =>
                                        setData('report_date', e.target.value)
                                    }
                                    required
                                />
                                {errors.report_date && (
                                    <p className="text-sm text-red-500">
                                        {errors.report_date}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="file">
                                    ไฟล์ Excel (.xlsx, .xls, .csv){' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="file"
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(e) =>
                                        setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    required
                                />
                                {errors.file && (
                                    <p className="text-sm text-red-500">
                                        {errors.file}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    คอลัมน์ที่รองรับ: Name, Host, DNS, State,
                                    Status, Provisioned Space, Used Space, Host
                                    CPU, Host Mem (ลำดับคอลัมน์ไม่จำเป็นต้องตรงกัน
                                    แต่ชื่อหัวตารางต้องใกล้เคียงกับที่กำหนด —
                                    Host และ DNS ไม่บังคับ แต่จำเป็นสำหรับตาราง
                                    Host และ VM ที่พบปัญหาในรายงาน PDF)
                                </p>
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'กำลังนำเข้า...' : 'นำเข้าข้อมูล'}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CameraOff } from 'lucide-react';
import QrScanner from 'qr-scanner';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Mobile QR scanner (device camera). A successful decode of an asset QR
 * jumps straight to that asset's public page — the same page the sticker's
 * link opens — where the verify/count check is recorded.
 */
export default function Scan() {
    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<QrScanner | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [last, setLast] = useState<string | null>(null);

    const handleDecode = (data: string) => {
        setLast(data);

        // Asset QRs encode the full public URL (.../asset/<token>).
        if (data.includes('/asset/')) {
            scannerRef.current?.stop();

            try {
                const url = new URL(data, window.location.origin);
                window.location.href = url.pathname + url.search;
            } catch {
                window.location.href = data;
            }
        }
    };

    useEffect(() => {
        const video = videoRef.current;

        if (!video) {
            return;
        }

        const scanner = new QrScanner(
            video,
            (result) => handleDecode(result.data),
            {
                highlightScanRegion: true,
                highlightCodeOutline: true,
                preferredCamera: 'environment',
                maxScansPerSecond: 4,
            },
        );
        scannerRef.current = scanner;

        scanner.start().catch(() => {
            setError(
                'ไม่สามารถเปิดกล้องได้ — โปรดอนุญาตการใช้กล้อง หรือกรอกรหัสด้วยตนเอง',
            );
        });

        return () => {
            scanner.stop();
            scanner.destroy();
            scannerRef.current = null;
        };
    }, []);

    return (
        <>
            <Head title="สแกน QR ครุภัณฑ์" />
            <div className="mx-auto flex h-full w-full max-w-md flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="icon" asChild>
                        <Link href="/it-assets">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-xl font-bold">สแกน QR ครุภัณฑ์</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>เล็งกล้องไปที่ QR Code บนครุภัณฑ์</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="relative overflow-hidden rounded-lg border bg-black">
                            <video
                                ref={videoRef}
                                className="aspect-square w-full object-cover"
                                playsInline
                                muted
                            />
                            {error && (
                                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/70 p-4 text-center text-sm text-white">
                                    <CameraOff className="h-6 w-6" />
                                    {error}
                                </div>
                            )}
                        </div>
                        {last && !last.includes('/asset/') && (
                            <p className="text-sm text-amber-600 dark:text-amber-400">
                                QR ที่อ่านได้ไม่ใช่ QR ครุภัณฑ์: {last}
                            </p>
                        )}

                        <ManualEntry />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ManualEntry() {
    const [code, setCode] = useState('');

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                const c = code.trim();

                if (c) {
                    window.location.href = `/asset/${encodeURIComponent(c)}`;
                }
            }}
            className="space-y-1 border-t pt-3"
        >
            <label className="text-xs text-muted-foreground">
                กล้องใช้ไม่ได้? กรอกรหัสครุภัณฑ์ที่พิมพ์บนสติกเกอร์ (เช่น
                NB-0002)
            </label>
            <div className="flex gap-2">
                <input
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="รหัสครุภัณฑ์ หรือรหัสจาก QR"
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <Button type="submit" variant="outline">
                    ไป
                </Button>
            </div>
        </form>
    );
}

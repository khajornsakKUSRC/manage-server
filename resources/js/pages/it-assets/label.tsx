import { Head } from '@inertiajs/react';
import { QrCode } from '@/components/qr-code';

interface AssetLabel {
    asset_code: string;
    name: string;
    category: string | null;
    department: string | null;
    location: string | null;
    public_url: string;
}

/**
 * Printable Thai asset labels (สติกเกอร์ครุภัณฑ์). Renders one label per
 * asset in a grid sized for A4; "พิมพ์" opens the browser print dialog.
 * Rendered without the app shell (see app.tsx layout switch).
 */
export default function Label({ assets }: { assets: AssetLabel[] }) {
    return (
        <>
            <Head title="ป้ายครุภัณฑ์" />
            <style>{`
                @media print {
                    .no-print { display: none !important; }
                    .label-sheet { gap: 0 !important; }
                    .asset-label { break-inside: avoid; }
                }
                @page { margin: 8mm; }
            `}</style>

            <div className="min-h-svh bg-white p-6 text-black">
                <div className="no-print mx-auto mb-4 flex max-w-4xl items-center justify-between">
                    <h1 className="text-lg font-bold">
                        ป้ายครุภัณฑ์ ({assets.length} รายการ)
                    </h1>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="rounded-md bg-black px-4 py-2 text-sm font-medium text-white"
                    >
                        พิมพ์
                    </button>
                </div>

                <div className="label-sheet mx-auto grid max-w-4xl grid-cols-2 gap-3 sm:grid-cols-3">
                    {assets.map((a) => (
                        <div
                            key={a.asset_code}
                            className="asset-label flex gap-3 rounded-lg border border-black/60 p-3"
                        >
                            <QrCode
                                value={a.public_url}
                                size={96}
                                className="shrink-0"
                            />
                            <div className="min-w-0 text-[11px] leading-tight">
                                <p className="font-mono text-sm font-bold">
                                    {a.asset_code}
                                </p>
                                <p className="font-semibold break-words">
                                    {a.name}
                                </p>
                                {a.category && (
                                    <p className="text-black/70">
                                        {a.category}
                                    </p>
                                )}
                                {(a.department || a.location) && (
                                    <p className="text-black/70">
                                        {[a.department, a.location]
                                            .filter(Boolean)
                                            .join(' / ')}
                                    </p>
                                )}
                                <p className="mt-1 text-[9px] text-black/60">
                                    สแกนเพื่อตรวจสอบครุภัณฑ์
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

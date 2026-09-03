import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

/**
 * Renders `value` as a QR code <img> (PNG data URL). Used on the asset
 * detail card and the printable Thai label.
 */
export function QrCode({
    value,
    size = 160,
    className,
}: {
    value: string;
    size?: number;
    className?: string;
}) {
    const [src, setSrc] = useState<string | null>(null);

    useEffect(() => {
        let alive = true;

        QRCode.toDataURL(value, {
            width: size,
            margin: 1,
            errorCorrectionLevel: 'M',
        })
            .then((url) => {
                if (alive) {
                    setSrc(url);
                }
            })
            .catch(() => {
                if (alive) {
                    setSrc(null);
                }
            });

        return () => {
            alive = false;
        };
    }, [value, size]);

    if (!src) {
        return (
            <div
                className={className}
                style={{ width: size, height: size }}
                aria-hidden
            />
        );
    }

    return (
        <img
            src={src}
            width={size}
            height={size}
            alt={`QR: ${value}`}
            className={className}
        />
    );
}

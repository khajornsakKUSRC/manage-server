import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

// Matches Inertia's own default progress-bar delay so a fast navigation
// never flashes the overlay.
const SHOW_DELAY_MS = 250;

export function GlobalLoader() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let showTimeout: ReturnType<typeof setTimeout> | null = null;

        const clearPendingShow = () => {
            if (showTimeout) {
                clearTimeout(showTimeout);
                showTimeout = null;
            }
        };

        const removeStart = router.on('start', () => {
            clearPendingShow();
            showTimeout = setTimeout(() => setVisible(true), SHOW_DELAY_MS);
        });

        const removeFinish = router.on('finish', () => {
            clearPendingShow();
            setVisible(false);
        });

        return () => {
            clearPendingShow();
            removeStart();
            removeFinish();
        };
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-background/60 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            aria-label="Loading"
        >
            <span className="loader" />
        </div>
    );
}

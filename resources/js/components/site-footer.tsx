import { usePage } from '@inertiajs/react';

export function SiteFooter() {
    const { siteSettings } = usePage().props;

    if (!siteSettings?.footer_text) {
        return null;
    }

    return (
        <footer className="shrink-0 border-t px-4 py-3 text-center text-xs text-muted-foreground">
            {siteSettings.footer_text}
        </footer>
    );
}

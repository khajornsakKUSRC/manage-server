import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

// Shared on every page via Inertia (see HandleInertiaRequests), including
// the unauthenticated login page.
export type SiteSettings = {
    maintenance_enabled: boolean;
    maintenance_message: string | null;
    footer_text: string | null;
    favicon_url: string | null;
    disabled_pages: string[];
};

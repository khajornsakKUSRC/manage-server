/**
 * Mirrors app/Support/ThemePalette.php — same hue+chroma formula, same
 * ratios (each derived from app.css's own blue-purple values: e.g.
 * background chroma 0.01 at primary chroma 0.19 → 0.01/0.19 ≈ 0.0526).
 * The PHP side renders the no-flash server-side <style> tag; this side
 * drives the Settings page's instant preview when picking a swatch, by
 * setting the same custom properties directly on the root element — no
 * component re-render needed, every bg-primary/etc use just repaints.
 *
 * Keep THEME_OPTIONS in sync with ThemePalette::THEMES.
 */

export type ThemeKey =
    'blue_purple' | 'ku_green' | 'ocean_blue' | 'teal' | 'rose';

export const DEFAULT_THEME: ThemeKey = 'blue_purple';

export const THEME_OPTIONS: Record<
    ThemeKey,
    { label: string; hue: number; chroma: number }
> = {
    blue_purple: { label: 'Blue-Purple', hue: 275, chroma: 0.19 },
    ku_green: { label: 'KU Green', hue: 156, chroma: 0.13 },
    ocean_blue: { label: 'Ocean Blue', hue: 230, chroma: 0.16 },
    teal: { label: 'Teal', hue: 190, chroma: 0.14 },
    rose: { label: 'Rose', hue: 345, chroma: 0.17 },
};

/** Swatch/preview colour — same formula's --primary, light mode. */
export function swatchColor(key: string): string {
    const t = THEME_OPTIONS[key as ThemeKey] ?? THEME_OPTIONS[DEFAULT_THEME];

    return `oklch(0.53 ${t.chroma} ${t.hue})`;
}

function varsFor(
    hue: number,
    chroma: number,
    dark: boolean,
): Record<string, string> {
    if (!dark) {
        return {
            '--background': `oklch(0.97 ${chroma * 0.0526} ${hue})`,
            '--primary': `oklch(0.53 ${chroma} ${hue})`,
            '--primary-foreground': 'oklch(0.985 0 0)',
            '--accent': `oklch(0.945 ${chroma * 0.2105} ${hue})`,
            '--accent-foreground': `oklch(0.42 ${chroma * 0.7895} ${hue})`,
            '--ring': `oklch(0.55 ${chroma} ${hue})`,
            '--chart-1': `oklch(0.55 ${chroma} ${hue})`,
            '--sidebar': `oklch(0.985 ${chroma * 0.0316} ${hue})`,
            '--sidebar-primary': `oklch(0.53 ${chroma} ${hue})`,
            '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
            '--sidebar-accent': `oklch(0.945 ${chroma * 0.2105} ${hue})`,
            '--sidebar-accent-foreground': `oklch(0.42 ${chroma * 0.7895} ${hue})`,
            '--sidebar-ring': `oklch(0.55 ${chroma} ${hue})`,
        };
    }

    return {
        '--background': `oklch(0.13 ${chroma * 0.0737} ${hue})`,
        '--card': `oklch(0.19 ${chroma * 0.1053} ${hue})`,
        '--popover': `oklch(0.19 ${chroma * 0.1053} ${hue})`,
        '--primary': `oklch(0.62 ${chroma} ${hue})`,
        '--primary-foreground': 'oklch(0.985 0 0)',
        '--accent': `oklch(0.32 ${chroma * 0.4211} ${hue})`,
        '--accent-foreground': `oklch(0.88 ${chroma * 0.6842} ${hue})`,
        '--ring': `oklch(0.66 ${chroma} ${hue})`,
        '--chart-1': `oklch(0.66 ${chroma} ${hue})`,
        '--sidebar': `oklch(0.17 ${chroma * 0.0842} ${hue})`,
        '--sidebar-primary': `oklch(0.62 ${chroma} ${hue})`,
        '--sidebar-primary-foreground': 'oklch(0.985 0 0)',
        '--sidebar-accent': `oklch(0.32 ${chroma * 0.4211} ${hue})`,
        '--sidebar-accent-foreground': `oklch(0.88 ${chroma * 0.6842} ${hue})`,
        '--sidebar-ring': `oklch(0.66 ${chroma} ${hue})`,
    };
}

/**
 * Live-previews a theme by setting its custom properties directly on
 * <html> — instant, no reload, no React re-render. Purely client-side and
 * not persisted: a fresh page load always renders the *saved* theme
 * server-side (app.blade.php). Inertia's SPA navigation does NOT reload
 * the document, though, so these inline overrides would otherwise leak
 * into whatever page the admin visits next — callers previewing an
 * unsaved change must call clearThemePreview() on unmount (see the
 * Settings page) rather than relying on navigation to undo it.
 */
export function previewTheme(key: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const isDark = root.classList.contains('dark');
    const t = THEME_OPTIONS[key as ThemeKey] ?? THEME_OPTIONS[DEFAULT_THEME];
    const vars = varsFor(t.hue, t.chroma, isDark);

    for (const [prop, value] of Object.entries(vars)) {
        root.style.setProperty(prop, value);
    }
}

/** Undoes previewTheme()'s inline overrides — back to whatever app.css /
 *  the server-rendered theme <style> tag actually says. */
export function clearThemePreview(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const isDark = root.classList.contains('dark');

    for (const prop of Object.keys(varsFor(0, 0, isDark))) {
        root.style.removeProperty(prop);
    }
}

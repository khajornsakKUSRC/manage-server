<?php

namespace App\Support;

/**
 * The app's colour tokens (resources/css/app.css) are all one hue rotated
 * through a fixed lightness/chroma formula — that's how the blue-purple
 * default and the original KU green were themselves built. This exposes
 * that same formula as a small set of named presets, so Settings can pick
 * one without hand-authoring a full palette (~15 custom properties × 2
 * modes) per option.
 *
 * Keep resources/js/lib/theme-palette.ts's THEME_OPTIONS in sync with
 * THEMES below — one renders the no-flash server-side <style> tag
 * (app.blade.php), the other drives the Settings page's live preview.
 */
class ThemePalette
{
    public const DEFAULT = 'blue_purple';

    /** theme key => [label, hue (0-360), chroma (0-0.4ish)] */
    public const THEMES = [
        'blue_purple' => ['label' => 'Blue-Purple', 'hue' => 275, 'chroma' => 0.19],
        'ku_green' => ['label' => 'KU Green', 'hue' => 156, 'chroma' => 0.13],
        'ocean_blue' => ['label' => 'Ocean Blue', 'hue' => 230, 'chroma' => 0.16],
        'teal' => ['label' => 'Teal', 'hue' => 190, 'chroma' => 0.14],
        'rose' => ['label' => 'Rose', 'hue' => 345, 'chroma' => 0.17],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::THEMES);
    }

    /**
     * @return array<string, string> theme key => label, for the Settings dropdown.
     */
    public static function options(): array
    {
        return collect(self::THEMES)->map(fn (array $t) => $t['label'])->all();
    }

    /**
     * The <style> block that overrides app.css's default (blue-purple)
     * hue-dependent custom properties with the chosen theme's. Placed in
     * <head> after @vite(...) so it wins the cascade on load — same
     * "server-rendered, no flash" approach as the favicon (see
     * AppServiceProvider::shareFavicon()). Empty for the default theme,
     * since that's already what app.css itself ships.
     */
    public static function styleTag(?string $key): string
    {
        if (blank($key) || $key === self::DEFAULT || ! isset(self::THEMES[$key])) {
            return '';
        }

        ['hue' => $hue, 'chroma' => $chroma] = self::THEMES[$key];

        $light = self::declarations(self::vars($hue, $chroma, dark: false));
        $dark = self::declarations(self::vars($hue, $chroma, dark: true));

        return "<style>\n:root {\n{$light}\n}\n.dark {\n{$dark}\n}\n</style>";
    }

    /**
     * Just the html { background-color } app.blade.php's pre-CSS inline
     * <style> needs (see resources/css/app.css's own light/dark
     * --background) — avoids a flash of the wrong colour before @vite's
     * stylesheet (and, for a non-default theme, styleTag() above) loads.
     *
     * @return array{light: string, dark: string}
     */
    public static function backgroundColors(?string $key): array
    {
        $theme = self::THEMES[$key] ?? self::THEMES[self::DEFAULT];

        return [
            'light' => sprintf('oklch(0.97 %.4f %g)', $theme['chroma'] * 0.0526, $theme['hue']),
            'dark' => sprintf('oklch(0.13 %.4f %g)', $theme['chroma'] * 0.0737, $theme['hue']),
        ];
    }

    /**
     * Same set of derived custom properties resources/js/lib/theme-palette.ts
     * computes client-side for the live preview — see that file for the
     * ratios' origin (they're app.css's own blue-purple values expressed
     * relative to its hue=275/chroma=0.19).
     *
     * @return array<string, string>
     */
    protected static function vars(float $hue, float $chroma, bool $dark): array
    {
        if (! $dark) {
            return [
                '--background' => sprintf('oklch(0.97 %.4f %g)', $chroma * 0.0526, $hue),
                '--primary' => sprintf('oklch(0.53 %.4f %g)', $chroma, $hue),
                '--primary-foreground' => 'oklch(0.985 0 0)',
                '--accent' => sprintf('oklch(0.945 %.4f %g)', $chroma * 0.2105, $hue),
                '--accent-foreground' => sprintf('oklch(0.42 %.4f %g)', $chroma * 0.7895, $hue),
                '--ring' => sprintf('oklch(0.55 %.4f %g)', $chroma, $hue),
                '--chart-1' => sprintf('oklch(0.55 %.4f %g)', $chroma, $hue),
                '--sidebar' => sprintf('oklch(0.985 %.4f %g)', $chroma * 0.0316, $hue),
                '--sidebar-primary' => sprintf('oklch(0.53 %.4f %g)', $chroma, $hue),
                '--sidebar-primary-foreground' => 'oklch(0.985 0 0)',
                '--sidebar-accent' => sprintf('oklch(0.945 %.4f %g)', $chroma * 0.2105, $hue),
                '--sidebar-accent-foreground' => sprintf('oklch(0.42 %.4f %g)', $chroma * 0.7895, $hue),
                '--sidebar-ring' => sprintf('oklch(0.55 %.4f %g)', $chroma, $hue),
            ];
        }

        return [
            '--background' => sprintf('oklch(0.13 %.4f %g)', $chroma * 0.0737, $hue),
            '--card' => sprintf('oklch(0.19 %.4f %g)', $chroma * 0.1053, $hue),
            '--popover' => sprintf('oklch(0.19 %.4f %g)', $chroma * 0.1053, $hue),
            '--primary' => sprintf('oklch(0.62 %.4f %g)', $chroma, $hue),
            '--primary-foreground' => 'oklch(0.985 0 0)',
            '--accent' => sprintf('oklch(0.32 %.4f %g)', $chroma * 0.4211, $hue),
            '--accent-foreground' => sprintf('oklch(0.88 %.4f %g)', $chroma * 0.6842, $hue),
            '--ring' => sprintf('oklch(0.66 %.4f %g)', $chroma, $hue),
            '--chart-1' => sprintf('oklch(0.66 %.4f %g)', $chroma, $hue),
            '--sidebar' => sprintf('oklch(0.17 %.4f %g)', $chroma * 0.0842, $hue),
            '--sidebar-primary' => sprintf('oklch(0.62 %.4f %g)', $chroma, $hue),
            '--sidebar-primary-foreground' => 'oklch(0.985 0 0)',
            '--sidebar-accent' => sprintf('oklch(0.32 %.4f %g)', $chroma * 0.4211, $hue),
            '--sidebar-accent-foreground' => sprintf('oklch(0.88 %.4f %g)', $chroma * 0.6842, $hue),
            '--sidebar-ring' => sprintf('oklch(0.66 %.4f %g)', $chroma, $hue),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    protected static function declarations(array $vars): string
    {
        return collect($vars)
            ->map(fn (string $v, string $k) => "  {$k}: {$v};")
            ->implode("\n");
    }
}

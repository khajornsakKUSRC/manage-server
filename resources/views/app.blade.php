<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Sub-path the app is mounted under ("" or "/manage-server"),
             read by resources/js/lib/base-path.ts so client-side navigation
             and fetches stay under it. --}}
        <meta name="base-path" content="{{ Illuminate\Support\Facades\Request::getBaseUrl() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on the
             chosen theme (Settings → Appearance) before app.css loads. --}}
        <style>
            html {
                background-color: {{ $themeBackground['light'] }};
            }

            html.dark {
                background-color: {{ $themeBackground['dark'] }};
            }
        </style>

        @if ($faviconUrl ?? null)
            <link rel="icon" href="{{ $faviconUrl }}">
        @else
            <link rel="icon" href="{{ Illuminate\Support\Facades\Request::getBaseUrl() }}/favicon.ico" sizes="any">
            <link rel="icon" href="{{ Illuminate\Support\Facades\Request::getBaseUrl() }}/favicon-32x32.png" type="image/png" sizes="32x32">
            <link rel="apple-touch-icon" href="{{ Illuminate\Support\Facades\Request::getBaseUrl() }}/apple-touch-icon.png">
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])

        {{-- Overrides app.css's default (blue-purple) colour tokens when
             Settings → Appearance has a different theme selected. After
             @vite so it wins the cascade; empty string for the default
             theme. --}}
        {!! $themeStyleTag !!}

        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

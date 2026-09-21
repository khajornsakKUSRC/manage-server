import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Self-hosted fonts (laravel-vite-plugin/fonts + @fonts/Vite::fonts())
            // need Laravel 13's Vite::fonts() method, which doesn't exist on
            // Laravel 12 — Instrument Sans loads from Bunny Fonts' CDN via a
            // <link> in app.blade.php instead. See that file for details.
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]

});

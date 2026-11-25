import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                bunny('Fraunces', {
                    weights: [400, 500, 600],
                    styles: ['normal', 'italic'],
                }),
                bunny('IBM Plex Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400, 500, 600],
                }),
                // Admin panel typeface, rendered only by AdminPanelProvider's head hook.
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [{ weight: 500 }, { weight: 700 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

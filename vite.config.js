import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    subsets: ['latin'],
                }),
                // latin-ext covers names with characters such as ł, ő, ş and č. It's a separate
                // alias so only basic Latin is preloaded; browsers fetch these files when a page uses them.
                bunny('Instrument Sans', {
                    alias: 'instrument-sans-latin-ext',
                    weights: [400, 500, 600],
                    subsets: ['latin-ext'],
                    preload: false,
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

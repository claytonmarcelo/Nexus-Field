import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    variable: '--font-family-nexusfield-ui',
                    weights: [400, 500, 600, 700],
                }),
                bunny('Fraunces', {
                    variable: '--font-family-nexusfield-display',
                    weights: [600, 700],
                    styles: ['normal'],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

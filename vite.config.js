import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path'


export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/sass/app.scss',
                'resources/sass/ledgerIndex.scss',
                'resources/sass/ledgerEdit.scss',
                'resources/sass/ledgerDefineEdit.scss',
                'resources/js/app.js',
                'resources/js/ledgerEdit.js',
                'resources/js/ledgerDefineEdit.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        devSourcemap: true
    },
    resolve: {
        alias: {
            '~fontawesome': path.resolve(__dirname, 'node_modules/@fortawesome/fontawesome-free'),
            '$': 'jQuery',
        }
    },
    server: {
        hmr: {
            host: 'localhost'
        }
    },
});

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
        devSourcemap: true,
        preprocessorOptions: {
            scss: {
                implementation: 'sass',
                sassOptions: {
                    fiber: false,
                },
                css: {
                    includePaths: [path.resolve(__dirname, 'node_modules/easymde/dist')],
                },
            }
        }
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

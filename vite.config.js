import {defineConfig} from 'vite';
import tailwindcss from "@tailwindcss/vite";
import laravel from 'laravel-vite-plugin';
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // 'resources/css/app.css',
                'resources/sass/app.scss',
                'resources/sass/ledgerIndex.scss',
                'resources/sass/ledgerEdit.scss',
                'resources/sass/ledgerDefineEdit.scss',
                'resources/sass/filamentCustom.scss',
                'resources/js/app.js',
                'resources/js/ledgerEdit.js',
                'resources/js/ledgerDefineEdit.js',
                'resources/js/ledgerShow.js',
                'resources/js/ledgerIndex.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
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
            '$': 'jQuery',
        }
    },
    server: {
        hmr: {
            host: 'localhost'
        }
    },
});

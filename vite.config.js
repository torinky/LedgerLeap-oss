import {defineConfig, loadEnv} from 'vite';
import tailwindcss from "@tailwindcss/vite";
import laravel from 'laravel-vite-plugin';
import path from 'path'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    
    return {
        plugins: [
            laravel({
                input: [
                    // 'resources/css/app.css',
                    'resources/sass/app.scss',
                    'resources/sass/ledgerIndex.scss',
                    'resources/sass/ledgerEdit.scss',
                    'resources/sass/ledgerDefineEdit.scss',
                    'resources/sass/ledgerShow.scss',
                    'resources/css/filament/admin/theme.css',
                    'resources/sass/filamentCustom.scss',
                    'resources/css/tree.css',
                    'resources/js/app.js',
                    'resources/js/ledgerEdit.js',
                    'resources/js/ledgerIndex.js',
                    'resources/js/ledgerShow.js',
                    'resources/js/ledgerDefineEdit.js',
                    'resources/sass/app.scss',
                ],
                refresh: true,
            }),
            tailwindcss(),
        ],
        css: {
            devSourcemap: true,
            preprocessorOptions: {
                scss: {
                    additionalData: `
                        $daisyui-light: "${env.DAISYUI_THEME_LIGHT || 'corporate'}";
                        $daisyui-dark: "${env.DAISYUI_THEME_DARK || 'coffee'}";
                    `,
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
    }
});

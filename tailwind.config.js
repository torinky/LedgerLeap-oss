import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import daisyui from 'daisyui';
import dotenv from 'dotenv';

dotenv.config();

const lightTheme = process.env.DAISYUI_THEME_LIGHT || 'corporate';
const darkTheme = process.env.DAISYUI_THEME_DARK || 'coffee';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/robsontenorio/mary/src/View/Components/**/*.php',
        './vendor/robsontenorio/mary/resources/views/**/*.blade.php'
    ],

    safelist: [
        // folder/tree.blade.php の x-transition で使用する動的クラス（Alpine.js経由でJITスキャン不可）
        'max-h-0',
        'max-h-screen',
        'opacity-0',
        'opacity-100',
        '-translate-y-1',
        'translate-y-0',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                body: [
                    'Avenir',
                    'Helvetica Neue',
                    'Helvetica',
                    'Arial',
                    'Hiragino Sans',
                    'ヒラギノ角ゴシック',
                    'メイリオ',
                    'Meiryo',
                    'YuGothic',
                    'Yu Gothic',
                    'ＭＳ Ｐゴシック',
                    'MS PGothic',
                    'sans-serif'
                ],
            },
            screens: {
                '3xl': '1920px', // 3xlブレークポイントを設定
                '4xl': '2048px', // 4xlブレークポイントを設定
                '5xl': '2560px', // 5xlブレークポイントを設定
                '6xl': '3840px', // 6xlブレークポイントを設定
            }
        }
    },

    plugins: [
        forms,
        typography,
        daisyui,
    ],

    daisyui: {
        themes: [lightTheme, darkTheme], // アプリケーションで使用するテーマを指定
        darkTheme: darkTheme, // ダークモード時のデフォルトテーマ
    },

};

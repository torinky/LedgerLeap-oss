import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
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
            screens: {
                '3xl': '1920px', // 3xlブレークポイントを設定
                '4xl': '2048px', // 4xlブレークポイントを設定
                '5xl': '2560px', // 5xlブレークポイントを設定
                '6xl': '3840px', // 6xlブレークポイントを設定
            },
        },
    },

    plugins: [forms, require('@tailwindcss/forms'), require("@tailwindcss/typography"), require("daisyui")],
    // daisyUI config (optional)
    daisyui: {
        styled: true,
        themes: [
            "light",
            "dark",
            "cupcake",
            "bumblebee",
            "emerald",
            "corporate",
            "synthwave",
            "retro",
            "cyberpunk",
            "valentine",
            "halloween",
            "garden",
            "forest",
            "aqua",
            "lofi",
            "pastel",
            "fantasy",
            "wireframe",
            "black",
            "luxury",
            "dracula",
            "cmyk",
            "autumn",
            "business",
            "acid",
            "lemonade",
            "night",
            "coffee",
            "winter",
            "dim",
            "nord",
            "sunset",],
        base: true,
        utils: true,
        logs: true,
        rtl: false,
        prefix: "",
        darkTheme: "dark",
    },
};

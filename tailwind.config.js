import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
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
            ]
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
        ],
        base: true,
        utils: true,
        logs: true,
        rtl: false,
        prefix: "",
        darkTheme: "dark",
    },
};

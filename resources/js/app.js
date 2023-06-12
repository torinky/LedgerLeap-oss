import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Theme changer
document.addEventListener('DOMContentLoaded', function () {

    // const darkThemeName='business'
    const darkThemeName = 'synthwave'
    const lightThemeName = 'cupcake'
    const themeToggleBtn = document.getElementById('theme-toggle');

    if (
        localStorage.getItem('color-theme') === darkThemeName ||
        (!('color-theme' in localStorage) &&
            window.matchMedia('(prefers-color-scheme: dark)').matches)
    ) {
        document.documentElement.dataset.theme = darkThemeName;
        themeToggleBtn.checked = false;
    } else {
        document.documentElement.dataset.theme = lightThemeName;
        themeToggleBtn.checked = true;
    }


    themeToggleBtn.addEventListener('click', function (e) {

        // console.log(e.target.checked);

        // if set via local storage previously
        if (localStorage.getItem('color-theme')) {
            if (localStorage.getItem('color-theme') === lightThemeName && !e.target.checked) {
                document.documentElement.classList.add(darkThemeName);
                localStorage.setItem('color-theme', darkThemeName);
            } else {
                document.documentElement.classList.remove(darkThemeName);
                localStorage.setItem('color-theme', lightThemeName);
            }

            // if NOT set via local storage previously
        } else {
            if (document.documentElement.classList.contains(darkThemeName)) {
                document.documentElement.classList.remove(darkThemeName);
                localStorage.setItem('color-theme', lightThemeName);
            } else {
                document.documentElement.classList.add(darkThemeName);
                localStorage.setItem('color-theme', darkThemeName);
            }
        }

        document.documentElement.dataset.theme = localStorage.getItem('color-theme');

    });
});

import flatpickr from "flatpickr"
import {Japanese} from "flatpickr/dist/l10n/ja.js";


window.flatpickr = flatpickr

window.flatpickr(".datepicker", {
    locale: Japanese,
    showMonths: 3,
    wrap: true,
});


import flatpickr from "flatpickr"
import {Japanese} from "flatpickr/dist/l10n/ja.js";


window.flatpickr = flatpickr
window.flatpickr.localize(flatpickr.l10ns.ja);

window.flatpickr(".datepicker", {
    locale: Japanese,
    showMonths: 3,
    wrap: true,
});


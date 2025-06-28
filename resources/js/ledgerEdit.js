console.log('LedgerEdit.js loaded successfully.')

// const $ = require("jquery");
// require('select2');

// import * as FilePond from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview'
import FilePondPluginFilePoster from 'filepond-plugin-file-poster';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import ja_ja from 'filepond/locale/ja-ja.js'


import * as FilePond from 'filepond'
import flatpickr from "flatpickr"
import {Japanese} from "flatpickr/dist/l10n/ja.js";

window.FilePondPluginImagePreview = FilePondPluginImagePreview

window.FilePondPluginFilePoster = FilePondPluginFilePoster

window.FilePondPluginFileValidateSize = FilePondPluginFileValidateSize

window.FilePondPluginFileValidateType = FilePondPluginFileValidateType

FilePond.registerPlugin(FilePondPluginImagePreview)
FilePond.registerPlugin(FilePondPluginFilePoster)
FilePond.registerPlugin(FilePondPluginFileValidateSize)
FilePond.registerPlugin(FilePondPluginFileValidateType)
FilePond.setOptions(ja_ja)
window.FilePond = FilePond

window.flatpickr = flatpickr
window.flatpickr.localize(flatpickr.l10ns.ja);

window.flatpickr(".datepicker", {
    locale: Japanese,
    showMonths: 3,
    wrap: true,
});

/*
$(document).ready(function () {
    $('.js-attachSelect2Tag').select2({
        tags: true
    });
});
*/

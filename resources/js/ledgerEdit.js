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

import EasyMDE from 'easymde';

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



window.EasyMDE = EasyMDE;

// エラーバッジ用のAlpineデータ登録 (Issue #17)
function registerGroupErrorBadge() {
    if (window.Alpine && !window.Alpine.data('groupErrorBadge')) {
        window.Alpine.data('groupErrorBadge', () => ({
            errorCount: 0,

            init() {
                // Livewireプロパティの errorsByGroup が存在する場合のみ有効化
                if (typeof this.$wire === 'undefined' || this.$wire.errorsByGroup === undefined) {
                    return;
                }

                const groupName = this.$root.dataset.groupName;
                if (!groupName) return;

                // 初期値の同期
                this.errorCount = this.$wire.errorsByGroup[groupName] || 0;

                // Livewireプロパティの変更を監視してエラー数をリアルタイム更新
                this.$watch('$wire.errorsByGroup', (value) => {
                    if (value) {
                        this.errorCount = value[groupName] || 0;
                    }
                });
            }
        }));
    }
}

if (window.Alpine) {
    registerGroupErrorBadge();
} else {
    document.addEventListener('alpine:init', registerGroupErrorBadge);
}

/*
$(document).ready(function () {
    $('.js-attachSelect2Tag').select2({
        tags: true
    });
});
*/

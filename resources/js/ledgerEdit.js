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
window.groupErrorBadge = function () {
    return {
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
    };
};

function registerGroupErrorBadge() {
    if (window.Alpine && !window.Alpine.data('groupErrorBadge')) {
        window.Alpine.data('groupErrorBadge', window.groupErrorBadge);
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

// エラー箇所への自動スクロール機能 (Issue #20, #23)
window.validationErrorNavigator = function () {
    return {
        errorFields: [],
        currentIndex: -1,

        init() {
            // スクロールイベントの待機 (windowレベルでリッスン)
            window.addEventListener('scroll-to-error', (event) => {
                this.scrollToField(event.detail.field);
            });

            // 前後へのナビゲーションイベント
            window.addEventListener('navigate-error', (event) => {
                this.navigate(event.detail.direction);
            });

            // エラーリストの更新を待機（Livewireからの通知などを想定）
            // ただし、現在はボタンクリック時にDOMから最新のリストを取得するほうが確実
        },

        updateErrorList() {
            // 画面上のエラーハイライトがある要素を取得してソート（上から順）
            const elements = Array.from(document.querySelectorAll('.validation-error-highlight'))
                .map(el => {
                    const fieldWrapper = el.closest('[id^="field-content-"]');
                    if (!fieldWrapper) return null;
                    return {
                        id: fieldWrapper.id,
                        top: fieldWrapper.getBoundingClientRect().top + window.scrollY
                    };
                })
                .filter(e => e !== null)
                .sort((a, b) => a.top - b.top);

            this.errorFields = elements.map(e => e.id);
        },

        async scrollToField(fieldName) {
            if (!fieldName) return;
            const elementId = fieldName.replace('.', '-');
            const targetId = `field-${elementId}`;
            let element = document.getElementById(targetId);

            if (element) {
                // 親グループ（collapse）が閉じている場合は展開する (Issue #20)
                const groupElement = element.closest('.collapse');
                if (groupElement && this.$wire) {
                    const groupName = groupElement.dataset.groupName;
                    if (groupName && this.$wire.collapsedStates[groupName] === true) {
                        // Livewireメソッドを呼び出して展開（false = 非折りたたみ = 展開）
                        await this.$wire.toggleGroup(groupName, false);
                        // DOMの更新を待機
                        await this.$nextTick();
                        // 再度要素を取得（Livewire re-render対策）
                        element = document.getElementById(targetId);
                    }
                }

                if (element) {
                    // レイアウトが安定するのを少し待ってからスクロール
                    setTimeout(() => {
                        element.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        element.classList.add('field-arrival-highlight');
                        setTimeout(() => {
                            element.classList.remove('field-arrival-highlight');
                        }, 1500);
                    }, 50);
                }

                // 現在のインデックスを更新
                this.updateErrorList();
                this.currentIndex = this.errorFields.indexOf(targetId);
            }
        },

        async navigate(direction) {
            this.updateErrorList();
            if (this.errorFields.length === 0) return;

            if (direction === 'next') {
                this.currentIndex = (this.currentIndex + 1) % this.errorFields.length;
            } else if (direction === 'prev') {
                this.currentIndex = (this.currentIndex - 1 + this.errorFields.length) % this.errorFields.length;
            }

            const targetId = this.errorFields[this.currentIndex];
            let element = document.getElementById(targetId);
            if (element) {
                // 親グループ（collapse）が閉じている場合は展開する (Issue #23)
                const groupElement = element.closest('.collapse');
                if (groupElement && this.$wire) {
                    const groupName = groupElement.dataset.groupName;
                    if (groupName && this.$wire.collapsedStates[groupName] === true) {
                        await this.$wire.toggleGroup(groupName, false);
                        await this.$nextTick();
                        // 再度要素を取得
                        element = document.getElementById(targetId);
                    }
                }

                if (element) {
                    setTimeout(() => {
                        element.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        element.classList.add('field-arrival-highlight');
                        setTimeout(() => {
                            element.classList.remove('field-arrival-highlight');
                        }, 1500);
                    }, 50);
                }
            }
        }
    };
};

function registerValidationErrorNavigator() {
    if (window.Alpine && !window.Alpine.data('validationErrorNavigator')) {
        window.Alpine.data('validationErrorNavigator', window.validationErrorNavigator);
    }
}

if (window.Alpine) {
    registerValidationErrorNavigator();
} else {
    document.addEventListener('alpine:init', registerValidationErrorNavigator);
}

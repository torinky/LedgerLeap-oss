@props(['generatedPrefillURL'])

<div x-data="{
    actionState: {
        copy: { loading: false, success: false }
    },
    showWarning: false,

    notify(title, type = 'success') {
        const css = type === 'success' ? 'alert-success' : 'alert-error';
        this.$dispatch('mary-toast', {
            toast: {
                type,
                title,
                description: '',
                css
            }
        });
    },

    async performAction(type, actionFn) {
        if (this.actionState[type].loading) return;

        this.actionState[type].loading = true;
        this.actionState[type].success = false;
        this.showWarning = false;

        try {
            // ファイルインスペクターの挙動に合わせた一貫性のための遅延
            await new Promise(r => setTimeout(r, 600));
            await actionFn();

            this.actionState[type].success = true;
            this.notify('{{ __('ledger.prefill.copy_success') }}');

            setTimeout(() => {
                this.actionState[type].success = false;
            }, 2000);

        } catch (e) {
            console.error('Copy failed:', e);
            this.showWarning = true;
            this.notify('{{ __('ledger.prefill.copy_failed') }}', 'error');
        } finally {
            this.actionState[type].loading = false;
        }
    },

    async copyToClipboard() {
        const url = $wire.generatedPrefillURL;
        if (!url) {
            this.notify('URL Not Found', 'error');
            return;
        }

        await this.performAction('copy', async () => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(url);
            } else {
                const successful = this.fallbackCopy(url);
                if (!successful) throw new Error('Copy failed');
            }
        });
    },

    fallbackCopy(text) {
        try {
            const textarea = document.getElementById('prefill-url-textarea');
            if (textarea) {
                textarea.focus();
                textarea.select();
                return document.execCommand('copy');
            }
            return false;
        } catch (err) {
            return false;
        }
    },

    init() {
        // モーダルが開いたときにURLを選択
        this.$watch('$wire.showPrefillModal', (value) => {
            if (value) {
                this.$nextTick(() => {
                    setTimeout(() => {
                        const textarea = document.getElementById('prefill-url-textarea');
                        if (textarea) {
                            textarea.focus();
                            textarea.select();
                        }
                    }, 200);
                });
            }
        });
    }
}">
    <x-mary-modal wire:model="showPrefillModal" class="backdrop-blur" title="">
        <div class="space-y-4">
            {{-- カスタムタイトル --}}
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                <span>{{ __('ledger.prefill.modal_title') }}</span>
            </h3>
            <p class="text-sm">{{ __('ledger.prefill.description') }}</p>

            {{-- コピー失敗メッセージ (手動コピー誘導) --}}
            <div x-show="showWarning"
                 x-transition
                 class="alert alert-warning wrap-break-word">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <div class="wrap-break-word overflow-hidden">
                    <div class="font-bold wrap-break-word">{{ __('ledger.prefill.auto_copy_failed_title') }}</div>
                    <div class="text-xs wrap-break-word">{{ __('ledger.prefill.auto_copy_failed_description') }}</div>
                </div>
            </div>

            {{-- URLを選択可能なテキストエリアとして表示（Safari対応） --}}
            <div>
                <textarea
                    id="prefill-url-textarea"
                    readonly
                    class="textarea textarea-bordered w-full font-mono text-xs"
                    rows="3"
                    @click="$el.select()"
                >{{ $generatedPrefillURL }}</textarea>
            </div>

            <x-mary-alert icon="o-information-circle" class="alert-info">
                {{ __('ledger.prefill.info_qr_or_share') }}
            </x-mary-alert>
        </div>

        <x-slot:actions>
            <x-mary-button
                class="btn-primary"
                @click="copyToClipboard()"
                x-bind:disabled="actionState.copy.loading"
            >
                <span x-show="actionState.copy.loading" class="loading loading-spinner loading-xs" x-cloak></span>
                <span x-show="actionState.copy.success" x-cloak>
                    <i class="fa-solid fa-check text-success"></i>
                </span>
                <span x-show="!actionState.copy.loading && !actionState.copy.success" x-cloak>
                    <i class="fa-solid fa-copy"></i>
                </span>
                <span>{{ __('ledger.prefill.copy_to_clipboard') }}</span>
            </x-mary-button>
            <x-mary-button
                label="{{ __('ledger.close') }}"
                @click="$wire.showPrefillModal = false"
            />
        </x-slot:actions>
    </x-mary-modal>
</div>

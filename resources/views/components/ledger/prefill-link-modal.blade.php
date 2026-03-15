@props(['generatedPrefillURL'])

@php
    $prefillQrCode = $this->prefillQRCode;
    $prefillUrlIsLong = $this->prefillUrlIsLong;
    $prefillQrCodeAvailable = $prefillQrCode !== '';
@endphp

<div x-data="{
    actionState: {
        copy: { loading: false, success: false },
        download: { loading: false, success: false }
    },
    qrCodeAvailable: @js($prefillQrCodeAvailable),
    showWarning: false,

    toastIcon(type = 'success') {
        const icons = {
            success: `<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-5 h-5'><path stroke-linecap='round' stroke-linejoin='round' d='M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' /></svg>`,
            error: `<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-5 h-5'><path stroke-linecap='round' stroke-linejoin='round' d='m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' /></svg>`,
        };

        return icons[type] ?? icons.success;
    },

    notify(title, type = 'success') {
        const css = type === 'success' ? 'alert-success' : 'alert-error';
        this.$dispatch('mary-toast', {
            toast: {
                type,
                title,
                description: '',
                icon: this.toastIcon(type),
                css
            }
        });
    },

    async performAction(type, actionFn, successMessage = '{{ __('ledger.prefill.copy_success') }}') {
        if (this.actionState[type].loading) return;

        this.actionState[type].loading = true;
        this.actionState[type].success = false;

        try {
            // ファイルインスペクターの挙動に合わせた一貫性のための遅延
            await new Promise(r => setTimeout(r, 600));
            await actionFn();

            this.actionState[type].success = true;
            this.notify(successMessage);

            setTimeout(() => {
                this.actionState[type].success = false;
            }, 2000);

        } catch (e) {
            console.error(`${type} failed:`, e);
            if (type === 'copy') this.showWarning = true;
            this.notify(e.message || 'Error occurred', 'error');
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

    async downloadQRCode() {
        if (!this.qrCodeAvailable) {
            this.notify('{{ __('ledger.prefill.qr_code_unavailable_title') }}', 'error');
            return;
        }

        await this.performAction('download', async () => {
            const svgElement = document.querySelector('#prefill-qr-svg svg');
            if (!svgElement) throw new Error('QR Code not found');

            const svgData = new XMLSerializer().serializeToString(svgElement);
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'prefill-qr-code.svg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, '{{ __('ledger.prefill.download_qr') }}');
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
    <x-mary-modal 
        wire:model="showPrefillModal" 
        class="backdrop-blur" 
        title="" 
        box-class="max-w-4xl"
        x-init="$watch('$wire.showPrefillModal', (value) => {
            if (value) {
                $nextTick(() => {
                    setTimeout(() => {
                        const textarea = $el.querySelector('.shared-url-textarea');
                        if (textarea) {
                            textarea.focus();
                            textarea.select();
                        }
                    }, 200);
                });
            }
        })"
    >
        <x-common.qr-share-layout
            title="{{ __('ledger.prefill.modal_title') }}"
            description="{{ __('ledger.prefill.description') }}"
            urlLabel="{{ __('ledger.prefill.url_label') ?? '事前入力URL' }}"
            :url="$this->generatedPrefillURL"
            :qrCode="$this->prefillQRCode"
            downloadName="prefill-qr-code.svg"
            copySuccessMessage="{{ __('ledger.prefill.copy_success') }}"
        >
            <x-slot:warnings>
                @if($this->prefillUrlIsLong)
                    <div class="alert alert-warning py-2 px-3 text-xs mb-4 shadow-sm">
                        <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                        <span>{{ __('ledger.prefill.long_url_warning') }}</span>
                    </div>
                @endif
            </x-slot:warnings>

            <x-slot:info>
                <x-mary-alert icon="o-information-circle" class="alert-info text-xs py-3 border-none bg-info/10 mt-4">
                    {{ __('ledger.prefill.info_qr_or_share') }}
                </x-mary-alert>
            </x-slot:info>

            <x-slot:closeButton>
                <x-mary-button
                    label="{{ __('ledger.close') }}"
                    class="btn-sm px-6"
                    @click="$wire.showPrefillModal = false"
                />
            </x-slot:closeButton>
        </x-common.qr-share-layout>
    </x-mary-modal>
</div>

@props([
    'title' => null,
    'description' => null,
    'urlLabel' => __('ledger.prefill.url_label') ?? 'URL',
    'url' => null,
    'qrCodeTitle' => __('ledger.prefill.qr_code_title') ?? 'QR Code',
    'qrCodeDescription' => __('ledger.prefill.qr_code_description') ?? '',
    'qrCode' => null,
    'downloadName' => 'qr-code.svg',
    'copySuccessMessage' => __('ledger.prefill.copy_success') ?? 'Copied',
])

@php
    $qrCodeAvailable = !empty($qrCode);
@endphp

<div x-data="{
    actionState: {
        copy: { loading: false, success: false },
        download: { loading: false, success: false }
    },
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
            toast: { type, title, description: '', icon: this.toastIcon(type), css }
        });
    },

    async performAction(type, actionFn, successMessage = @js($copySuccessMessage)) {
        if (this.actionState[type].loading) return;

        this.actionState[type].loading = true;
        this.actionState[type].success = false;

        try {
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
        // Fallback to Livewire's url if provided
        const url = @js($url) || (this.$wire ? this.$wire.url : '');
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
        const svgElement = this.$root.querySelector('.qr-svg-container svg');
        if (!svgElement) {
            this.notify('{{ __('ledger.prefill.qr_code_unavailable_title') ?? 'QRコードを利用できません' }}', 'error');
            return;
        }

        await this.performAction('download', async () => {
            const svgData = new XMLSerializer().serializeToString(svgElement);
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = @js($downloadName);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, '{{ __('ledger.prefill.download_qr') }}');
    },

    fallbackCopy(text) {
        try {
            const textarea = this.$root.querySelector('.shared-url-textarea');
            if (textarea) {
                textarea.focus();
                textarea.select();
                return document.execCommand('copy');
            }
            return false;
        } catch (err) {
            return false;
        }
    }
}">
    <div class="space-y-6">
        @if($title || $description)
            <div class="flex items-center gap-4 border-b pb-4">
                <div class="p-3 bg-primary/10 rounded-xl">
                    <x-mary-icon name="o-link" class="w-8 h-8 text-primary" />
                </div>
                <div>
                    @if($title)
                        <h3 class="font-bold text-2xl tracking-tight">{{ $title }}</h3>
                    @endif
                    @if($description)
                        <p class="text-sm text-base-content/60">{{ $description }}</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-stretch">
            {{-- 左側: URL (3/5) --}}
            <div class="lg:col-span-3 space-y-4 flex flex-col">
                <div class="flex items-center gap-2 text-sm font-bold opacity-80">
                    <x-mary-icon name="o-document-text" class="w-4 h-4" />
                    {{ $urlLabel }}
                </div>

                @if(isset($warnings))
                    {{ $warnings }}
                @endif

                <div x-show="showWarning"
                     x-cloak
                     x-transition
                     class="alert alert-warning py-2 px-3 text-xs">
                    <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
                    <span>{{ __('ledger.prefill.auto_copy_failed_description') ?? 'コピーに失敗しました。以下のテキストエリアから手動でコピーしてください。' }}</span>
                </div>

                <div class="relative group grow">
                    <textarea
                        readonly
                        class="shared-url-textarea textarea textarea-bordered w-full font-mono text-xs bg-base-200/50 focus:bg-base-100 transition-all h-full min-h-[180px] resize-none leading-relaxed"
                        @click="$el.select()"
                    >{{ $url }}</textarea>
                    <div class="absolute bottom-3 right-3 opacity-20 group-hover:opacity-100 transition-opacity">
                        <x-mary-icon name="o-cursor-arrow-rays" class="w-5 h-5 text-primary" />
                    </div>
                </div>

                @if(isset($info))
                    {{ $info }}
                @endif
            </div>

            {{-- 右側: QRコード (2/5) --}}
            <div class="lg:col-span-2 flex flex-col items-center justify-center p-6 bg-base-200/50 rounded-2xl border border-base-300/50">
                <div class="text-sm font-bold mb-6 text-base-content/70">
                    {{ $qrCodeTitle }}
                </div>

                @if($qrCodeAvailable)
                    <div class="qr-svg-container bg-white p-5 rounded-2xl shadow-xl mb-6 transform transition-transform hover:scale-105">
                        {!! $qrCode !!}
                    </div>

                    <div class="text-xs text-base-content/50 text-center max-w-[200px] leading-relaxed">
                        {{ $qrCodeDescription }}
                    </div>
                @else
                    <div class="alert alert-warning text-sm w-full shadow-sm">
                        <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0" />
                        <span>{{ __('ledger.prefill.qr_code_unavailable') ?? 'QRコードを利用できません' }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Actions Slot --}}
    @if(isset($actions))
        <div class="mt-6">
            {{ $actions }}
        </div>
    @else
        <div class="flex flex-col sm:flex-row gap-4 w-full justify-between items-center bg-base-100 pt-6">
            <div class="text-xs text-base-content/40 italic flex items-center gap-2">
                <x-mary-icon name="o-device-phone-mobile" class="w-4 h-4" />
                {{ __('ledger.prefill.qr_code_hint') ?? '※モバイル端末での利用に最適化されています' }}
            </div>

            <div class="flex flex-wrap justify-center sm:justify-end gap-3 w-full sm:w-auto">
                <x-mary-button
                    type="button"
                    class="btn-ghost btn-sm border border-base-300 hover:bg-base-200"
                    @click="downloadQRCode()"
                    x-bind:disabled="actionState.download.loading"
                    :disabled="!$qrCodeAvailable"
                >
                    <span x-show="actionState.download.loading" class="loading loading-spinner loading-xs" x-cloak></span>
                    <x-mary-icon x-show="!actionState.download.loading" name="o-arrow-down-tray" class="w-4 h-4" />
                    <span>{{ __('ledger.prefill.download_qr') ?? 'QRコードを保存' }}</span>
                </x-mary-button>

                <x-mary-button
                    class="btn-primary btn-sm px-6 shadow-md"
                    @click="copyToClipboard()"
                    x-bind:disabled="actionState.copy.loading"
                >
                    <span x-show="actionState.copy.loading" class="loading loading-spinner loading-xs" x-cloak></span>
                    <template x-if="actionState.copy.success">
                        <x-mary-icon name="o-check" class="w-4 h-4" />
                    </template>
                    <template x-if="!actionState.copy.loading && !actionState.copy.success">
                        <x-mary-icon name="o-clipboard-document" class="w-4 h-4" />
                    </template>
                    <span>{{ __('ledger.prefill.copy_to_clipboard') ?? 'クリップボードにコピー' }}</span>
                </x-mary-button>

                @if(isset($closeButton))
                    <div class="hidden sm:block border-l border-base-300 h-8"></div>
                    {{ $closeButton }}
                @endif
            </div>
        </div>
    @endif
</div>

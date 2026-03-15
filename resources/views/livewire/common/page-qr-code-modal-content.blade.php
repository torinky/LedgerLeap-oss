<div
    x-data="{
        actionState: {
            copy: { loading: false, success: false },
            download: { loading: false, success: false }
        },
        showWarning: false,
        notify(title, status = 'success') {
            if (typeof FilamentNotification !== 'undefined') {
                const notification = new FilamentNotification().title(title);

                if (status === 'success') {
                    notification.success().send();
                } else {
                    notification.danger().send();
                }

                return;
            }

            console[status === 'success' ? 'info' : 'error'](title);
        },
        async performAction(type, actionFn, successMessage) {
            if (this.actionState[type].loading) {
                return;
            }

            this.actionState[type].loading = true;
            this.actionState[type].success = false;

            try {
                await new Promise(resolve => setTimeout(resolve, 600));
                await actionFn();

                this.actionState[type].success = true;
                this.notify(successMessage);

                setTimeout(() => {
                    this.actionState[type].success = false;
                }, 2000);
            } catch (error) {
                if (type === 'copy') {
                    this.showWarning = true;
                }

                this.notify(error.message || @js(__('ledger.qr_share.error_generic')), 'danger');
            } finally {
                this.actionState[type].loading = false;
            }
        },
        fallbackCopy() {
            const textarea = this.$root.querySelector('.shared-url-textarea');
            if (!textarea) {
                return false;
            }

            textarea.focus();
            textarea.select();

            return document.execCommand('copy');
        },
        async copyToClipboard() {
            if (!@js($url)) {
                this.notify(@js(__('ledger.qr_share.url_not_found')), 'danger');
                return;
            }

            await this.performAction('copy', async () => {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(@js($url));
                } else if (!this.fallbackCopy()) {
                    throw new Error(@js(__('ledger.qr_share.auto_copy_failed_title')));
                }
            }, @js(__('ledger.qr_share.copy_success')));
        },
        async downloadQRCode() {
            const svgElement = this.$root.querySelector('#filament-modal-qr-container svg');

            if (!svgElement) {
                this.notify(@js(__('ledger.qr_share.qr_code_unavailable_title')), 'danger');
                return;
            }

            await this.performAction('download', async () => {
                const svgData = new XMLSerializer().serializeToString(svgElement);
                const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                const blobUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = @js($downloadName);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(blobUrl);
            }, @js(__('ledger.qr_share.download_qr')));
        }
    }"
>
    <div class="space-y-6 py-2">
        <div class="grid grid-cols-1 items-stretch gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3 space-y-4 flex flex-col">
                <div class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300">
                    <x-heroicon-o-document-text class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                    {{ __('ledger.qr_share.url_label') }}
                </div>

                <div
                    x-show="showWarning"
                    x-cloak
                    x-transition
                    class="rounded-xl border border-warning-300 bg-warning-50 px-3 py-2 text-xs text-warning-800 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200"
                >
                    {{ __('ledger.qr_share.auto_copy_failed_description') }}
                </div>

                <div class="relative group grow">
                    <textarea
                        readonly
                        class="shared-url-textarea h-full min-h-[180px] w-full resize-none rounded-2xl border border-gray-300 bg-gray-50 p-3 font-mono text-xs leading-relaxed text-gray-800 transition-colors focus:bg-white dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:bg-gray-800"
                        @click="$el.select()"
                    >{{ $url }}</textarea>
                    <div class="absolute bottom-3 right-3 opacity-20 transition-opacity group-hover:opacity-100">
                        <x-heroicon-o-cursor-arrow-rays class="h-5 w-5 text-primary-500" />
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-6 text-sm font-bold text-gray-600 dark:text-gray-400">
                    {{ __('ledger.qr_share.qr_code_title') }}
                </div>

                @if($qrCode)
                    <div class="mx-auto my-3 w-fit rounded-2xl bg-white px-4 py-5 shadow-xl transition-transform hover:scale-105" id="filament-modal-qr-container">
                        <div class="flex w-full justify-center">
                            {!! $qrCode !!}
                        </div>
                    </div>

                    <p class="max-w-[200px] text-center text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ __('ledger.qr_share.qr_code_description') }}
                    </p>
                @else
                    <div class="w-full rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/30 dark:text-warning-200">
                        {{ __('ledger.qr_share.qr_code_unavailable') }}
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-col items-center justify-between gap-4 border-t border-gray-200 pt-6 dark:border-gray-800 sm:flex-row">
            <div class="flex items-center gap-2 text-xs italic text-gray-500 dark:text-gray-400">
                <x-heroicon-o-device-phone-mobile class="h-4 w-4" />
                {{ __('ledger.qr_share.qr_code_hint') }}
            </div>

            <div class="flex w-full flex-wrap justify-center gap-3 sm:w-auto sm:justify-end">
                <x-filament::button
                    type="button"
                    color="gray"
                    outlined
                    icon="heroicon-o-arrow-down-tray"
                    @click="downloadQRCode()"
                    x-bind:disabled="actionState.download.loading"
                    :disabled="!$qrCode"
                >
                    <span x-show="actionState.download.loading" class="fi-btn-label inline-flex items-center gap-2" x-cloak>
                        <x-filament::loading-indicator class="h-4 w-4" />
                        <span>{{ __('ledger.qr_share.download_qr') }}</span>
                    </span>
                    <span x-show="!actionState.download.loading">{{ __('ledger.qr_share.download_qr') }}</span>
                </x-filament::button>

                <x-filament::button
                    type="button"
                    icon="heroicon-o-clipboard-document"
                    @click="copyToClipboard()"
                    x-bind:disabled="actionState.copy.loading"
                >
                    <span x-show="actionState.copy.loading" class="fi-btn-label inline-flex items-center gap-2" x-cloak>
                        <x-filament::loading-indicator class="h-4 w-4" />
                        <span>{{ __('ledger.qr_share.copy_to_clipboard') }}</span>
                    </span>
                    <span x-show="!actionState.copy.loading && !actionState.copy.success">{{ __('ledger.qr_share.copy_to_clipboard') }}</span>
                    <span x-show="actionState.copy.success" x-cloak>{{ __('ledger.qr_share.copy_success') }}</span>
                </x-filament::button>
            </div>
        </div>
    </div>
</div>

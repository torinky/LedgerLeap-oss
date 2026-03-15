<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-stretch py-4">
    {{-- 左側: URL (3/5) --}}
    <div class="lg:col-span-3 space-y-4 flex flex-col">
        <div class="flex items-center gap-2 text-sm font-bold text-gray-700 dark:text-gray-300">
            <x-heroicon-o-document-text class="w-5 h-5 text-gray-500 dark:text-gray-400" />
            {{ __('ledger.prefill.url_label') ?? 'URL' }}
        </div>

        <div class="relative group grow" x-data>
            <textarea
                readonly
                class="w-full font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg p-3 transition-colors focus:bg-white dark:focus:bg-gray-800 h-full min-h-[180px] resize-none leading-relaxed text-gray-800 dark:text-gray-200"
                @click="$el.select()"
            >{{ $url }}</textarea>
        </div>

        {{-- URLコピーアクション --}}
        <div 
            x-data="{ 
                copied: false,
                copyToClipboard() {
                    window.navigator.clipboard.writeText('{{ $url }}');
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                    new FilamentNotification()
                        .title('{{ __('ledger.file_inspector.messages.link_copied') }}')
                        .success()
                        .send();
                }
            }"
            class="mt-auto"
        >
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-clipboard-document"
                @click="copyToClipboard"
                x-bind:class="copied ? 'ring-2 ring-success-500' : ''"
                class="w-full justify-center"
            >
                <span x-show="!copied">{{ __('ledger.prefill.copy_to_clipboard') ?? 'クリップボードにコピー' }}</span>
                <span x-show="copied" x-cloak class="text-success-500">{{ __('ledger.prefill.copy_success') ?? 'コピーしました' }}</span>
            </x-filament::button>
        </div>
    </div>

    {{-- 右側: QRコード (2/5) --}}
    <div class="lg:col-span-2 flex flex-col items-center justify-center p-6 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800">
        <div class="text-sm font-bold mb-4 text-gray-600 dark:text-gray-400">
            {{ __('ledger.prefill.qr_code_title') ?? 'QR Code' }}
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm mb-4 border border-gray-100 dark:border-gray-700 w-full flex justify-center items-center min-h-[200px]" id="filament-modal-qr-container">
            @if($qrCode)
                <div class="w-full h-full flex justify-center">
                    {!! $qrCode !!}
                </div>
            @else
                <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
            @endif
        </div>

        <p class="text-xs text-center text-gray-500 dark:text-gray-500 leading-relaxed mb-4">
            {{ __('ledger.page_qr_code.description') }}
        </p>

        {{-- QRダウンロードアクション --}}
        <div 
            x-data="{
                downloadQRCode() {
                    const svgElement = document.querySelector('#filament-modal-qr-container svg');
                    if (!svgElement) return;

                    const svgData = new XMLSerializer().serializeToString(svgElement);
                    const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'page-qr-code.svg';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                }
            }"
            class="w-full"
        >
            <x-filament::button
                type="button"
                color="gray"
                outlined
                icon="heroicon-o-arrow-down-tray"
                @click="downloadQRCode"
                class="w-full justify-center"
            >
                {{ __('ledger.prefill.download_qr') ?? 'QRコードを保存' }}
            </x-filament::button>
        </div>
    </div>
</div>

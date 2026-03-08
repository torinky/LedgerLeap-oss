<div class="flex flex-col items-center space-y-4 py-4">
    {{-- QRコード表示エリア --}}
    <div class="bg-white p-4 rounded-lg shadow-inner flex justify-center w-full min-h-[250px] items-center border border-gray-100 dark:border-gray-800">
        @if($qrCode)
            <div class="w-full h-full flex justify-center">
                {!! $qrCode !!}
            </div>
        @else
            <div class="flex justify-center items-center">
                <x-filament::loading-indicator class="h-10 w-10 text-primary-500" />
            </div>
        @endif
    </div>
    
    <p class="text-sm text-center text-gray-600 dark:text-gray-400">
        {{ __('ledger.page_qr_code.description') }}
    </p>

    {{-- URLコピーエリア --}}
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
        class="w-full flex items-center space-x-2 bg-gray-100 dark:bg-gray-800 p-2 rounded truncate text-xs"
    >
        <span class="truncate flex-1 dark:text-gray-300">{{ $url }}</span>
        <button 
            type="button"
            @click="copyToClipboard"
            class="flex items-center justify-center rounded-lg p-1 transition hover:bg-gray-500/5 focus:outline-none"
            :class="copied ? 'text-success-500' : 'text-gray-500 dark:text-gray-400'"
        >
            <x-heroicon-o-clipboard x-show="!copied" class="h-5 w-5" />
            <x-heroicon-o-check x-show="copied" class="h-5 w-5" />
        </button>
    </div>
</div>

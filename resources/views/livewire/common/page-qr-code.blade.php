<div>
    @if($triggerType === 'mary')
        <x-mary-button 
            icon="o-qr-code" 
            @click="$wire.openModal(window.location.href)"
            class="btn-ghost btn-sm btn-circle tooltip tooltip-bottom"
            data-tip="{{ __('ledger.page_qr_code.modal_title') }}" />

        <x-mary-modal wire:model="showModal" title="{{ __('ledger.page_qr_code.modal_title') }}" separator>
            <div class="flex flex-col items-center space-y-4">
                {{-- QRコード表示エリア --}}
                <div class="bg-white p-4 rounded-lg shadow-inner flex justify-center w-full min-h-[250px] items-center">
                    @if($showModal && $this->qrCode)
                        <div class="w-full h-full flex justify-center">
                            {!! $this->qrCode !!}
                        </div>
                    @else
                        <x-mary-loading class="text-primary" />
                    @endif
                </div>
                
                <p class="text-sm text-center text-base-content/70">
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
                            $mary.toast({type: 'success', title: '{{ __('ledger.file_inspector.messages.link_copied') }}'});
                        }
                    }"
                    class="w-full flex items-center space-x-2 bg-base-200 p-2 rounded truncate text-xs"
                >
                    <span class="truncate flex-1">{{ $url }}</span>
                    <button 
                        type="button"
                        @click="copyToClipboard"
                        class="flex items-center justify-center rounded-lg p-1 transition hover:bg-base-300 focus:outline-none"
                        :class="copied ? 'text-success' : 'text-base-content/70'"
                    >
                        <x-mary-icon x-show="!copied" name="o-clipboard" class="h-4 w-4" />
                        <x-mary-icon x-show="copied" name="o-check" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <x-slot:actions>
                <x-mary-button label="{{ __('ledger.close') }}" @click="showModal = false" />
            </x-slot:actions>
        </x-mary-modal>
    @else
        {{ $this->qrCodeAction }}

        <x-filament-actions::modals />
    @endif
</div>

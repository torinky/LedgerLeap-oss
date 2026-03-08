<div>
    @if($triggerType === 'mary')
        <x-mary-button 
            icon="o-qr-code" 
            @click="$wire.openModal(window.location.href)"
            class="btn-ghost btn-sm btn-circle tooltip tooltip-bottom"
            data-tip="{{ __('ledger.page_qr_code.modal_title') }}" />
    @else
        <div class="flex items-center">
            <button 
                type="button"
                @click="$wire.openModal(window.location.href)"
                class="filament-icon-button flex items-center justify-center rounded-full relative hover:bg-gray-500/5 focus:outline-none text-gray-500 dark:text-gray-400 p-1"
                title="{{ __('ledger.page_qr_code.modal_title') }}">
                <x-heroicon-o-qr-code class="w-5 h-5" />
            </button>
        </div>
    @endif

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
            <div class="w-full flex items-center space-x-2 bg-base-200 p-2 rounded truncate text-xs">
                <span class="truncate flex-1">{{ $url }}</span>
                <x-mary-button 
                    icon="o-clipboard" 
                    class="btn-ghost btn-xs"
                    @click="navigator.clipboard.writeText('{{ $url }}'); $mary.toast({type: 'success', title: '{{ __('ledger.file_inspector.messages.link_copied') }}'})" />
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="{{ __('ledger.close') }}" @click="showModal = false" />
        </x-slot:actions>
    </x-mary-modal>
</div>

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
    @else
        <div class="flex items-center">
            <x-filament::icon-button
                icon="heroicon-o-qr-code"
                @click="$wire.openModal(window.location.href)"
                tooltip="{{ __('ledger.page_qr_code.modal_title') }}"
            />
        </div>

        <x-filament::modal wire:model="showModal" width="md">
            <x-slot name="heading">
                {{ __('ledger.page_qr_code.modal_title') }}
            </x-slot>

            <div class="flex flex-col items-center space-y-4">
                {{-- QRコード表示エリア --}}
                <div class="bg-white p-4 rounded-lg shadow-inner flex justify-center w-full min-h-[250px] items-center">
                    @if($showModal && $this->qrCode)
                        <div class="w-full h-full flex justify-center">
                            {!! $this->qrCode !!}
                        </div>
                    @else
                        <div wire:loading class="flex justify-center items-center">
                            <x-filament::loading-indicator class="h-10 w-10 text-primary-500" />
                        </div>
                    @endif
                </div>
                
                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    {{ __('ledger.page_qr_code.description') }}
                </p>

                {{-- URLコピーエリア --}}
                <div class="w-full flex items-center space-x-2 bg-gray-100 dark:bg-gray-800 p-2 rounded truncate text-xs">
                    <span class="truncate flex-1 dark:text-gray-300">{{ $url }}</span>
                    <x-filament::icon-button
                        icon="heroicon-o-clipboard"
                        size="sm"
                        @click="navigator.clipboard.writeText('{{ $url }}'); new FilamentNotification().title('{{ __('ledger.file_inspector.messages.link_copied') }}').success().send()" />
                </div>
            </div>

            <x-slot name="footerActions">
                <x-filament::button
                    color="gray"
                    @click="showModal = false"
                >
                    {{ __('ledger.close') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</div>

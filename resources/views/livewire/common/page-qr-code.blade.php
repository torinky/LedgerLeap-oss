<div>
    @if($triggerType === 'mary')
        <x-mary-button 
            icon="o-qr-code" 
            wire:click="openModal"
            class="btn-ghost btn-sm btn-circle tooltip tooltip-bottom"
            data-tip="{{ __('ledger.page_qr_code.modal_title') }}" />

        <x-mary-modal wire:model="showModal" box-class="max-w-4xl" class="backdrop-blur">
            <x-common.qr-share-layout
                title="{{ __('ledger.page_qr_code.modal_title') }}"
                description="{{ __('ledger.page_qr_code.description') }}"
                :url="$url"
                :qrCode="$showModal ? $this->qrCode : null"
                :downloadName="$this->downloadFileName"
            >
                <x-slot:closeButton>
                    <x-mary-button label="{{ __('ledger.close') }}" class="btn-sm px-6" @click="$wire.showModal = false" />
                </x-slot:closeButton>
            </x-common.qr-share-layout>
        </x-mary-modal>
    @else
        {{ $this->qrCodeAction }}

        <x-filament-actions::modals />
    @endif
</div>

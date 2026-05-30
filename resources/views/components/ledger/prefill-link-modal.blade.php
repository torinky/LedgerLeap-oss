@props(['generatedPrefillURL'])

<div>
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
            urlLabel="{{ __('ledger.prefill.url_label') }}"
            qrCodeDescription="{{ __('ledger.prefill.qr_code_description') }}"
            :url="$this->generatedPrefillURL"
            :qrCode="$this->prefillQRCode"
            :downloadName="$this->prefillDownloadFileName"
            copySuccessMessage="{{ __('ledger.prefill.copy_success') }}"
            qrCodeUnavailableTitle="{{ __('ledger.prefill.qr_code_unavailable_title') }}"
            qrCodeUnavailableMessage="{{ __('ledger.prefill.qr_code_unavailable') }}"
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

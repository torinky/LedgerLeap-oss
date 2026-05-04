<div class="flex flex-wrap items-center gap-3">
    @php
        $isExporting = $exporting && !$exportFinished;
        $exportLabel = $isExporting ? __('ledger.exporting') : __('ledger.export_csv');
    @endphp

    @if(!$exportFinished)
        <x-mary-button
            wire:click="export"
            icon="o-arrow-down-tray"
            :label="$exportLabel"
            class="btn-outline btn-secondary w-48 justify-start"
            wire:key="ledger_export_request-{{ $ledgerDefineId }}"
            :disabled="$isExporting"
            wire:loading.attr="disabled"
            wire:target="export"
        />
    @else
        <x-mary-button
            :link="$this->downloadUrl"
            no-wire-navigate
            download="{{ $exportFilename }}"
            icon="o-arrow-down-on-square"
            :label="__('actions.download')"
            class="btn-secondary btn-sm"
            wire:key="ledger_export_download-{{ $ledgerDefineId }}"
        />
    @endif

    @if($isExporting)
        <span wire:poll.1s="updateExportProgress" class="sr-only" wire:key="ledger_export_progress-{{ $ledgerDefineId }}"></span>
    @endif
</div>

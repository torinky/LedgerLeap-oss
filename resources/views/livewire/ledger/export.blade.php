<div class="flex flex-wrap items-center gap-3">
    <x-mary-button
        wire:click="export"
        icon="o-arrow-down-tray"
        :label="$exporting && !$exportFinished ? __('ledger.exporting') : __('ledger.export_csv')"
        class="btn-outline btn-secondary w-48 justify-start"
        wire:key="ledger_export_request-{{ $ledgerDefineId }}"
        @if($exporting && !$exportFinished) disabled="disabled" @endif
        spinner="export"
    />

    @if($exporting && !$exportFinished)
        <span wire:poll="updateExportProgress" class="sr-only" wire:key="ledger_export_progress-{{ $ledgerDefineId }}"></span>
    @endif

    <x-mary-button
        :link="$exportFinished ? $this->downloadUrl : '#'"
        no-wire-navigate
        download="{{ $exportFilename }}"
        icon="o-arrow-down-on-square"
        :label="__('actions.download')"
        class="btn-secondary btn-sm{{ $exportFinished ? '' : ' pointer-events-none opacity-50' }}"
        wire:key="ledger_export_download-{{ $ledgerDefineId }}"
    />
</div>

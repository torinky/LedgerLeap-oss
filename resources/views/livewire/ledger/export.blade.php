<div>
    <a wire:click="export()" class="btn btn-outline-primary">Export</a>

    @if($exporting && !$exportFinished)
        <div class="d-inline" wire:poll="updateExportProgress">Exporting...please wait.</div>
    @endif

    @if($exportFinished)
        Done. Download file <a class="stretched-link" wire:click="downloadExport()"
                               :wire:key="'ledger_export_download-'.$ledgerDefineId">here</a>
    @endif
</div>

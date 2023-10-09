<div>
    <a wire:click="export()" class="btn btn-outline btn-secondary btn-sm relative inline-flex"
       @if($exporting && !$exportFinished) disabled="disabled" @endif
    >{{__('Start CSV Export')}}</a>

    @if($exporting && !$exportFinished)
        <span class="" wire:poll="updateExportProgress" class="flex space-x-5 items-center">
            <span class="loading loading-spinner loading-xs mx-2"></span><span>{{__('Exporting...please wait.')}}</span>
        </span>
    @endif

    @if($exportFinished)
        {{__('Export Process finished.')}}
        <a class="btn btn-secondary btn-sm relative inline-flex my-2" wire:click="downloadExport()"
           :wire:key="'ledger_export_download-'.$ledgerDefineId">Download</a>
    @endif
</div>

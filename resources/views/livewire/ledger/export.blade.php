<div>
    <a wire:click="export()" class="btn btn-outline btn-secondary btn-sm relative inline-flex"
       wire:key="ledger_export_request-{{$ledgerDefineId}}"
       @if($exporting && !$exportFinished) disabled="disabled" @endif
    >{{__('ledger.export_csv')}}</a>

    @if($exporting && !$exportFinished)
        <span wire:poll="updateExportProgress" class="flex space-x-5 items-center"
              wire:key="ledger_export_progress-{{$ledgerDefineId}}">
            <span class="loading loading-spinner loading-xs mx-2"></span><span>{{__('ledger.exporting')}}</span>
        </span>
    @endif

    @if($exportFinished)
        {{__('ledger.export_finished')}}
        <a class="btn btn-secondary btn-sm relative inline-flex my-2" wire:click="downloadExport()"
           wire:key="ledger_export_download-{{$ledgerDefineId}}"
        >{{__('actions.download')}}</a>
    @endif
</div>

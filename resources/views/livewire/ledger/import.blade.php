<div>

    <form wire:submit.prevent="importExcelCSV" enctype="multipart/form-data">
        @csrf
        <input type="file" wire:model="importFile" class="@error('import_file') is-invalid @enderror">
        <button class="btn btn-outline-secondary" @if($importing && !$importFinished) disable @endif>Import</button>
        @error('import_file')
        <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
    </form>
    <div>
        importing:{{$importing}}
    </div>
    <div>
        finished: {{$importFinished}}
    </div>

    {{-- The best athlete wants his opponent at his best. --}}
    {{--    @dd($ledgerDefine)--}}
    {{--    <div wire:poll="updateImportProgress">{{$totalRows}} </div>--}}
    {{--    <div wire:poll>--}}
    @if($importing && !$importFinished)
        <div wire:poll="updateImportProgress">Importing...please wait.</div>
        {{--    </div>--}}
    @else
        {{--        <div wire:poll>--}}
        @if($importFinished)
            Finished importing.
        @endif
        <p>
            {{$totalRows}}
        </p>
        <p>
            {{$currentRows}}
        </p>

        {{--        </div>--}}
    @endif


</div>

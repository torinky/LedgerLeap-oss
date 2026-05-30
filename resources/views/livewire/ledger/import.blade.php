@php use App\Imports\LedgerImport; @endphp
<div class="grid justify-items-center mt-10">

    <div class="card w-1/2 bg-base-100 shadow-xl relative overflow-hidden">
        <x-element.loading-overlay tier="2" target="importExcelCSV" message="{{ __('ledger.importing') }}" />
        <div class="card-body">
            <h2 class="card-title">{{__('CSV File Upload')}}</h2>
            <p>
                @if(!$importing && !$importFinished)
                    {{__('import to ')}}「{{$ledgerDefine?->title}}」<br/>
            {{__('Select CSV File.')}}
            @endif

            @if($importing && !$importFinished)
                <div wire:poll="updateImportProgress">
                    {{__('Importing...please wait.')}}
                </div>
            @endif

            @if($importFinished)
                {{__('Finished importing.')}}<br/>
                {{$updateRows??0}} {{__('records updated.')}} / {{$insertRows??0}} {{__('records inserted.')}}
                @endif
                </p>
                <form wire:submit="importExcelCSV" enctype="multipart/form-data">
                    @csrf
                    <div class="grid justify-items-center space-y-5 my-5">
                        <div class="form-control w-full max-w-md">
                            <label class="label cursor-pointer">
                        <span class="label-text">
                            <div class="tooltip"
                                 data-tip="[[id]]列で特定できるレコードは更新、その他は新規登録します">
                                <i class="fas fa-question-circle"></i></div>
                            {{__("update and insert")}}
                        </span>
                                <input type="radio" name="radio-import-mode" class="radio" wire:model.live="importMode"
                                       value="{{LedgerImport::MODE_UPDATE}}"/>
                            </label>
                            <label class="label cursor-pointer">
                        <span class="label-text">
                            <div class="tooltip" data-tip="台帳全体を破棄し新規登録します">
                                <i class="fas fa-question-circle"></i></div>
                            {{__("destroy ledger and insert as new record")}}
                        </span>
                                <input type="radio" name="radio-import-mode" class="radio" wire:model.live="importMode"
                                       value="{{LedgerImport::MODE_DESTOROY}}"/>
                            </label>
                            <label class="label cursor-pointer">
                        <span class="label-text">
                            <div class="tooltip" data-tip="新規登録します">
                                <i class="fas fa-question-circle"></i></div>
                            {{__("insert as new record")}}
                        </span>
                                <input type="radio" name="radio-import-mode" class="radio" wire:model.live="importMode"
                                       value="{{LedgerImport::MODE_INSERT}}"/>
                            </label>
                        </div>
                        <div>
                            <input type="file" class="file-input file-input-bordered file-input-primary w-full max-w-xs"
                                   wire:model.live="importFile" class="@error('import_file') is-invalid @enderror">
                        </div>
                        <div>
                            <progress class="progress progress-success w-56" value="{{$currentRows}}"
                                      max="{{$totalRows-1}}"></progress>
                        </div>

                    </div>
                    <div class="card-actions justify-end space-x-2">
                        <button class="btn btn-primary"
                                @if(empty($importFile) || ($importing && !$importFinished)) disabled @endif>Import
                        </button>
                        <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"
                           @if($importing && !$importFinished) disabled @endif><i
                                class="fa-solid fa-close"></i>{{__('close')}}</a>
                    </div>
                    @error('import_file')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                    @enderror
                </form>

        </div>
    </div>


    <p>
        file:{{$importFile}}
    </p>
    <p>
        importing:{{$importing}}
    </p>
    <p>
        finished: {{$importFinished}}
    </p>
    <p>
        total row : {{$totalRows}}
    </p>
    <p>
        current row : {{$currentRows}}
    </p>
    <p>
        mode : {{$importMode}}
    </p>

</div>

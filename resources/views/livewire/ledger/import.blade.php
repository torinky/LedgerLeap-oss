<div class="grid justify-items-center mt-10">

    <div class="card w-1/2 bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{__('CSV File Upload')}}</h2>
            <p>
            @if(!$importing && !$importFinished)
                {{__('Select CSV File.')}}
            @endif
            @if($importing && !$importFinished)
                <div wire:poll="updateImportProgress">
                    {{__('Importing...please wait.')}}
                </div>
                @endif
                @if($importFinished)
                    {{__('Finished importing.')}} {{$currentRows}} {{__('records updated/inserted.')}}
                @endif
                </p>
                <form wire:submit.prevent="importExcelCSV" enctype="multipart/form-data">
                    @csrf
                    <div class="grid justify-items-center space-y-5 my-5">
                        <div class="form-control w-full max-w-xs">
                            <label class="label">
                                <span class="label-text">{{__('Upload mode')}}</span>
                                {{--                            <span class="label-text-alt">Alt label</span>--}}
                            </label>
                            <select class="select select-bordered">
                                <option disabled selected>{{__('Pick one')}}</option>
                                <option>{{__("update and insert")}}</option>
                                <option>{{__("destroy ledger and insert as new record")}}</option>
                                <option>{{__("insert as new record")}}</option>
                            </select>
                            {{--
                                                    <label class="label">
                                                        <span class="label-text-alt">Alt label</span>
                                                        <span class="label-text-alt">Alt label</span>
                                                    </label>
                            --}}
                        </div>
                        <div>
                            <input type="file" class="file-input file-input-bordered file-input-primary w-full max-w-xs"
                                   wire:model="importFile" class="@error('import_file') is-invalid @enderror">
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

</div>

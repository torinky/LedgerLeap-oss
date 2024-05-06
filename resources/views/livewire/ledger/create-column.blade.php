<div>
    @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
        {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}"--}}
        <form wire:submit="store"
              method="post"
              class="w-full">
            @csrf
            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">
            <caption>
                {{$ledgerDefineRecord->title}}

            </caption>
            @php
                $columnJs=[];
            @endphp

            <div class="mb-32 space-y-5 mt-5">
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    <div class="flex justify-items-start items-center px-3 "
                         wire:key="content-{{$columnDefine->id}}">
                        <label for="content[{{$columnDefine->id}}]"
                               class="basis-1/4 text-right text-gray-700 font-bold mr-5">
                            @if($columnDefine->required)
                                <i class="fas fa-check-circle text-accent"></i>
                            @endif
                            {{$columnDefine->name}}
                        </label>
                            @if($columnDefine->type=='files')
                            <div class="form-control basis-3/4">
                                <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                                     wire:model.live="content"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                                     multiple
                                                     allowImagePreview
                                                     imagePreviewMaxHeight="200"
                                />
                            @else
                                    <div class="form-control">
                                <x-dynamic-component :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                                                     wire:model="content"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                />
                            @endif
                                        @error('content.' . $columnDefine->id)
                                        <label class="label">
                                    <span class="label-text-alt text-red-500 text-xs space-x-2">
                                        <i class="fas fa-times-circle"></i>
                                        <span class="error">{{ $message }}</span>
                                    </span>
                                        </label>
                                        @enderror
                                    </div>
                            </div>
                            @endforeach
                    </div>


                    {{--
                                    <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
                                        <button type="submit" class="btn btn-outline btn-warning btn-wide"><i
                                                class="fa-solid fa-pencil mr-2"></i>{{__('save')}}</button>
                                        <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                                class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
                                    </div>
                    --}}


                    <div
                        class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                        <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                            <div class="card-body ">
                                <div class="card-actions justify-center items-center">
                                    <button type="submit" class="btn btn-lg btn-warning btn-wide mr-4"><i
                                            class="fa-solid fa-plus-circle"></i>{{__('ledger.create_message')}}</button>
                                <a href="{{route('ledger.import.show',['ledgerDefineId'=>$ledgerDefineRecord->id])}}"
                                   class="btn btn-outline btn-info mr-4"><i
                                        class="fa-solid fa-file-csv ml-2"></i>{{__('ledger.import_file')}}</a>
                                    {{--
                                                                    <a href="#" class="btn btn-outline btn-info" onclick="window.close();"><i
                                                                            class="fa-solid fa-close mr-2"></i>{{__('ledger.close_window')}}</a>
                                    --}}
                                    <x-ledger.close-window-button/>
                            </div>
                        </div>
                        </div>
                    </div>
        </form>

    @endif
</div>


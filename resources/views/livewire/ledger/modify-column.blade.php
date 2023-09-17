<div>
    @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
            {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}"--}}
            <form wire:submit.prevent="store"
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
                                                     wire:model="content"
                                                     wire:model="deletedContent"
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
                                    @if(isset($ledgerRecord->id))
                                        <label for="delete-modal" class="btn btn-outline btn-error ml-10"><i
                                                class="fa-solid fa-trash mr-2"></i>{{__('delete record')}}</label>
                                    @endif
                                </div>
                --}}

                <div
                    class="card mx-auto md:w-full lg:w-2/3 bg-primary-content text-base-100 justify-center opacity-30 hover:opacity-90 transition-opacity inset-x-0 fixed bottom-3">
                    <div class="card-body items-center text-center">
                        <div class="card-actions justify-center">
                            <button type="submit" class="btn btn-outline btn-warning btn-wide"><i
                                    class="fa-solid fa-pencil mr-2"></i>{{__('save')}}</button>
                            <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                    class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
                            @if(isset($ledgerRecord->id))
                                <label for="delete-modal" class="btn btn-outline btn-error ml-10"><i
                                        class="fa-solid fa-trash mr-2"></i>{{__('delete record')}}</label>
                            @endif
                        </div>
                    </div>
                </div>

            </form>

            @if(isset($ledgerRecord->id))
                <input type="checkbox" id="delete-modal" class="modal-toggle"/>
                <div class="modal">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">{{__('delete record')}}</h3>
                        <p class="py-4">{{__('This record will be deleted')}}</p>
                        <div class="modal-action">
                            <div class="btnContainer">
                                <form method="POST" action="{{route('ledger.delete',$ledgerRecord->id)}}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn"
                                            name="deleteLedgerDefine">{{__('delete record')}}</button>
                                </form>
                            </div>
                            <label for="delete-modal" class="btn btn-outline ml-5">{{__('cancel')}}</label>
                        </div>
                    </div>
                </div>
            @endif

    @endif
</div>

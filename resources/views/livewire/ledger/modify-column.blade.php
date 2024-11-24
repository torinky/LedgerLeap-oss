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

            <div class="m-5 space-y-5">
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    @if($columnDefine->type=='files')
                        <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                             wire:model.live="content"
                                             wire:model.live="deletedContent"
                                             :columnDefine="$columnDefine"
                                             :ledgerRecord="$ledgerRecord??[]"
                                             multiple
                                             allowImagePreview
                                             imagePreviewMaxHeight="200"
                        />

                    @else
                        <x-dynamic-component
                            :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                            wire:model.live="content"
                            :columnDefine="$columnDefine"
                            :ledgerRecord="$ledgerRecord??[]"
                        />
                    @endif
                @endforeach
            </div>

            <div
                class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                    <div class="card-body flex flex-row justify-center items-center">
                        <div class="card-actions justify-center place-items-center">
                            <button type="submit" class="btn btn-lg btn-warning btn-wide"><i
                                    class="fa-solid fa-pencil mr-2"></i>{{__('ledger.modify_message')}}</button>
                            @if(isset($ledgerRecord->id))
                                <label for="delete-modal" class="btn btn-outline btn-sm btn-error ml-10"><i
                                        class="fa-solid fa-trash mr-2"></i>{{__('ledger.delete')}}</label>
                            @endif
                            <x-ledger.close-window-button/>

                        </div>
                    </div>
                </div>
            </div>

        </form>

        @if(isset($ledgerRecord->id))
            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box bg-warning text-warning-content">
                    <h3 class="font-bold text-lg space-x-2"><i
                            class="fas fa-trash-alt"></i><span>{{__('ledger.remove_title')}}</span></h3>
                    <p class="py-4">{{__('ledger.remove_message')}}</p>
                    <div class="modal-action">
                        <div class="btnContainer">
                            <form method="POST" action="{{route('ledger.delete',$ledgerRecord->id)}}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error space-x-2"
                                        name="deleteLedgerDefine"><i
                                        class="fas fa-trash-alt"></i>{{__('ledger.delete')}}
                                </button>
                            </form>
                        </div>
                        <label for="delete-modal" class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                    </div>
                </div>
            </div>
        @endif

    @endif
</div>

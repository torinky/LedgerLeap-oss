<div>
    <div
        class="background-image-change"
        x-data="{
            currentBg: null,
            updateBackground(columnId) {
                this.currentBg = $wire.backgroundImages[columnId] || null;

//                console.log($wire.backgroundImages);
//                console.log(this.currentBg);

                if(this.currentBg == null || this.currentBg.length == 0) {
                    document.querySelector('.background-image-change').style.backgroundImage = ``;
                }else{
                    document.querySelector('.background-image-change').style.backgroundImage = `url('${this.currentBg}')`;
                }
            },
            focusFirstInput() {
                const firstInput = document.querySelector('.background-image-change input:first-child');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        }"
        x-init="focusFirstInput()"
    >
    {{--    css生成のためのダミー--}}
    <div class="hidden">
        <div class="bg-success"></div>
        <x-mary-input label="Name" placeholder="Your name" icon="o-user" hint="Your full name"/>
    </div>
    @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
        {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}"--}}
        <x-mary-form wire:submit="store"
                     method="post"
                     class="card w-full bg-neutral-500/10 shadow-xl"
        >
            @csrf

            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">
            @php
                $columnJs=[];
            @endphp

            <div class="card-body mb-32 space-y-3 ">
                {{--
                                <h2 class="card-title">
                                    {{$ledgerDefineRecord->title}}
                                </h2>
                --}}
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    <div class="flex">
                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}}"></div>
                        <div
                            x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                            class="w-full opacity-control-block opacity-50 hover:opacity-100 transition-opacity duration-500 ease-in-out p-2 rounded hover:bg-base-100/80 {{ $loop->first ? 'initial-opacity-100' : '' }}"
                            @if($loop->first)
                                x-on:mouseleave="event.target.classList.remove('initial-opacity-100')"
                            x-init="updateBackground('{{ $columnDefine->id }}')"
                            @endif
                        >
                            @if($columnDefine->type=='files')
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
                                <x-dynamic-component
                                    :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                                    wire:model.live="content"
                                    :columnDefine="$columnDefine"
                                    :ledgerRecord="$ledgerRecord??[]"
                                />
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>
            <div
                class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                    <div class="card-body flex flex-row justify-center items-center">
                        <div class="card-actions justify-center place-items-center">
                            <x-mary-button label="{{__('ledger.modify_message')}}" icon="o-pencil-square"
                                           class="btn btn-lg btn-warning btn-wide mr-4" type="submit" spinner="store"/>
                            {{--
                                                        <button type="submit" class="btn btn-lg btn-warning btn-wide"><i
                                                                class="fa-solid fa-pencil mr-2"></i>{{__('ledger.modify_message')}}</button>
                            --}}
                            @if(isset($ledgerRecord->id))
                                <label for="delete-modal" class="btn btn-outline btn-sm btn-error ml-10"><i
                                        class="fa-solid fa-trash mr-2"></i>{{__('ledger.delete')}}</label>
                            @endif
                            <x-ledger.close-window-button/>

                        </div>
                    </div>
                </div>
            </div>

        </x-mary-form>

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

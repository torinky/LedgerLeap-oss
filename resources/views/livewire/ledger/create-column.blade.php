<div>
    {{--    css生成のためのダミー--}}
    <div class="hidden">
        <div class="bg-success"></div>
        <x-mary-input label="Name" placeholder="Your name" icon="o-user" hint="Your full name"/>
    </div>
    @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
        {{--            <form action="{{ route('ledger.store',$ledgerDefineRecord->id) }}"--}}
        <x-mary-form wire:submit="store"
                     method="post"
                     class="card w-full bg-neutral-500/10 shadow-xl">
            @csrf
            <input type="hidden" name="ledger_define_id" value="{{ $ledgerDefineRecord->id }}">
            @php
                $columnJs=[];
            @endphp


            <div class="card-body mb-32 space-y-5 ">
                <h2 class="card-title">
                    {{$ledgerDefineRecord->title}}
                    {{--@dd($content)--}}
                </h2>
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                    <div class="flex">
                        <div class="w-1 bg-{{$labelColor[$columnDefine->id]}} mr-2 "></div>
                        <div class="w-full"
                             wire:key="content-{{$columnDefine->id}}">

                            @if($columnDefine->type=='files')

                                <div class="">
                                    <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                                         wire:model.live="content"
                                                         :columnDefine="$columnDefine"
                                                         :ledgerRecord="$ledgerRecord??[]"
                                                         multiple
                                                         allowImagePreview
                                                         imagePreviewMaxHeight="200"
                                    />
                                </div>
                            @else
                                <x-dynamic-component :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                                                     wire:model="content"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                />

                            @endif
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
                            <x-mary-button label="{{__('ledger.create_message')}}" icon="s-plus-circle"
                                           class="btn btn-lg btn-warning btn-wide mr-4" type="submit" spinner="store"/>
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
        </x-mary-form>

    @endif
</div>


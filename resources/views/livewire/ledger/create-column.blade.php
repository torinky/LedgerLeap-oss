<div>
    @if($ledgerDefineRecord && $ledgerDefineRecord->column_define)
        <div class="flex flex-wrap items-center justify-center">
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

                <div class="mb-32">
                    @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)
                        <div class="flex flex-justify items-center align-middle px-3 my-5"
                             wire:key="content-{{$columnDefine->id}}">
                            <label for="content[{{$columnDefine->id}}]"
                                   class="basis-1/4 text-right text-gray-700 font-bold mr-5">
                                {{$columnDefine->name}}
                            </label>
                            {{--
                                    <input type="hidden" name="content[{{$columnDefine->id}}]" value="">
                                    <input name="content[{{$columnDefine->id}}]" type="text"
                                           value="{{$ledgerRecord->content[$columnDefine->id] ?? ''}}"
                                           placeholder="Type here"
                                           class="input input-bordered w-full"/>
                            --}}

                            @if($columnDefine->type=='files')
                                <x-dynamic-component :component="'ledger.form.'.$columnDefine->type"
                                                     wire:model="content"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                                     multiple
                                                     allowImagePreview
                                                     imagePreviewMaxHeight="200"
                                />

                                {{--                        @elseif($columnDefine->type=='text' || $columnDefine->type=='chk' || $columnDefine->type=='select')--}}
                            @else
                                <x-dynamic-component :component="'ledger.form.'. Str::kebab($columnDefine->type)"
                                                     wire:model="content"
                                                     :columnDefine="$columnDefine"
                                                     :ledgerRecord="$ledgerRecord??[]"
                                />
                            @endif

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
                    class="card mx-auto lg:w-1/2 bg-neutral text-neutral-content justify-center opacity-30 hover:opacity-100 transition-opacity inset-x-0 fixed bottom-0">
                    <div class="card-body items-center text-center">
                        <div class="card-actions justify-end">
                            <button type="submit" class="btn btn-outline btn-warning btn-wide"><i
                                    class="fa-solid fa-pencil mr-2"></i>{{__('save')}}</button>
                            <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                                    class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
                        </div>
                    </div>
                </div>
            </form>


        </div>
    @endif
</div>

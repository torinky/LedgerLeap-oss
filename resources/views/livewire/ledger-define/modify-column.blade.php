<div class="mt-5 mb-36">


    @if(!empty($ledgerDefineRecord->column_define))
        <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ animation: 500 }" class="space-y-3" drag-root>
            @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)

                <li wire:sortable.item="{{ $columnDefine->id }}"
                    wire:key="columnDefine-{{ $columnDefine->id }} drag-item"
                    class=" ">
                    <div
                        wire:sortable.handle
                        class="flex collapse-title bg-primary text-primary-content
                            peer-checked:bg-secondary peer-checked:text-secondary-content
                            peer-checked:pl-16
                            hover:opacity-80
                            ">

                        <div class="flex flex-row w-full ">

                            <h3 class="text-lg">
                                {{$columnDefine->id}}
                                : {{$columnDefine->name}}
                                : {{$columnInputTypes[$columnDefine->type]}}
                            </h3>
                        </div>
                        <button

                            class="justify-self-end btn btn-ghost btn-sm w-10 mr-2 tooltip tooltip-left"
                            data-tip="{{__('ledger.column.drag2sort')}}"
                        ><i class="fa-solid fa-grip-lines"></i>
                        </button>
                    </div>
                    <div class="collapse ">
                        <input type="checkbox" name="collapse_{{$columnDefine->id}}"
                               id="collapse_{{$columnDefine->id}}" class="peer collapse_swap hidden"/>



                        <div
                            class="collapse-content  bg-primary text-primary-content peer-checked:bg-secondary peer-checked:text-secondary-content">
                            <div class="items-center flex flex-row space-x-10">


                                <div class="basis-1/2 space-y-4">

                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][id]"
                                           value="{{$columnDefine->id}}">

                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][name]"
                                           value="{{$columnDefine->name}}">
                                    <label class="form-control">
                                        <div class="label">
                                            <span class="label-text">
                                                 {{__('ledger.column.title')}}
                                            </span>
                                        </div>
                                        <input name="column_define[{{$columnDefine->id}}][name]" type="text"
                                               value="{{$columnDefine->name}}"
                                               placeholder="{{__('ledger.column.title_input')}}"
                                               class="input input-bordered input-error" required/>
                                    </label>

                                    <label class="form-control">
                                        <div class="label">
                                            <span class="label-text">
                                                {{__('ledger.column.type')}}
                                            </span>
                                        </div>
                                        <select
                                            name="column_define[{{$columnDefine->id}}][type]"
                                            wire:model.live="columnTypes.{{$columnDefine->id}}"
                                            {{-- これは表現が複雑すぎるっぽい--}}
                                            {{-- wire:model.live="ledgerDefineRecord.column_define.{{$columnDefine->id}}.type"--}}
                                            {{-- これはapplyTpeが発火しない--}}
                                            {{-- wire:change="applyType($event.target.value,$columnDefine->id)"--}}
                                            {{-- wire:change="applyType($columnDefine->id)" --}}
                                            {{-- これは選択したの値を送ることができるがほとんど意味がない--}}
                                            {{-- wire:change="applyType($event.target.value)"--}}
                                            wire:change="applyType"
                                            class="select select-error">
                                            @foreach($columnInputTypes as $value => $columnInputTypeName)
                                                <option
                                                    {{--                                        value="{{$value}}" {{ old('column_define['.$columnDefine->id.'][type]', $columnDefine->type) == $value ? 'selected' : '' }}--}}
                                                    value="{{$value}}"
                                                    @if($columnDefine->type == $value) selected="selected" @endif
                                                >{{$columnInputTypeName}}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                </div>

                                <div class="basis-1/2 space-y-4">
                                    <div class="flex-none ml-5">

                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][required]"
                                               value="0">
                                        <div class="form-control">
                                            <label class="cursor-pointer label">
                                            <span class="label-text">
                                                {{__('ledger.column.required')}}
                                            </span>
                                                <input type="checkbox" class="checkbox checkbox-default"
                                                       name="column_define[{{$columnDefine->id}}][required]"
                                                       value="1" {{$columnDefine->required ? 'checked' : ''}} />
                                            </label>
                                        </div>

                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                               value="0">
                                        <div class="form-control">
                                            <label class="cursor-pointer label">
                                            <span class="label-text">
                                                {{__('ledger.column.unique')}}
                                            </span>
                                                <input type="checkbox" class="checkbox"
                                                       name="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                                       value="1" {{$columnDefine->doNotDuplicate ? 'checked' : ''}} />
                                            </label>
                                        </div>

                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][sortBy]"
                                               value="0">
                                        <div class="form-control">
                                            <label class="cursor-pointer label">
                                                <span class="label-text">
                                                    {{__('ledger.column.sort')}}
                                                </span>
                                                <input type="checkbox" class="checkbox"
                                                       name="column_define[{{$columnDefine->id}}][sortBy]"
                                                       value="1" {{$columnDefine->sortBy ? 'checked' : ''}} />
                                            </label>
                                        </div>
                                    </div>
                                    {{--                                </div>--}}
                                    {{--                                <div class="basis-1/4">--}}

                                    {{--                        @dd($columnDefine)--}}
                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][useOptions]"
                                           value="{{$columnDefine->useOptions}}">

                                    @if($columnDefine->useOptions)
                                        <div wire:ignore>
                                            <div class="flex-1 ml-5">
                                                <label class="form-control">
                                                    <div class="label">
                                                        <span class="label-text">
                                                            {{__('ledger.options')}}
                                                        </span>
                                                    </div>
                                                    <select name="column_define[{{$columnDefine->id}}][options][]"
                                                            class="js-attachSelect2Tag select select-bordered"
                                                            multiple="multiple">
                                                        @foreach($columnDefine->options as $key => $columnOption)
                                                            <option
                                                                value="{{$columnOption}}" selected
                                                            >{{$columnOption}}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>
                                        </div>
                                    @else
                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][options][]"
                                               value="">
                                        @foreach($columnDefine->options as $key => $columnOption)
                                            <input type="hidden"
                                                   name="column_define[{{$columnDefine->id}}][options][{{$key}}]"
                                                   value="{{$columnOption}}">
                                        @endforeach
                                    @endif


                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][order]"
                                           value="{{$columnDefine->order}}">

                                    <div class="mt-3 flex-row w-full text-right">
                                        {{--
                                                                <a href="#"
                                                                   wire:click="removeColumn({{$columnDefine->id}})"
                                                                   class="btn btn-outline btn-ghost btn-sm mx-3"
                                                                >
                                                                    <i class="fa-solid fa-trash mr-1"></i>
                                                                    {{__('remove')}}</a>
                                        --}}

                                        <label for="delete-modal-{{$columnDefine->id}}"
                                               class="btn btn-outline btn-error ml-10 justify-self-end"><i
                                                class="fa-solid fa-trash mr-1"></i> {{__('ledger.column.remove')}}
                                        </label>


                                    </div>
                                    <input type="checkbox" id="delete-modal-{{$columnDefine->id}}"
                                           class="modal-toggle hidden"
                                    />

                                    <div class="modal" role="dialog">
                                        <div class="modal-box">
                                            <h3 class="font-bold text-lg">{{__('ledger.column.remove')}}</h3>
                                            <p class="py-4">{{__('ledger.column.remove_message',['name'=>$columnDefine->name])}}
                                                <br/>
                                                {{$columnDefine->id}}
                                                : {{$columnDefine->name}}<br/>
                                            </p>
                                            <p class="text-lg text-bold text-error">  {{__('ledger.column.will_ledger_delete_message')}}</p>
                                            <div class="modal-action">
                                                <div class="btnContainer btn-error">
                                                    <a href="#"
                                                       wire:click="removeColumn({{$columnDefine->id}})"
                                                       class="btn"
                                                    >{{__('ledger.column.remove')}}</a>
                                                </div>
                                                <label for="delete-modal-{{$columnDefine->id}}"
                                                       class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                            </div>
                    </div>
                    <label for="collapse_{{ $columnDefine->id }}"
                           class="btn btn-sm btn-outline w-full tooltip" data-tip="{{__('ledger.collapse')}}">
                        {{--                                <input type="checkbox" style="display: none"/>--}}
                        <div class="pt-2">
                            <span class="swap-on"> <i class="fas fa-angles-down"></i></span>
                            <span class="swap-off"> <i class="fas fa-angles-up"></i></span>
                        </div>
                    </label>
                </li>
            @endforeach
            {{--         <input type="hidden" name="column_order"--}}
            {{--               value="{{Js::from( $ledgerDefineRecord->column_order)}}">--}}
        </ul>

        @once
            @push('scripts')
                <script type="module">

                    $(document).ready(function () {
                        // select2.jsの初期化
                        initializeSelect2();

                        Livewire.on('elementUpdated', function () {
                            // select2.jsの更新
                            initializeSelect2();
                        });

                        function initializeSelect2() {

                            $('.js-attachSelect2Tag').select2({
                                tags: true
                            });
                        }
                    });
                </script>
            @endpush
        @endonce

    @endif
    <a href="#" wire:click="addColumn" class="btn btn-outline btn-secondary btn-sm w-full my-4">
        <i class="fa-solid fa-plus-circle mr-1"></i>
        {{__('ledger.column.add')}}
    </a>

</div>

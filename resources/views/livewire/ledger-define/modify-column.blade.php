<div class="mt-5">

    @if(!empty($ledgerDefineRecord->column_define))
        <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ animation: 500 }" class="space-y-3" drag-root>
            @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)

                <li wire:sortable.item="{{ $columnDefine->id }}"
                    wire:key="columnDefine-{{ $columnDefine->id }} drag-item"
                    class=" ">
                    <div class="collapse collapse-arrow">
                        <input type="checkbox" name="collapse_{{$columnDefine->id}}"
                               id="collapse_{{$columnDefine->id}}" class="peer"/>

                        <div
                            class="flex collapse-title bg-primary text-primary-content peer-checked:bg-secondary peer-checked:text-secondary-content peer-checked:pl-16">

                            <button wire:sortable.handle class="btn btn-outline btn-sm w-10 mr-2 tooltip tooltip-right"
                                    data-tip="{{__('ledger.column.drag2sort')}}"><i
                                    class="fa-solid fa-grip-lines flex-none"></i></button>
                            <div
                                class="subpixel-antialiased text-xl flex-auto self-center">
                                <span> {{$columnDefine->name}}</span>
                            </div>
                        </div>

                        <div
                            class="collapse-content  bg-primary text-primary-content peer-checked:bg-secondary peer-checked:text-secondary-content">
                            <div class="items-center flex flex-row">


                                <div class="basis-1/2 space-y-4">

                                    <div class=" ">
                                        <label for="column_define[{{$columnDefine->id}}][name]"
                                               class="mr-2 text-right text-white">
                                            {{__('column name')}}
                                        </label>
                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][name]"
                                               value="{{$columnDefine->name}}">
                                        <input name="column_define[{{$columnDefine->id}}][name]" type="text"
                                               value="{{$columnDefine->name}}"
                                               placeholder="Type here"
                                               class="input input-bordered bg-secondary w-full max-w-xs " required/>
                                    </div>
                                    <div class="">
                                        <label for="column_define[{{$columnDefine->id}}][type]"
                                               class="mr-2 text-right text-white">
                                            {{__('type')}}
                                        </label>
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
                                            class="select">
                                            @foreach($columnInputTypes as $value => $columnInputTypeName)
                                                <option
                                                    {{--                                        value="{{$value}}" {{ old('column_define['.$columnDefine->id.'][type]', $columnDefine->type) == $value ? 'selected' : '' }}--}}
                                                    value="{{$value}}" {{  $columnDefine->type == $value ? 'selected' : '' }}
                                                >{{$columnInputTypeName}}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div>

                                <div class="basis-1/4 space-y-4">

                                    <div class="flex-none ml-5">
                                        <label for="column_define[{{$columnDefine->id}}][required]"
                                               class="mr-2 text-right text-white">
                                            {{__('input　required')}}
                                        </label>
                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][required]"
                                               value="0">
                                        <input type="checkbox" class="toggle"
                                               name="column_define[{{$columnDefine->id}}][required]"
                                               value="1" {{$columnDefine->required ? 'checked' : ''}} />
                                    </div>

                                    <div class="flex-none ml-5">
                                        <label for="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                               class="mr-2 text-right text-white">
                                            {{__('Do not duplicate')}}
                                        </label>
                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                               value="0">
                                        <input type="checkbox" class="toggle"
                                               name="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                               value="1" {{$columnDefine->doNotDuplicate ? 'checked' : ''}} />
                                    </div>

                                    <div class="flex-none ml-5">
                                        <label for="column_define[{{$columnDefine->id}}][sortBy]"
                                               class="mr-2 text-right text-white">
                                            {{__('Sort by this')}}
                                        </label>
                                        <input type="hidden" name="column_define[{{$columnDefine->id}}][sortBy]"
                                               value="0">
                                        <input type="checkbox" class="toggle"
                                               name="column_define[{{$columnDefine->id}}][sortBy]"
                                               value="1" {{$columnDefine->sortBy ? 'checked' : ''}} />
                                    </div>
                                </div>

                                <div class="basis-1/4">

                                    {{--                        @dd($columnDefine)--}}
                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][useOptions]"
                                           value="{{$columnDefine->useOptions}}">

                                    @if($columnDefine->useOptions)
                                        <div wire:ignore>
                                            <div class="flex-1 ml-5">
                                                <label for="column_define[{{$columnDefine->id}}][options][]"
                                                       class="mr-2 text-right">
                                                    {{__('options')}}
                                                </label>
                                                <select name="column_define[{{$columnDefine->id}}][options][]"
                                                        class="js-attachSelect2Tag" multiple="multiple">
                                                    @foreach($columnDefine->options as $key => $columnOption)
                                                        <option
                                                            value="{{$columnOption}}" selected
                                                        >{{$columnOption}}
                                                        </option>
                                                    @endforeach
                                                </select>
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


                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][id]"
                                           value="{{$columnDefine->id}}">
                                    <input type="hidden" name="column_define[{{$columnDefine->id}}][order]"
                                           value="{{$columnDefine->order}}">
                                </div>

                            </div>
                            <div class="mt-3 flex-row text-right">
                                {{--
                                                        <a href="#"
                                                           wire:click="removeColumn({{$columnDefine->id}})"
                                                           class="btn btn-outline btn-ghost btn-sm mx-3"
                                                        >
                                                            <i class="fa-solid fa-trash mr-1"></i>
                                                            {{__('remove')}}</a>
                                --}}

                                <label for="delete-modal-{{$columnDefine->id}}" class="btn btn-outline btn-error ml-10"><i
                                        class="fa-solid fa-trash mr-1"></i> {{__('remove')}}</label>


                            </div>
                            <input type="checkbox" id="delete-modal-{{$columnDefine->id}}" class="modal-toggle"
                                   style="display:none;"/>
                            <div class="modal">
                                <div class="modal-box">
                                    <h3 class="font-bold text-lg">{{__('delete column')}}</h3>
                                    <p class="py-4">{{__('This Column will be deleted')}}<br/>
                                        {{__('Ledger in records will be deleted')}}</p>
                                    <div class="modal-action">
                                        <div class="btnContainer">
                                            <a href="#"
                                               wire:click="removeColumn({{$columnDefine->id}})"
                                               class="btn"
                                            >{{__('remove')}}</a>
                                        </div>
                                        <label for="delete-modal-{{$columnDefine->id}}"
                                               class="btn btn-outline ml-5">{{__('cancel')}}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <label for="collapse_{{ $columnDefine->id }}"
                           class="btn btn-sm btn-outline w-full">
                                <span class=" tooltip"
                                      data-tip="{{__('click to collapse')}}"> <i class="fas fa-angles-down"></i></span>
                    </label>

                </li>
            @endforeach
            {{--        <input type="hidden" name="column_order"--}}
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

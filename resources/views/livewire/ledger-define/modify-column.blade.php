<div class="mt-5">
    <a href="#" wire:click="addColumn" class="btn btn-outline btn-secondary btn-sm mx-3">
        <i class="fa-solid fa-plus-circle mr-1"></i>
        {{__('add column setting')}}
    </a>

    @if(!empty($ledgerDefineRecord->column_define))
        <ul wire:sortable="updateColumnOrder" class="" drag-root>
            @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)

                <li wire:sortable.item="{{ $columnDefine->id }}"
                    wire:key="columnDefine-{{ $columnDefine->id }} drag-item"
                    class="grid card h-20 p-5 m-5 rounded bg-accent min-h-fit">

                    <div class="flex items-center ">
                        <div wire:sortable.handle class="w-10 align-middle 4xl flex-none"><i
                                class="fa-solid fa-grip-lines"></i></div>

                        <div class="flex-none ml-5 w-24">
                            <label for="column_define[{{$columnDefine->id}}][required]" class="mr-2 text-right">
                                {{__('input　required')}}
                            </label>
                            <input type="hidden" name="column_define[{{$columnDefine->id}}][required]" value="0">
                            <input type="checkbox" class="toggle" name="column_define[{{$columnDefine->id}}][required]"
                                   value="1" {{$columnDefine->required ? 'checked' : ''}} />
                        </div>

                        <div class="flex-none ml-5 w-24">
                            <label for="column_define[{{$columnDefine->id}}][doNotDuplicate]" class="mr-2 text-right">
                                {{__('Do not duplicate')}}
                            </label>
                            <input type="hidden" name="column_define[{{$columnDefine->id}}][doNotDuplicate]" value="0">
                            <input type="checkbox" class="toggle"
                                   name="column_define[{{$columnDefine->id}}][doNotDuplicate]"
                                   value="1" {{$columnDefine->doNotDuplicate ? 'checked' : ''}} />
                        </div>

                        <div class="flex-none ml-5 w-24">
                            <label for="column_define[{{$columnDefine->id}}][sortBy]" class="mr-2 text-right">
                                {{__('Sort by')}}
                            </label>
                            <input type="hidden" name="column_define[{{$columnDefine->id}}][sortBy]" value="0">
                            <input type="checkbox" class="toggle"
                                   name="column_define[{{$columnDefine->id}}][sortBy]"
                                   value="1" {{$columnDefine->sortBy ? 'checked' : ''}} />
                        </div>


                        <div class="flex-1 ml-5 ">
                            <label for="column_define[{{$columnDefine->id}}][name]" class="mr-2 text-right">
                                {{__('column name')}}
                            </label>
                            <input type="hidden" name="column_define[{{$columnDefine->id}}][name]"
                                   value="{{$columnDefine->name}}">
                            <input name="column_define[{{$columnDefine->id}}][name]" type="text"
                                   value="{{$columnDefine->name}}"
                                   placeholder="Type here"
                                   class="input input-bordered w-full max-w-xs" required/>
                        </div>

                        <div class="flex-none ml-5">
                            <label for="column_define[{{$columnDefine->id}}][type]" class="mr-2 text-right">
                                {{__('type')}}
                            </label>
                            <select
                                name="column_define[{{$columnDefine->id}}][type]"
                                wire:model="columnTypes.{{$columnDefine->id}}"
                                {{-- これは表現が複雑すぎるっぽい--}}
                                {{-- wire:model="ledgerDefineRecord.column_define.{{$columnDefine->id}}.type"--}}
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
                                <input type="hidden" name="column_define[{{$columnDefine->id}}][options][{{$key}}]"
                                       value="{{$columnOption}}">
                            @endforeach
                        @endif

                        <a href="#"
                           wire:click="removeColumn({{$columnDefine->id}})"
                           class="btn btn-outline btn-ghost btn-sm mx-3 "
                        >
                            <i class="fa-solid fa-trash mr-1"></i>
                            {{__('remove')}}</a>

                        <input type="hidden" name="column_define[{{$columnDefine->id}}][id]"
                               value="{{$columnDefine->id}}">
                        <input type="hidden" name="column_define[{{$columnDefine->id}}][order]"
                               value="{{$columnDefine->order}}">

                    </div>
                </li>
            @endforeach
            {{--        <input type="hidden" name="column_order"--}}
            {{--               value="{{Js::from( $ledgerDefineRecord->column_order)}}">--}}
        </ul>
    @endif
</div>

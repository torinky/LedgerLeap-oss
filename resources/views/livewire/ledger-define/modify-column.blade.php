<div>
    {{--    <x-mary-toast/>--}}
    <div wire:loading class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>


    @if(!empty($ledgerDefineRecord->column_define))
        <x-mary-form wire:submit.prevent="store">
            <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ animation: 500 }" class="space-y-3"
                drag-root>
                @foreach($ledgerDefineRecord->column_define as $cKey => $columnDefine)

                    <li wire:sortable.item="{{ $columnDefine->id }}"
                        wire:key="columnDefine-{{ $columnDefine->id }} drag-item"
                        class=" "
                    >
                        <div
                            wire:sortable.handle
                            class="flex collapse-title bg-primary/50 text-primary-content
                            peer-checked:bg-secondary peer-checked:text-secondary-content
                            peer-checked:pl-16
                            hover:opacity-80
                            rounded-tl-lg
                            rounded-tr-lg
                            "
                        >

                            <div class="flex flex-row w-full "
                            >
                                <h3 class="text-lg">
                                    {{$columnDefine->id}}
                                    : {{$columnDefine->name}}
                                    : {{$columnInputTypes[$columnDefine->type]}}
                                </h3>
                            </div>
                            <button
                                class=" btn btn-sm tooltip tooltip-left"
                                data-tip="{{__('ledger.column.drag2sort')}}"
                            ><i class="fa-solid fa-grip-lines"></i>
                            </button>
                        </div>
                        <div class="collapse rounded-none  bg-primary/30 text-primary-content">
                            <input type="checkbox" name="collapse_{{$columnDefine->id}}"
                                   id="collapse_{{$columnDefine->id}}"
                                   class="peer collapse_swap hidden"
                            />


                            <div class="collapse-content">
                                <div class="items-center flex flex-row space-x-10">

                                    <div class="basis-1/2 space-y-4">

                                        <x-mary-input
                                            label="{{__('ledger.column.title')}}"
                                            placeholder="{{__('ledger.column.title')}}" icon="o-table-cells" required
                                            wire:model.blur="columnName.{{$columnDefine->id}}"
                                            {{--                                        wire:change="applyName({{$columnDefine->id}})"--}}
                                            wire:key="name_{{$columnDefine->id}}"
                                            class="input-accent"
                                        />

                                        @php
                                            $tmpOptions=[];
                                            foreach ($columnInputTypes as $value => $columnInputTypeName){
                                                $tmpSelected = ($columnType[$columnDefine->id] == $value??'') ? true : false;
                                                $tmpOptions[] = ['id'=>$value, 'name'=>$columnInputTypeName, 'selected'=>$tmpSelected];
                                            }
                                        //    dd($tmpOptions);
                                        @endphp
                                        <x-mary-select label=" {{__('ledger.column.type')}}"
                                                       icon="o-chevron-up-down"
                                                       id="type[{{$columnDefine->id}}]"
                                                       name="column_define[{{$columnDefine->id}}][type]"
                                                       wire:model.live="columnType.{{$columnDefine->id}}"
                                                       {{--                                                   wire:change="applyType({{$columnDefine->id}})"--}}
                                                       wire:key="type_{{$columnDefine->id}}"
                                                       :options="$tmpOptions"
                                                       class="input-accent"
                                                       required
                                        />

                                        <hr/>
                                        <x-mary-checkbox label="{{__('ledger.column.required')}}"
                                                         wire:model.live.debounce.500ms="columnRequired.{{$columnDefine->id}}"
                                                         {{--                                                     wire:change="applyRequired({{$columnDefine->id}})"--}}
                                                         wire:key="required_{{$columnDefine->id}}"
                                                         name="column_define[{{$columnDefine->id}}][required]"
                                                         id="required[{{$columnDefine->id}}]"
                                        />
                                        <x-mary-checkbox label="{{__('ledger.column.unique')}}"
                                                         wire:model.live.debounce.500ms="columnUnique.{{$columnDefine->id}}"
                                                         {{--                                                     wire:change="applyUnique({{$columnDefine->id}})"--}}
                                                         wire:key="unique_{{$columnDefine->id}}"
                                                         name="column_define[{{$columnDefine->id}}][unique]"
                                                         id="unique[{{$columnDefine->id}}]"
                                        />
                                        <x-mary-checkbox label="{{__('ledger.column.sort')}}"
                                                         wire:model.live.debounce.500ms="columnSortBy.{{$columnDefine->id}}"
                                                         {{--                                                     wire:change="applySortBy({{$columnDefine->id}})"--}}
                                                         wire:key="sortBy_{{$columnDefine->id}}"
                                                         name="column_define[{{$columnDefine->id}}][sortBy]"
                                                         id="sortBy[{{$columnDefine->id}}]"
                                        />

                                    </div>


                                    <div class="basis-1/2 space-y-4 m-3">

                                        <x-mary-textarea
                                            label="{{__('ledger.column.hint')}}"
                                            wire:model.blur="columnHint.{{$columnDefine->id}}"
                                            class="input-accent w-full"
                                            wire:key="hint_{{$columnDefine->id}}"
                                            wire:dirty.class="border-yellow"
                                        />

                                        @if(isset($columnFile[$columnDefine->id]['name']) )
                                            <a href="{{ asset('storage/'.$columnFile[$columnDefine->id]['path']??'') }}"
                                               target="_blank"
                                            >
                                                <img
                                                    src="{{ asset('storage/thumbnails/'.$columnFile[$columnDefine->id]['path']) }}"
                                                    alt="{{ $columnFile[$columnDefine->id]['name'] }}">
                                            </a>

                                            <button
                                                class="btn btn-sm tooltip"
                                                data-tip="{{__('ledger.column.delete_file')}}"
                                                wire:click="deleteFile({{$columnDefine->id}})"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        @else
                                            <x-mary-file
                                                label="{{__('ledger.column.file')}}"
                                                wire:model.live="columnUploadedFile.{{$columnDefine->id}}"
                                                class="input-accent"
                                                wire:key="file_{{$columnDefine->id}}"
                                            />
                                        @endif

                                        @if($columnDefine->useOptions)

                                            <x-mary-tags label="{{__('ledger.options')}}"
                                                         wire:model.live="columnOptions.{{$columnDefine->id}}"
                                                         {{--                                                     wire:click="applyOptions({{$columnDefine->id}})" refresh--}}
                                                         wire:key="columnDefine-{{ $columnDefine->id }}-options"
                                                         wire:dirty.class="border-yellow"
                                                         icon="o-tag"
                                                         hint="Hit enter to create a new tag"
                                            />

                                        @endif


                                        <div class="mt-3 flex items-center justify-end w-full space-x-2">

                                            {{--
                                                                                    <x-mary-button
                                                                                        label="{{__('ledger.apply')}}"
                                                                                        type="submit"
                                                                                        spinner="store"
                                                                                        wire:click="store"
                                                                                        icon="o-check-circle"
                                                                                        class="btn btn-primary btn-sm btn-outline"
                                                                                    />
                                            --}}
                                            <label for="delete-modal-{{$columnDefine->id}}"
                                                   class="btn btn-outline btn-sm btn-error ml-10 justify-self-end"><i
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
                               class="btn btn-sm btn-primary bg-primary/20 w-full tooltip rounded-none rounded-bl-lg rounded-br-lg"
                               data-tip="{{__('ledger.collapse')}}">
                            {{--                                <input type="checkbox" style="display: none"/>--}}
                            <div class="pt-2">
                                <span class="swap-on"> <i class="fas fa-angles-down"></i></span>
                                <span class="swap-off"> <i class="fas fa-angles-up"></i></span>
                            </div>
                        </label>
                    </li>
                @endforeach
            </ul>
        </x-mary-form>

    @endif
    <a href="#" wire:click="addColumn" class="btn btn-outline btn-secondary btn-sm w-full my-4">
        <i class="fa-solid fa-plus-circle mr-1"></i>
        {{__('ledger.column.add')}}
    </a>


</div>

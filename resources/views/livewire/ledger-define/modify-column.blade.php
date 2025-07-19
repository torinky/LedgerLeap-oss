<div>
    {{-- Loading indicator --}}
    <div wire:loading wire:target="save,addColumn,removeColumn"
         class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

    {{-- Parent window reload script (no changes needed) --}}
    <script>
        window.addEventListener('reloadParentWindow', () => {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
        });
    </script>

    <form wire:submit.prevent="save" x-data="{ isDirty: @js($isDirty) }"
          @is-dirty-changed.window="isDirty = $event.detail.isDirty">
        @if(!empty($columns))
            <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ animation: 500 }" class="space-y-3">
                @foreach($columns as $index => $column)
                    <li wire:sortable.item="{{ $column['id'] }}" wire:key="column-{{ $column['id'] }}" class="z-20">
                        <div class="flex items-center">
                            {{-- ドラッグハンドルをx-mary-collapseの外に配置 --}}
                            <button wire:sortable.handle class="btn btn-sm tooltip tooltip-left mr-2"
                                    data-tip="{{__('ledger.column.drag2sort')}}">
                                <i class="fa-solid fa-grip-lines"></i>
                            </button>

                            <x-mary-collapse x-data="{ is_collapsed: @js($column['is_collapsed']) }"
                                             x-bind:open="is_collapsed"
                                             @toggle-collapse.window="is_collapsed = $event.detail.is_collapsed"
                                             class="bg-base-200 text-primary-content"
                                             wire:key="collapse-{{ $column['id'] }}">
                                <x-slot name="heading">
                                    <h3 class="text-lg font-semibold">
                                        {{ $column['id'] }}: {{ $column['name'] }}
                                        : {{ $columnInputTypes[$column['type']] ?? $column['type'] }}
                                    </h3>
                                </x-slot>

                                <x-slot name="content">
                                    <div class="p-4">
                                        <div class="items-center flex flex-row space-x-10">
                                            <div class="basis-1/2 space-y-4">
                                                <x-mary-input label="{{__('ledger.column.title')}}"
                                                              placeholder="{{__('ledger.column.title')}}"
                                                              icon="o-table-cells"
                                                              required
                                                              wire:model.live="columns.{{$index}}.name"
                                                              wire:key="name-{{$column['id']}}" class="input-accent"/>

                                                @php
                                                    $typeOptions = array_map(function($value, $name) {
                                                        return ['id' => $value, 'name' => $name];
                                                    }, array_keys($columnInputTypes), array_values($columnInputTypes));
                                                @endphp
                                                <x-mary-select label="{{__('ledger.column.type')}}"
                                                               icon="o-chevron-up-down"
                                                               wire:model.live="columns.{{$index}}.type"
                                                               wire:key="type-{{$column['id']}}" :options="$typeOptions"
                                                               class="input-accent" required/>

                                                <hr/>
                                                <x-mary-checkbox label="{{__('ledger.column.required')}}"
                                                                 wire:model="columns.{{$index}}.required"
                                                                 wire:key="required-{{$column['id']}}"/>
                                                <x-mary-checkbox label="{{__('ledger.column.unique')}}"
                                                                 wire:model="columns.{{$index}}.unique"
                                                                 wire:key="unique-{{$column['id']}}"/>
                                                <x-mary-checkbox label="{{__('ledger.column.sort')}}"
                                                                 wire:model="columns.{{$index}}.sortBy"
                                                                 wire:key="sortBy-{{$column['id']}}"/>
                                            </div>

                                            <div class="basis-1/2 space-y-4 m-3">
                                                <x-mary-textarea label="{{__('ledger.column.hint')}}"
                                                                 wire:model.live="columns.{{$index}}.hint"
                                                                 class="input-accent w-full"
                                                                 wire:key="hint-{{$column['id']}}"/>

                                                @if(isset($column['file']['path']))
                                                    <a href="{{ asset('storage/'.$column['file']['path']) }}"
                                                       target="_blank">
                                                        <img src="{{ asset('storage/thumbnails/'.$column['file']['path']) }}"
                                                             alt="{{ $column['file']['name'] }}">
                                                    </a>
                                                    <label for="delete-file-modal-{{$column['id']}}"
                                                           class="btn btn-sm tooltip"
                                                           data-tip="{{__('ledger.column.delete_file')}}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </label>
                                                    <!-- 背景画像削除確認モーダル -->
                                                    <input type="checkbox" id="delete-file-modal-{{$column['id']}}"
                                                           class="modal-toggle hidden"/>
                                                    <div class="modal" role="dialog" class="z-50">
                                                        <div class="modal-box">
                                                            <h3 class="font-bold text-lg">{{__('ledger.column.delete_file')}}</h3>
                                                            <p class="py-4">{{__('ledger.column.delete_file_message', ['name' => $column['name']])}}</p>
                                                            <div class="modal-action">
                                                                <label for="delete-file-modal-{{$column['id']}}"
                                                                       wire:click.prevent="deleteFile({{$column['id']}})"
                                                                       class="btn btn-error">{{__('actions.delete')}}</label>
                                                                <label for="delete-file-modal-{{$column['id']}}"
                                                                       class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <x-mary-file label="{{__('ledger.column.bg_file')}}"
                                                                 wire:model.live="columnUploadedFile.{{$column['id']}}"
                                                                 class="input-accent" wire:key="file-{{$column['id']}}"
                                                                 hint="png, jpg, jpeg, gif, svg"/>
                                                @endif

                                                @if($columns[$index]['useOptions'])
                                                    @if($column['type'] === 'auto_number')
                                                        <x-mary-input label="{{__('ledger.column.auto_number.prefix')}}"
                                                                      wire:model.live="columns.{{$index}}.options.prefix"
                                                                      wire:key="prefix-{{$column['id']}}"
                                                                      hint="{{__('ledger.column.auto_number.prefix_hint')}}"/>
                                                        <x-mary-input label="{{__('ledger.column.auto_number.digits')}}"
                                                                      wire:model.live="columns.{{$index}}.options.digits"
                                                                      wire:key="digits-{{$column['id']}}"
                                                                      type="number" min="1"
                                                                      hint="{{__('ledger.column.auto_number.digits_hint')}}"/>
                                                        <x-mary-input label="{{__('ledger.column.auto_number.revision')}}"
                                                                      wire:model.live="columns.{{$index}}.options.revision"
                                                                      wire:key="revision-{{$column['id']}}"
                                                                      hint="{{__('ledger.column.auto_number.revision_hint')}}"/>
                                                    @elseif($column['type'] === 'number')
                                                        <div class="grid grid-cols-2 gap-4">
                                                            {{--                                                        @dd($columns[$index])--}}
                                                            <x-mary-input label="{{__('ledger.column.number.min')}}"
                                                                          wire:model.live="columns.{{$index}}.options.min"
                                                                          wire:key="min-{{$column['id']}}"
                                                                          type="number"
                                                                          placeholder="{{__('ledger.column.number.min_placeholder')}}"/>
                                                            <x-mary-input label="{{__('ledger.column.number.max')}}"
                                                                          wire:model.live="columns.{{$index}}.options.max"
                                                                          wire:key="max-{{$column['id']}}"
                                                                          type="number"
                                                                          placeholder="{{__('ledger.column.number.max_placeholder')}}"/>
                                                            <x-mary-input label="{{__('ledger.column.number.step')}}"
                                                                          wire:model.live="columns.{{$index}}.options.step"
                                                                          wire:key="step-{{$column['id']}}"
                                                                          type="number"
                                                                          placeholder="{{__('ledger.column.number.step_placeholder')}}"/>
                                                            <x-mary-input label="{{__('ledger.column.number.unit')}}"
                                                                          wire:model.live="columns.{{$index}}.options.unit"
                                                                          wire:key="unit-{{$column['id']}}"
                                                                          placeholder="{{__('ledger.column.number.unit_placeholder')}}"/>
                                                        </div>
                                                    @else

                                                    <x-mary-tags label="{{__('ledger.options')}}"
                                                                     wire:model.live="columns.{{$index}}.options"
                                                                     wire:key="options-{{$column['id']}}" icon="o-tag"
                                                                     hint="Hit enter to create a new tag"/>
                                                    @endif
                                                @endif


                                                <div class="mt-3 flex items-center justify-end w-full space-x-2">
                                                    @if($isDirty)
                                                        <x-mary-button label="{{__('actions.save')}}"
                                                                       wire:click="saveColumn({{$index}})"
                                                                       icon="o-pencil-square"
                                                                       class="btn-primary btn-sm"
                                                                       spinner="saveColumn({{$index}})"
                                                        />
                                                    @else
                                                        <x-mary-button label="{{__('actions.save')}}"
                                                                       icon="o-pencil-square"
                                                                       class="btn-primary btn-sm"
                                                                       disabled
                                                        />
                                                    @endif
                                                    <label for="delete-modal-{{$column['id']}}"
                                                           class="btn btn-outline btn-sm btn-error ml-10 justify-self-end"><i
                                                                class="fa-solid fa-trash mr-1"></i> {{__('ledger.column.remove')}}
                                                    </label>
                                                </div>
                                                <input type="checkbox" id="delete-modal-{{$column['id']}}"
                                                       class="modal-toggle hidden"/>
                                                <div class="modal" role="dialog">
                                                    <div class="modal-box">
                                                        <h3 class="font-bold text-lg">{{__('ledger.column.remove')}}</h3>
                                                        <p class="py-4">{{__('ledger.column.remove_message',['name'=>$column['name']])}}</p>
                                                        <p class="text-lg text-bold text-error">{{__('ledger.column.will_ledger_delete_message')}}</p>
                                                        <div class="modal-action">
                                                            <label for="delete-modal-{{$column['id']}}"
                                                                   wire:click.prevent="removeColumn({{$index}})"
                                                                   class="btn btn-error">{{__('ledger.column.remove')}}</label>
                                                            <label for="delete-modal-{{$column['id']}}"
                                                                   class="btn btn-outline ml-5">{{__('actions.cancel')}}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </x-slot>
                            </x-mary-collapse>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        </form>
        <div class="mt-6 flex justify-between items-center">
            <button type="button" wire:click="addColumn" class="btn btn-outline btn-secondary btn-sm">
                <i class="fa-solid fa-plus-circle mr-1"></i>
                {{__('ledger.column.add')}}
            </button>
            @if($isDirty)
                <x-mary-button label="{{__('actions.save')}}"
                               type="button"
                               wire:click="save"
                               icon="o-pencil-square"
                               class="btn-primary"
                               spinner="save"
                />
            @else
                <x-mary-button label="{{__('actions.save')}}"
                               type="button"
                               wire:click="save"
                               icon="o-pencil-square"
                               class="btn-primary"
                               disabled
                />
            @endif
        </div>
</div>

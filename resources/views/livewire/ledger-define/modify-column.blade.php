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
                                             class="bg-base-200 text-primary-content opacity-50 hover:opacity-100 focus-within:opacity-100 transition-opacity duration-500 ease-in-out"
                                             wire:key="collapse-{{ $column['id'] }}"
                                             x-on:mouseenter="updateBackground('{{ $column['id'] }}')"
                                             x-on:focusin="updateBackground('{{ $column['id'] }}')"
                            >
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

                                                @php
                                                    $displayLevelOptions = array_map(function($value, $name) {
                                                        return ['id' => $value, 'name' => $name];
                                                    }, array_keys(__('ledger.form.display_level_options')), array_values(__('ledger.form.display_level_options')));
                                                @endphp
                                                <x-mary-select label="{{__('ledger.form.display_level')}}"
                                                               icon="o-list-bullet"
                                                               wire:model.live="columns.{{$index}}.display_level"
                                                               wire:key="display-level-{{$column['id']}}"
                                                               :options="$displayLevelOptions"
                                                               class="input-accent" required/>

                                                <x-mary-choices label="{{__('ledger.form.group_name')}}"
                                                                placeholder="{{__('ledger.form.group_name')}}"
                                                                icon="o-folder"
                                                                wire:model.live="columns.{{$index}}.group"
                                                                wire:key="group-{{$column['id']}}"
                                                                livewire-search="search"
                                                                clearable
                                                                single
                                                                searchable
                                                                search-function="searchGroups"
                                                                debounce="500ms"
                                                                allow-create
                                                                :options="$groupNames"
                                                                option-value="id"
                                                                option-label="name"
                                                                class="input-accent"/>

                                                <hr/>
                                                <x-mary-checkbox label="{{__('ledger.column.required')}}"
                                                                 wire:model.live="columns.{{$index}}.required"
                                                                 wire:key="required-{{$column['id']}}"/>
                                                <x-mary-checkbox label="{{__('ledger.column.unique')}}"
                                                                 wire:model.live="columns.{{$index}}.unique"
                                                                 wire:key="unique-{{$column['id']}}"/>
                                                <x-mary-checkbox label="{{__('ledger.column.sort')}}"
                                                                 wire:model.live="columns.{{$index}}.sortBy"
                                                                 wire:key="sortBy-{{$column['id']}}"/>
                                            </div>

                                            <div class="basis-1/2 space-y-4 m-3">
                                                <x-mary-textarea label="{{__('ledger.column.hint')}}"
                                                                 wire:model.live="columns.{{$index}}.hint"
                                                                 class="input-accent w-full"
                                                                 wire:key="hint-{{$column['id']}}"/>

                                                @include('livewire.ledger-define.partials.column-options', [
                                                    'column' => $column,
                                                    'index' => $index,
                                                    'columnUploadedFile' => $columnUploadedFile
                                                ])

                                                <div class="mt-3 flex items-center justify-end w-full space-x-2">
                                                    @include('livewire.ledger-define.partials.save-button', [
                                                        'isDirty' => $isDirty,
                                                        'label' => __('actions.save'),
                                                        'wireClick' => "saveColumn({$index})",
                                                        'spinner' => "saveColumn({$index})",
                                                        'class' => 'btn-primary btn-sm'
                                                    ])
                                                    <label for="delete-modal-{{$column['id']}}"
                                                           class="btn btn-outline btn-sm btn-error ml-10 justify-self-end">
                                                        <i class="fa-solid fa-trash mr-1"></i> {{__('ledger.column.remove')}}
                                                    </label>
                                                </div>

                                                @include('livewire.ledger-define.partials.delete-column-modal', [
                                                    'column' => $column,
                                                    'index' => $index
                                                ])
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
        @include('livewire.ledger-define.partials.save-button', [
            'isDirty' => $isDirty,
            'label' => __('actions.save'),
            'type' => 'button'
        ])
    </div>
</div>

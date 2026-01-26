<div>
    <x-element.loading-overlay tier="1" target="save,addColumn,removeColumn" />

    {{-- Tier 1 Skeleton Loader --}}
    <div wire:loading.delay wire:target="save,addColumn,removeColumn" class="flex-col gap-4 mt-4 w-full px-4">
        <x-element.skeleton-input-form rows="4" />
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <x-element.skeleton-card />
            <x-element.skeleton-card />
            <x-element.skeleton-card />
        </div>
    </div>

    <div wire:loading.delay.remove wire:target="save,addColumn,removeColumn">
        {{-- Parent window reload script (no changes needed) --}}
    <script>
        window.addEventListener('reloadParentWindow', () => {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
        });
    </script>

    <div x-data="{
             isDirty: @js($isDirty),
             allExpanded: false,
             expandAll() { $dispatch('toggle-columns', { open: true }) },
             collapseAll() { $dispatch('toggle-columns', { open: false }) }
         }"
         x-init="$watch('allExpanded', val => val ? expandAll() : collapseAll())"
         @is-dirty-changed.window="isDirty = $event.detail.isDirty">
        <form wire:submit.prevent="save">
            {{-- 操作ツールバー (Issue #53) --}}
            @if(!empty($columns))
                <div class="flex flex-wrap justify-between items-center mb-4 gap-2 bg-base-100 p-2 rounded-xl border border-base-300 shadow-sm sticky top-0 z-20 backdrop-blur-md bg-opacity-90">
                    <div class="flex items-center gap-2 px-1">
                        <div class="badge badge-md py-3 gap-1 bg-primary/5 text-primary border-primary/20">
                            <x-mary-icon name="o-queue-list" class="w-3 h-3" />
                            <span class="font-bold ">{{ count($columns) }}</span>
                            <span class="text-xs opacity-90">{{ __('ledger.column.count_unit')  }}</span>
                        </div>

                        @if($isDirty)
                            <div class="flex items-center gap-1 text-warning font-black text-xs animate-pulse bg-warning/5 px-2 py-1 rounded-full border border-warning/20">
                                <x-mary-icon name="o-exclamation-triangle" class="w-3 h-3" />
                                {{ __('ledger.changed') }}
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- 追加ボタン (ツールバー) --}}
                        <x-mary-button wire:click="addColumn" icon="o-plus" class="btn-ghost  text-primary font-bold" label="{{__('ledger.column.add')}}" />

                        {{-- 一括開閉管理トグル --}}
                        <div class="flex items-center gap-2 bg-base-200/50 px-2 py-1 rounded-lg border border-base-300">
                            <span class="font-black text-xs text-base-content/40 uppercase tracking-widest">{{ __('ledger.column.expand_all') }}</span>
                            <x-mary-toggle x-model="allExpanded" right tight class="toggle-xs toggle-primary" />
                        </div>

                        {{-- メイン保存ボタン (フロー可能) --}}
                        @include('livewire.ledger-define.partials.save-button', [
                            'isDirty' => $isDirty,
                            'label' => __('actions.save'),
                            'type' => 'button',
                            'class' => 'btn-primary px-4 shadow-md'
                        ])
                    </div>
                </div>

                <ul wire:sortable="updateColumnOrder" wire:sortable.options="{ 'animation': 500 }" class="space-y-3">
                    @foreach($columns as $index => $column)
                        <li wire:sortable.item="{{ $column['id'] }}" wire:key="column-{{ $column['id'] }}" class="z-10">
                            <div class="flex items-start gap-1">
                                {{-- ドラッグハンドル --}}
                                <button wire:sortable.handle class="btn btn-ghost btn-sm text-base-content/30 hover:text-primary cursor-grab active:cursor-grabbing mt-[13px]"
                                        data-tip="{{__('ledger.column.drag2sort')}}">
                                    <x-mary-icon name="o-bars-3" class="w-5 h-5" />
                                </button>

                                {{-- DaisyUI Collapse with Alpine integration --}}
                                <div class="collapse collapse-arrow bg-base-100 border border-base-300 shadow-sm hover:shadow-md transition-all duration-300 w-full rounded-lg overflow-hidden group"
                                     wire:key="collapse-{{ $column['id'] }}"
                                     x-data="{
                                         isOpen: false,
                                         toggle() {
                                             this.isOpen = !this.isOpen;
                                         }
                                     }"
                                     x-on:toggle-columns.window="isOpen = $event.detail.open"
                                     :class="{ 'collapse-open': isOpen, 'collapse-close': !isOpen, 'border-primary/20 ring-1 ring-primary/5': isOpen }">

                                    <div class="collapse-title text-base font-bold cursor-pointer flex items-center gap-2 py-3 pr-10 min-h-0 bg-base-100 group-hover:bg-base-200/20"
                                         @click="toggle()">
                                        <span class="badge badge-outline badge-sm font-mono text-base-content/40 border-base-300 group-hover:border-primary/20 transition-colors">{{ $column['id'] }}</span>
                                        <span class="grow truncate">{{ $column['name'] }}</span>
                                        <div class="hidden sm:flex items-center gap-2">
                                            <span class="badge badge-ghost badge-sm py-1 px-2 opacity-70 group-hover:opacity-100 transition-opacity">
                                                {{ $columnInputTypes[$column['type']] ?? $column['type'] }}
                                            </span>
                                            @if($column['required'])
                                                <span class="badge badge-primary badge-xs p-1 tooltip tooltip-left" data-tip="{{ __('ledger.column.required') }}"></span>
                                            @endif
                                            @if($column['unique'])
                                                <span class="badge badge-secondary badge-xs p-1 tooltip tooltip-left" data-tip="{{ __('ledger.column.unique') }}"></span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="collapse-content border-t border-base-200/50 bg-base-200/5 px-3 pb-3">
                                        <div class="pt-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            <div class="space-y-4">
                                                <div class="form-section">
                                                    <h4 class="text-xs font-black text-base-content/30 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                                                        <x-mary-icon name="o-cog-6-tooth" class="w-3 h-3" />
                                                        {{ __('ledger.define.basic_setting') }}
                                                    </h4>
                                                    <div class="space-y-3/4">
                                                        <x-mary-input label="{{__('ledger.column.title')}}"
                                                                      placeholder="{{__('ledger.column.title')}}"
                                                                      icon="o-pencil-square"
                                                                      required
                                                                      wire:model.change="columns.{{$index}}.name"
                                                                      wire:key="name-{{$column['id']}}" class="input-bordered focus:input-primary"/>

                                                        @php
                                                            $typeOptions = array_map(function($value, $name) {
                                                                return ['id' => $value, 'name' => $name];
                                                            }, array_keys($columnInputTypes), array_values($columnInputTypes));
                                                        @endphp
                                                        <x-mary-select label="{{__('ledger.column.type')}}"
                                                                       icon="o-beaker"
                                                                       wire:model.live="columns.{{$index}}.type"
                                                                       wire:key="type-{{$column['id']}}" :options="$typeOptions"
                                                                       class="select-bordered focus:select-primary" required/>

                                                        @php
                                                            $displayLevelOptions = array_map(function($value, $name) {
                                                                return ['id' => $value, 'name' => $name];
                                                            }, array_keys(__('ledger.form.display_level_options')), array_values(__('ledger.form.display_level_options')));
                                                        @endphp
                                                        <x-mary-select label="{{__('ledger.form.display_level')}}"
                                                                       icon="o-eye"
                                                                       wire:model.live="columns.{{$index}}.display_level"
                                                                       wire:key="display-level-{{$column['id']}}"
                                                                       :options="$displayLevelOptions"
                                                                       class="select-bordered focus:select-primary" required/>

                                                        <x-mary-choices label="{{__('ledger.form.group_name')}}"
                                                                        placeholder="{{__('ledger.form.group_name')}}"
                                                                        icon="o-tag"
                                                                        wire:model.change="columns.{{$index}}.group"
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
                                                                        class="choices-compact focus:ring-primary"/>
                                                    </div>
                                                </div>

                                                <div class="divider opacity-30"></div>

                                                <div class="grid grid-cols-2 gap-4">
                                                    <x-mary-checkbox label="{{__('ledger.column.required')}}"
                                                                     wire:model.live="columns.{{$index}}.required"
                                                                     wire:key="required-{{$column['id']}}"
                                                                     class="checkbox-primary"/>
                                                    <x-mary-checkbox label="{{__('ledger.column.unique')}}"
                                                                     wire:model.live="columns.{{$index}}.unique"
                                                                     wire:key="unique-{{$column['id']}}"
                                                                     class="checkbox-secondary"/>
                                                </div>

                                                <x-mary-input label="{{__('ledger.column.sort_index')}}"
                                                              placeholder="1 ({{__('ledger.column.sort_priority_example')}})"
                                                              icon="o-arrows-up-down"
                                                              type="number"
                                                              min="1"
                                                              wire:model.live="columns.{{$index}}.sort_index"
                                                              wire:key="sortIndex-{{$column['id']}}" class="input-bordered"/>
                                            </div>

                                            <div class="space-y-5">
                                                <div class="form-section">
                                                    <h4 class="text-xs font-black text-base-content/40 uppercase tracking-widest mb-4 flex items-center gap-2">
                                                        <x-mary-icon name="o-information-circle" class="w-3 h-3" />
                                                        {{ __('ledger.column.hint') }} & {{ __('ledger.options') }}
                                                    </h4>
                                                    <div class="space-y-4">
                                                        <x-mary-textarea label="{{__('ledger.column.hint')}}"
                                                                         placeholder="{{ __('ledger.column.hint') }}..."
                                                                         wire:model.live="columns.{{$index}}.hint"
                                                                         class="textarea-bordered focus:textarea-primary w-full h-24"
                                                                         wire:key="hint-{{$column['id']}}"/>

                                                        @include('livewire.ledger-define.partials.column-options', [
                                                            'column' => $column,
                                                            'index' => $index,
                                                            'columnUploadedFile' => $columnUploadedFile
                                                        ])
                                                    </div>
                                                </div>

                                                <div class="mt-8 flex items-center justify-between gap-4 p-4 rounded-xl bg-base-300/20 border border-base-300/50">
                                                    <label for="delete-modal-{{$column['id']}}"
                                                           class="btn btn-ghost btn-sm text-error/60 hover:text-error hover:bg-error/10">
                                                        <x-mary-icon name="o-trash" class="w-4 h-4 mr-1" />
                                                        {{__('ledger.column.remove')}}
                                                    </label>

                                                    @include('livewire.ledger-define.partials.save-button', [
                                                        'isDirty' => $isDirty,
                                                        'label' => __('actions.save'),
                                                        'wireClick' => "saveColumn({$index})",
                                                        'spinner' => "saveColumn({$index})",
                                                        'class' => 'btn-primary btn-sm px-6 shadow-sm'
                                                    ])
                                                </div>

                                                @include('livewire.ledger-define.partials.delete-column-modal', [
                                                    'column' => $column,
                                                    'index' => $index
                                                ])
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

        </form>
        <div class="mt-10 flex justify-between items-center border-t border-base-300 pt-8">
            <x-mary-button type="button" wire:click="addColumn" icon="o-plus-circle" class="btn-outline btn-secondary" label="{{__('ledger.column.add')}}" />

            <div class="flex items-center gap-4">
                @if($isDirty)
                    <span class="text-xs font-bold text-warning animate-pulse flex items-center gap-1">
                        <x-mary-icon name="o-exclamation-circle" class="w-4 h-4" />
                        {{ __('ledger.changed') }}
                    </span>
                @endif
                @include('livewire.ledger-define.partials.save-button', [
                    'isDirty' => $isDirty,
                    'label' => __('actions.save'),
                    'type' => 'button',
                    'class' => 'btn-primary btn-md px-10 shadow-lg'
                ])
            </div>
        </div>
    </div> {{-- End of x-data div --}}
</div>
</div>

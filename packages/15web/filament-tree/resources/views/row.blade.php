@php
    $modelClass = $component::getModel();
    if($modelClass instanceof \Kalnoy\Nestedset\QueryBuilder) {
        $modelClass = $modelClass->getModel()::class;
    }
@endphp

<li class="tree-node-{{ $row->depth }}" data-id="{{ $row->getKey() }}" data-depth="{{ $row->depth }}" style="padding-left: {{ (1 + $row->depth) * 1.5 }}rem;">
    <div class="flex flex-row items-center rounded-xl shadow-sm ring-1 dark:ring-gray-950/5 ring-gray-950/5 p-3 gap-4">
        <div class="tree-row-draggable flex flex-row items-center grow gap-4">
            <div class="tree-row-handler flex flex-row items-center ps-2">
                @if($row->children->isNotEmpty())
                    <span class="pe-2">
                        <x-filament::icon
                            icon="heroicon-o-chevron-{{ $collapsed ? 'right' : 'down' }}"
                            class="cursor-pointer h-5 w-5 text-gray-400 dark:text-gray-400"
                            wire:click="$toggle('collapsed')"
                        />
                    </span>
                @endif
                <span class="tree-row-drag-handle pe-2 inline-flex items-center justify-center rounded-md p-2">
                    <x-filament::icon
                        icon="heroicon-o-ellipsis-vertical"
                        class="h-5 w-5 text-gray-400 dark:text-gray-400 pointer-events-none"
                    />
                </span>
            </div>
            <div class="tree-row-info sm:flex flex-col sm:flex-row items-start sm:items-center grow ps-2 gap-3">
                <div class="flex flex-col grow py-2 sm:py-0">
                    <div class="text-gray-700 dark:text-gray-200">
                        {{ $row->getAttribute($modelClass::getTreeLabelAttribute()) }}
                    </div>
                    @if(method_exists($row, 'getTreeCaption'))
                        <div class="text-gray-400 dark:text-gray-400 text-sm">
                            {{ $row->getTreeCaption() }}
                        </div>
                    @endif
                </div>
                <div class="pe-3">
                    {{ $this->infolist }}
                </div>
            </div>
        </div>
        <div class="tree-row-actions flex flex-row items-center gap-x-2 pe-2">
            <div>{{ ($this->editAction)(['id' => $row->getKey()]) }}</div>
            @if($this->canBeDeleted)
                <div>{{ ($this->deleteAction)(['id' => $row->getKey()]) }}</div>
            @endif
        </div>
    </div>

    <ul class="studio15-tree" data-id="{{ $row->getKey() }}">
        @if(!$collapsed)
            @foreach($row->children->sortBy(Kalnoy\Nestedset\NestedSet::LFT) as $child)
                <livewire:filament-tree::row
                        :component="$component"
                        :row="$child"
                        :row-id="$child->getKey()"
                        :key="Studio15\FilamentTree\Helpers::treeKey($child)"
                />
            @endforeach
        @endif
    </ul>

    <x-filament-actions::modals/>
</li>
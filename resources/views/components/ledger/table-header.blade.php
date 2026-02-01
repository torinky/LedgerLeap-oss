@props([
    'ledgerDefine' => null,
    'orderBy' => 'id',
    'orderAsc' => false,
    'filteredColumnDefines' => [],
    'defaultSortColumns' => [],
])
<tr class="hover z-30" wire:key="ledger_table_header_{{ $ledgerDefine->id }}">
    {{-- アクションボタン用の列 --}}
    <th class="w-10 text-center px-4 py-2 tracking-wider bg-accent/30 bg-opacity-30">
        @if (!empty($defaultSortColumns) && $orderBy !== 'default')
            {{-- デフォルト順に戻すボタン --}}
            <x-mary-button wire:click="$parent.sort('default')" icon="o-arrow-path" class="btn btn-square btn-warning"
                spinner="$parent.sort" />
        @else
            {{-- 通常のアクションアイコン --}}
            <div class="tooltip tooltip-right z-50" data-tip="{{ __('actions.actions') }}">
                <i class="fas fa-cogs"></i>
            </div>
        @endif
    </th>

    @foreach ($filteredColumnDefines as $cKey => $column_define)
        @php
            $sortIndex = $column_define->sort_index;
            $isSorted = $orderBy === 'content->' . (string) $column_define->id;
            $highlightClass = 'bg-accent/30';

            if ($isSorted) {
                // 手動ソート時
                $highlightClass = 'bg-primary/40 border-b-2 border-primary';
            } elseif ($orderBy === 'default' && $sortIndex !== null) {
                // デフォルトソート時
                $opacity = match ($sortIndex) {
                    1 => '20',
                    2 => '10',
                    default => '5',
                };
                $highlightClass = "bg-primary/{$opacity} border-b-2 border-primary";
            }
        @endphp
        <th class="px-4 py-2 space-y-1 text-center {{ $highlightClass }}"
            wire:key="ledger_table_header_{{ $ledgerDefine->id }}_column_{{ $column_define->id }}">
            <div
                @if ($orderBy === 'default' && $sortIndex !== null) class="tooltip" data-tip="{{ __('ledger.sort_priority') }}: {{ $sortIndex }}" @endif>
                <span class=" text-accent-content font-bold">
                    {{ $column_define->name }}
                </span>
                <button class="btn btn-xs"
                    wire:key="ledger_sort_id_{{ $ledgerDefine->id }}_column_{{ $column_define->id }}"
                    wire:click.stop="$parent.sort('content->{{ (string) $column_define->id }}', '{{ $column_define->name }}')"
                    wire:loading.attr="disabled" wire:target="$parent.sort">
                    @if ($orderBy == 'content->' . (string) $column_define->id)
                        @if ($orderAsc)
                            <i class="fas fa-sort-down" style="pointer-events: none;"></i>
                        @else
                            <i class="fas fa-sort-up" style="pointer-events: none;"></i>
                        @endif
                    @else
                        <i class="fas fa-sort opacity-30" style="pointer-events: none;"></i>
                    @endif
                </button>
            </div>
            <x-mary-input
                    x-on:input.debounce.500ms="$wire.$parent.updateFilterFromChild('{{$column_define->id}}', $event.target.value, {{$ledgerDefine->id}})"
                    value="{{ $this->filter[$column_define->id] ?? '' }}"
                    wire:key="ledger_filter_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                    type="search"
                    class="input-xs w-full max-w-xs"
                    icon="o-funnel"
                    spinner="$wire.$parent.updateFilterFromChild"
                    placeholder="{{__('ledger.filter')}}..." />
        </th>
    @endforeach

    @php
        $isUpdatedAtSorted = $orderBy === 'updated_at';
        $updatedAtHeaderClass = $isUpdatedAtSorted ? 'bg-primary/40 border-b-2 border-primary' : 'bg-accent/30';
    @endphp
    <th class="px-4 py-2 text-center {{ $updatedAtHeaderClass }}">
        <span class="text-sm font-bold text-accent-content">
            {{ __('ledger.updated_at') }}
        </span>
        <button class="btn btn-xs" wire:click.stop="$parent.sort('updated_at', '{{ __('ledger.updated_at') }}')"
            wire:loading.attr="disabled" wire:target="$parent.sort">
            @if ($orderBy == 'updated_at')
                @if ($orderAsc)
                    <i class="fas fa-sort-down" style="pointer-events: none;"></i>
                @else
                    <i class="fas fa-sort-up" style="pointer-events: none;"></i>
                @endif
            @else
                <i class="fas fa-sort opacity-30" style="pointer-events: none;"></i>
            @endif
        </button>
    </th>
</tr>

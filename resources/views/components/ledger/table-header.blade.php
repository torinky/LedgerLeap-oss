@props([
    'ledgerDefine'=>null,
    'orderBy'=>'id',
    'orderAsc'=>false,
    'filteredColumnDefines' => [],
    ])
<tr class="hover"
    wire:key="ledger_table_header_{{$ledgerDefine->id}}"
>
    {{-- アクションボタン用の列 --}}
    <th class="w-10 text-center px-4 py-2 tracking-wider bg-accent bg-opacity-30">
        <div class="tooltip tooltip-right z-50" data-tip="{{ __('ledger.actions') }}">
            <i class="fas fa-cogs"></i>
        </div>
    </th>

    @foreach($filteredColumnDefines as $cKey=>$column_define)
        <td class="px-4 py-2 space-y-1 text-center bg-accent bg-opacity-30"
            wire:key="ledger_table_header_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
        >
            <span class=" text-base font-bold">
                {{$column_define->name}}
            </span>
            <button class="btn btn-xs"
                    wire:key="ledger_sort_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                    wire:click.self="sort('content->{{ (string)$column_define->id }}', '{{ $column_define->name }}')"
            >
                @if($orderBy == 'content->'.(string)$column_define->id)
                    @if($orderAsc)
                        <i class="fas fa-sort-down" style="pointer-events: none;"></i>
                    @else
                        <i class="fas fa-sort-up" style="pointer-events: none;"></i>
                    @endif
                @else
                    <i class="fas fa-sort opacity-30" style="pointer-events: none;"></i>
                @endif
            </button>
            <input
                    wire:change="focusLedgerDefine({{$ledgerDefine->id}})"
                    wire:model="filter.{{$column_define->id}}"
                    wire:key="ledger_filter_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                    type="search"
                    class="input input-bordered input-xs w-full max-w-xs flex flex-row icon-input"
                    placeholder="&#xf0b0; {{__('ledger.filter')}}...">
        </td>
    @endforeach

    <td class="px-4 py-2 text-center bg-accent bg-opacity-30">
        <span class="text-sm font-bold">
            {{__('ledger.updated_at')}}
        </span>
        <button href="#" class="btn btn-xs" wire:click="sort('updated_at', '{{ __('ledger.updated_at') }}')">
            @if($orderBy == 'updated_at')
                @if($orderAsc)
                    <i class="fas fa-sort-down" style="pointer-events: none;"></i>
                @else
                    <i class="fas fa-sort-up" style="pointer-events: none;"></i>
                @endif
            @else
                <i class="fas fa-sort opacity-30" style="pointer-events: none;"></i>
                @endif

        </button>
    </td>
</tr>

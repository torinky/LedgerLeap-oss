<tr class="hover"
    wire:key="ledger_table_header_{{$ledgerDefine->id}}"
>
    <th class="text-center px-4 py-2 px-0 tracking-wider bg-accent bg-opacity-30">
        <button class="btn btn-xs"
                wire:click.self="sort('id')"
                wire:key="ledger_id_sort_{{$ledgerDefine->id}}"
        >
            @if($orderBy == 'id')
                @if($orderAsc)
                    <i class="fas fa-sort-up"></i>
                @else
                    <i class="fas fa-sort-down"></i>
                @endif
            @else
                <i class="fas fa-sort opacity-30"></i>
            @endif
        </button>

    </th>
    @foreach($ledgerDefine->column_define as $cKey=>$column_define)
        <td class="px-4 py-2 space-y-1 text-center bg-accent bg-opacity-30"
            wire:key="ledger_table_header_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
        >
            <span class=" text-base font-bold">
                {{$column_define->name}}
            </span>
            <button class="btn btn-xs"
                    wire:key="ledger_sort_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                    wire:click.self="sort('content->{{ (string)$column_define->id }}')"
            >
                @if($orderBy == 'content->'.(string)$column_define->id)
                    @if($orderAsc)
                        <i class="fas fa-sort-down"></i>
                    @else
                        <i class="fas fa-sort-up"></i>
                    @endif
                @else
                    <i class="fas fa-sort opacity-30"></i>
                @endif
            </button>
            <input
                wire:change="focusLedgerDefine({{$ledgerDefine->id}})"
                wire:model="filter.{{$column_define->id}}"
                wire:key="ledger_filter_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                type="text"
                class="input input-bordered input-xs w-full max-w-xs flex flex-row"
                placeholder="{{__('ledger.filter')}}...">
        </td>
    @endforeach

    <td class="px-4 py-2 text-center bg-accent bg-opacity-30">
        <span class="text-sm font-bold">
            {{__('ledger.updated_at')}}
        </span>
        <button href="#" class="btn btn-xs" wire:click="sort('updated_at')">
            @if($orderBy == 'updated_at')
                @if($orderAsc)
                    <i class="fas fa-sort-down"></i>
                @else
                    <i class="fas fa-sort-up"></i>
                @endif
            @else
                <i class="fas fa-sort opacity-30"></i>
            @endif

        </button>
    </td>
</tr>

<tr class="hover"
    wire:key="ledger_table_header_{{$ledgerDefine->id}}"
>
    <th class="text-center px-4 py-2 px-0 tracking-wider"
        wire:click.self="sort('id')"
        wire:key="ledger_id_sort_{{$ledgerDefine->id}}"
    >
        @if($orderBy == 'id')
            @if($orderAsc)
                <i class="fas fa-chevron-up"></i>
            @else
                <i class="fas fa-chevron-down"></i>
            @endif
        @endif
    </th>
    @foreach($ledgerDefine->column_define as $cKey=>$column_define)
        <td class="px-4 py-2 space-y-1 text-center"
            wire:key="ledger_table_header_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
        >
            <a class="btn btn-ghost text-base font-bold"
               wire:key="ledger_sort_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
               wire:click.self="sort('content->{{ (string)$column_define->id }}')"
            >
                {{$column_define->name}}
                @if($orderBy == 'content->'.(string)$column_define->id)
                    @if($orderAsc)
                        <i class="fas fa-chevron-down"></i>
                    @else
                        <i class="fas fa-chevron-up"></i>
                    @endif
                @endif
            </a>
            <input
                wire:change="focusLedgerDefine({{$ledgerDefine->id}})"
                wire:model="filter.{{$column_define->id}}"
                wire:key="ledger_filter_id_{{$ledgerDefine->id}}_column_{{$column_define->id}}"
                type="text"
                class="input input-bordered input-sm w-full max-w-xs flex flex-row"
                placeholder="Search {{$column_define->name}}...">
        </td>
    @endforeach

    <td class="px-4 py-2 text-center">
        <a href="#" class="btn btn-ghost text-sm font-bold" wire:click="sort('updated_at')">
            {{__('ledger.updated_at')}}
            @if($orderBy == 'updated_at')
                @if($orderAsc)
                    <i class="fas fa-chevron-down"></i>
                @else
                    <i class="fas fa-chevron-up"></i>
                @endif
            @endif

        </a>
    </td>
</tr>

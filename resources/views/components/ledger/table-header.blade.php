@props([
    'ledgerDefine'=>null,
    'orderBy'=>'id',
    'orderAsc'=>false,
    'filteredColumnDefines' => [],
    ])
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
    {{-- 複合スコア列 --}}
    <th class="text-center px-4 py-2 tracking-wider bg-accent bg-opacity-30">
        <span class="text-sm font-bold">{{ __('ledger.scoring.composite_score') }}</span>
        <button class="btn btn-xs"
                wire:click.self="sort('composite_score')"
                wire:key="ledger_composite_score_sort_{{$ledgerDefine->id}}"
        >
            @if($orderBy == 'composite_score')
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
    @foreach($filteredColumnDefines as $cKey=>$column_define)
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
                    type="search"
                    class="input input-bordered input-xs w-full max-w-xs flex flex-row icon-input"
                    placeholder="&#xf0b0; {{__('ledger.filter')}}...">
        </td>
    @endforeach

    {{-- ステータス列ヘッダー --}}
    @if($ledgerDefine->workflow_enabled)
        <th class="px-4 py-2 text-center bg-accent bg-opacity-30">
            <span class="text-sm font-bold">{{ __('ledger.workflow.status.label') }}</span>
            {{-- 必要ならソートボタン --}}
            {{-- <button class="btn btn-xs" wire:click.self="$parent.sort('status')">...</button> --}}
            {{-- 必要ならフィルタ (Select) --}}
            {{-- <select wire:model.live="$parent.filterStatus" class="select select-xs ...">...</select> --}}
        </th>
    @endif

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

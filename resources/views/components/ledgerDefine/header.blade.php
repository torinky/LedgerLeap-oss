@props([
    'canCreate'=>false,
    'canView'=>false,
    'canManage'=>false,
    'ledgerDefine'=>null,
    'breadcrumbsPerLedgerDefine'=>[],
    'keywords'=>[],
    'filter'=>[],
])
<div
    class="flex flex-row justify-content-between items-center bg-base-300 mt-0 px-4 text-sm rounded-t-box text-base-content/70 ">
    <h3 class="text-2xl font-medium leading-tight text-primary space-x-3 my-2">
        <span><i class="fa-solid fa-book-open mr-2"></i>{{$ledgerDefine->title}}</span>
    </h3>
        <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefine->id]"
        />
    <div class="flex-grow text-right">
        <a href="#" class="btn btn-square btn-xs tooltip items-center pt-1"
           data-tip="{{__('ledger.close')}}"
           wire:click="toggleLedgerDefineId({{ $ledgerDefine->id }})"
        ><i class="fas fa-times"></i></a>
    </div>
</div>
<x-markdown class="prose text-xs leading-relaxed w-full max-w-none px-4">
    {!! $ledgerDefine->list_description !!}
</x-markdown>

<div class="grid justify-items-end mx-4">

    <div class="flex flex-row  space-x-2 place-items-center">
        @if($canCreate)
            <a href="{{ route('ledger.create', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
               class="btn btn-neutral relative inline-flex w-48 "
               target="ledgerCreate_{{$ledgerDefine->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
                {{__('ledger.create')}}
            </a>
        @else
            <div class="tooltip" data-tip="{{ __('ledger.not_allow_create') }}">
                <button class="btn btn-neutral relative inline-flex w-48 " disabled>
                    <i class="fas fa-circle-plus mr-1"></i>
                    {{__('ledger.create')}}
                </button>
            </div>
        @endif
            @if($canView)
                <livewire:ledger.export :ledgerDefineId="$ledgerDefine->id"
                                        :$keywords
                                        :$filter
                                        key="{{Hash::make('ledger_export-'. $ledgerDefine->id)}}"
                />
            @else
                <div class="tooltip" data-tip="{{ __('ledger.no_view_permission') }}">
                    <button class="btn btn-outline btn-secondary w-48" disabled>
                        <i class="fas fa-file-csv"></i>
                        {{__('ledger.export_csv')}}
                    </button>
                </div>
            @endif
        <div class="w-6"></div>
            @if($canManage)
                <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
                   class="btn btn-outline btn-primary btn-sm relative inline-flex"
                   target="ledgerDefineEdit_{{$ledgerDefine->id}}}}">
                    <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                </a>
            @else
                <div class="tooltip" data-tip="{{ __('ledger.no_manage_permission') }}">
                    <button class="btn btn-outline btn-primary btn-sm relative inline-flex" disabled>
                        <i class="fas fa-gears mr-1"></i> {{__('ledger.setting')}}
                    </button>
                </div>
            @endif

    </div>
</div>
{{--    <div class="flex flex-row">--}}
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerDefine->id"
                                     key="{{Hash::make('ledger_define_tag-'. $ledgerDefine->id)}}"
        />
{{--    </div>--}}

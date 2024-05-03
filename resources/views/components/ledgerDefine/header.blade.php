<div class="flex flex-row bg-base-300 mt-0 px-4 text-sm rounded-t-box">
    <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefine->id]"
    />
</div>
<h3 class=" flex flex-row items-center text-3xl font-medium leading-tight text-primary space-x-3 mx-4">
    <i class="fa-solid fa-book-open"></i>
    <span>{{$ledgerDefine->title}}</span>
</h3>
<div class="grid justify-items-end mx-4">

    <div class="flex flex-row  space-x-2">
        <a href="{{ route('ledger.create', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
           class="btn btn-primary btn-sm relative inline-flex w-48"
           target="ledgerCreate_{{$ledgerDefine->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
            {{__('ledger.create')}}</a>
        {{--
                <a href="{{ route('ledger.downloadExcelCSV', [
                            'ledgerDefineId' => $ledgerDefine->id,
                            'keyword' =>  $search, 'filter' => http_build_query( $filter)]) }}"
                   class="btn btn-outline btn-secondary btn-sm relative inline-flex"
                >{{__('ledger.export_csv')}}</a>
        --}}
        <livewire:ledger.export :ledgerDefineId="$ledgerDefine->id"
                                :$keywords
                                :$filter
                                key="{{Hash::make('ledger_export-'. $ledgerDefine->id)}}"
        />
        <div class="w-6"></div>
        <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
           class="btn btn-outline btn-primary btn-sm relative inline-flex"
           target="ledgerDefineEdit_{{$ledgerDefine->id}}}}"><i
                class="fas fa-gears mr-1"></i> {{__('ledger.settings')}}</a>

    </div>
</div>
{{--    <div class="flex flex-row">--}}
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerDefine->id"
                                     key="{{Hash::make('ledger_define_tag-'. $ledgerDefine->id)}}"
        />
{{--    </div>--}}

<div class="flex flex-row ">
    <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefine->id]"
    />
</div>
<h3 class=" flex flex-row items-center text-3xl font-medium leading-tight text-primary space-x-3">
    <i class="fa-solid fa-book-open"></i>
    <span>{{$ledgerDefine->title}}</span>
</h3>
<div class="grid justify-items-end">

    <div class="flex flex-row  space-x-2">
        <a href="{{ route('ledger.create', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
           class="btn btn-primary btn-sm relative inline-flex w-32"
           target="ledgerCreate_{{$ledgerDefine->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
            {{__('Create')}}</a>
        <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefine->id]) }}"
           class="btn btn-outline btn-primary btn-sm relative inline-flex w-32"
           target="ledgerDefineEdit_{{$ledgerDefine->id}}}}"><i
                class="fas fa-gears mr=1"></i> {{__('Settings')}}</a>
        <a href="{{ route('ledger.downloadExcelCSV', [
                    'ledgerDefineId' => $ledgerDefine->id,
                    'keyword' =>  $search, 'filter' => http_build_query( $filter)]) }}"
           class="btn btn-outline btn-secondary btn-sm relative inline-flex"
        >{{__('Export CSV')}}</a>
        <livewire:ledger.export :ledgerDefineId="$ledgerDefine->id"
                                :$keywords
                                :$filter
                                key="{{Hash::make('ledger_export-'. $ledgerDefine->id)}}"
        />

    </div>
    <div class="flex flex-row">
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerDefine->id"
                                     key="{{Hash::make('ledger_define_tag-'. $ledgerDefine->id)}}"
        />
    </div>
</div>

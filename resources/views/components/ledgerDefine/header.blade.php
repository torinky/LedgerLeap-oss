<div class="flex flex-row ">
    <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerRecord->define->id]"
    />
</div>
<h3 class=" flex flex-row items-center text-3xl font-medium leading-tight text-primary space-x-3">
    <i class="fa-solid fa-book-open"></i>
    <span>{{$ledgerRecord->define->title}}</span>
</h3>
<div class="grid justify-items-end">

    <div class="flex flex-row  space-x-2">
        <a href="{{ route('ledger.create', ['ledgerDefineId'=>$ledgerRecord->define->id]) }}"
           class="btn btn-primary btn-sm relative inline-flex w-32"
           target="ledgerCreate_{{$ledgerRecord->define->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
            {{__('create')}}</a>
        <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerRecord->define->id]) }}"
           class="btn btn-outline btn-primary btn-sm relative inline-flex w-32"
           target="ledgerDefineEdit_{{$ledgerRecord->define->id}}}}"><i
                class="fas fa-gears mr=1"></i> {{__('setting')}}</a>
        <a href="{{ route('ledger.downloadExcelCSV', [
                    'ledgerDefineId' => $ledgerRecord->define->id,
                    'keyword' =>  $search, 'filter' => http_build_query( $filter)]) }}"
           class="btn btn-outline btn-secondary btn-sm relative inline-flex"
        >Download Excel CSV</a>
        <livewire:ledger.export :ledgerDefineId="$ledgerRecord->define->id"
                                :$keywords
                                :$filter
                                key="ledger_export-{{$ledgerRecord->id}}"
        />

    </div>
    <div class="flex flex-row">
        <livewire:ledger-define.tags :ledgerDefineId="$ledgerRecord->define->id"
                                     key="ledger_define_tag-{{$ledgerRecord->id}}"
        />
    </div>
</div>

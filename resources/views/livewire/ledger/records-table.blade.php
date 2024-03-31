<div>

    <x-ledger.search/>

    <x-ledger.livewire-breadcrumbs
        :breadcrumbs="$breadcrumbs"
    />

    {{--
        <div class="flex flex-row">
            <livewire:folder.tag :folderId="$currentFolderId" :wire:key="$currentFolderId"/>
        </div>
    --}}

    <x-folder.folder-and-ledger-panels
        :folderRecords="$folderRecords"
        :selectedFolderIds="$selectedFolderIds"
        :ledgerDefineRecords="$ledgerDefineRecords"
        :selectedLedgerDefineIds="$selectedLedgerDefineIds"
    />


    <div class="divider"></div>

    <div wire:loading class="w-full">
        <div class="flex flex-row justify-center ">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

    <div class="">
        @if($totalRecords > 0)
            <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center">
                <div class="card bg-base-300 opacity-70 hover:opacity-100">
                    <div class="card-body">
                        {!! $ledgerRecords->links('components.ledger.pagination-links',['position'=>'top']) !!}
                    </div>
                </div>
            </div>


            @foreach($ledgerRecordsGroupByDefineIds as $ledgerDefineId => $ledgerDefineAndRecords)
                <div class="card bg-base100 shadow-xl my-4" wire:key="ledger_record_{{$ledgerDefineId}}">
                    <div class="card-body">
                        <x-ledgerDefine.header
                            :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
                            :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine"
                            :search="$search"
                            :filter="$filter"
                            :keywords="$keywords"
                        />

                        <div class="overflow-x-auto max-h-screen" wire:key="ledgerDefine_block-{{$ledgerDefineId}}">
                            <table
                                class="relative table table-zebra table-compact table-auto table-pin-rows table-pin-cols max-h-fit">
                                <thead>
                                <x-ledger.table-header
                                    :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
                                    :orderBy="$orderBy"
                                    :orderAsc="$orderAsc"
                                />
                                </thead>
                                <tbody>
                                @foreach($ledgerDefineAndRecords as $ledgerRecordValues)
                                    <x-ledger.table-row
                                        :ledgerRecord="$ledgerRecordValues"
                                    />
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            @endforeach

    </div>
    {!! $ledgerRecords->links('components.ledger.pagination-links',['position'=>'bottom']) !!}
    @else
        {{--
                        <x-ledger.alert
                            message="{{__('Select Ledger or Folder')}}"
                            icon="fa-circle-info"
                            type="warning"
                            refreshParentWindow ={{false}}
                        />
        --}}
        @include('components.ledger.alert',[
            'message'=>__('Select Ledger or Folder'),
            'icon'=> 'fa-circle-info',
            'type'=>'warning',
            'refreshParentWindow'=>false,
        ])

    @endif
</div>

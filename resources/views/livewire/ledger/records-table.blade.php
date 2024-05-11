<div>
    <div wire:loading class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

    <x-ledger.search/>

    <div class="bg-base-300 text-base-content/70 rounded-box px-4 mb-4 font-bold ">
        <x-ledger.livewire-breadcrumbs
            :breadcrumbs="$breadcrumbs"
        />
    </div>

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
    @if(!empty($keywords))
        <div class="flex flex-row space-x-2">
            @foreach($keywords as $keyword)
                <span>{{__('ledger.searched')}}</span><span>...</span><span
                    class="badge badge-info badge-lg">{{$keyword}}</span>
            @endforeach

        </div>
    @endif

    <div class="">
        @if($totalRecords > 0)
            <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center">
                <div class="card bg-base-300 opacity-70 transition-opacity hover:opacity-100 shadow-lg">
                    <div class="card-body">
                        {!! $ledgerRecords->links('components.ledger.pagination-links',['position'=>'top']) !!}
                    </div>
                </div>
            </div>


            @foreach($ledgerRecordsGroupByDefineIds as $ledgerDefineId => $ledgerDefineAndRecords)
                <div class="card bg-base100 shadow-xl my-10" wire:key="ledger_record_{{$ledgerDefineId}}">
                    <div class="card-body pt-0 px-0">
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
                                        :keywords="$highlights"
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
            'message'=>__('ledger.select_message'),
            'icon'=> 'fa-hand-pointer',
            'type'=>'warning',
            'refreshParentWindow'=>false,
        ])

    @endif
</div>

<div>
    <div wire:loading class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>
    {{--   Dummy for CSS Build --}}
    <div class="hidden">
        <span class="badge badge-secondary bg-secondary/50 py-4 mx-1 my-1">dummy</span>
        <span class="badge badge-error  py-4 mx-1 my-1">dummy</span>
    </div>
    {{--   Dummy for CSS Build --}}

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
    <div class="info-block  sticky top-20 z-40 space-y-2">
        @if(!empty($highlights))
            <div class="space-x-2 flex  mr-10 rounded-box bg-base-100/80 px-2 justify-center">
                <span class="self-center"><i
                        class="fas fa-search mr-2"></i>{{__('ledger.searched')}}</span><span>...</span>
                @foreach($keywords as $keyword)
                    @if(empty($synonyms[$keyword]))
                        <div class="badge badge-neutral opacity-70 badge-lg h-8 flex items-stretch tooltip"
                             data-tip="{{__('ledger.no_synonyms')}}">
                            <div class="self-center space-x-2 font-bold">
                                {{$keyword}}
                            </div>
                        </div>
                    @else
                        <div class="badge badge-primary opacity-70 badge-lg h-8 flex items-stretch tooltip"
                             data-tip="{{implode( ' / ',$synonyms[$keyword] )}}"
                        >
                            <div class="self-center space-x-2 font-bold">
                                {{$keyword}}
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="flex justify-center space-x-4">

            @if(!empty($selectedFolderIds))
                <div class="badge badge-info bg-info/90 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.folder.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-folder-open text-info-content/50"></i><span
                            class="font-bold">@php echo count($selectedFolderIds) @endphp</span>
                    </div>
                </div>
            @endif
            @if(!empty($selectedLedgerDefineIds))
                <div class="badge badge-info bg-info/60 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.define.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-book-open text-info-content/50"></i><span
                            class="font-bold">@php echo count($selectedLedgerDefineIds) @endphp</span>
                    </div>
                </div>
            @endif
            @if(!empty($totalRecords))
                <div class="badge badge-info bg-info/30 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-list"></i><span class="font-bold">@php echo $totalRecords @endphp</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

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

<div
    class="grid sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 2xl:grid-cols-8 grid-flow-row-dense gap-4 text-white text-center leading-6 bg-stripes-purple rounded-lg">

    @foreach($folderRecords as $fKey => $folderRecord)
        <div class="p-4 rounded-lg shadow-lg bg-secondary hover:bg-secondary-focus"
             wire:key="folder_{{$folderRecord->id}}"
        >
            <div class="indicator ">
                <div class="indicator-item indicator-top indicator-center w-32">
                    {{--
                                        @if($folderRecord->folders->count()>0)
                                            <span class="badge badge-info text-base-100 ">{{ $folderRecord->folders->count() }}</span>
                                        @endif
                    --}}
                    @php
                        $count = $folderRecord->descendantLedgerDefinesCount();
                    @endphp
                    @if($count > 0)
                        <span class="badge badge-info text-base-100 space-x-1"><i
                                class="fas fa-book"></i><span>{{ $count }}</span></span>
                    @endif
                </div>
                <div class="flex justify-center items-center space-x-1">
                    <button class="btn btn-ghost px-2"
                            wire:click="toggleFolderId({{ $folderRecord->id }})"
                            wire:key="selected_folder_{{$folderRecord->id}}">
                            <span class="fa-layers fa-fw text-3xl">
                                <i class="fa-solid {{in_array($folderRecord->id, $selectedFolderIds) ? 'fa-folder-open' : 'fa-folder'}} "></i>
                                <span class="fa-layers-text text-secondary" data-fa-transform="shrink-8 down-1"
                                      style="font-weight:900">{{$folderRecord->descendantCount()}}</span>
                            </span>
                    </button>
                    <button class="btn btn-ghost px-2"
                            wire:click="changeCurrentFolder({{$folderRecord->id}})"
                            wire:key="enter_folder_{{$folderRecord->id}}"><i
                            class="text-3xl fa-solid fa-right-to-bracket"></i></button>
                </div>
            </div>
            <div class="ladgerTitle text-base mt-1">{{$folderRecord->title}}</div>
            <div class="lastUpdate text-sm"><i
                    class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
        </div>
    @endforeach

    @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
        <button class="p-4 rounded-lg shadow-lg bg-accent hover:bg-accent-focus"
                wire:click="toggleLedgerDefineId({{ $ledgerDefineRecord->id }})"
                wire:key="selected_ledger_{{$ledgerDefineRecord->id}}">

            <i class="fa-solid {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'fa-book-open' : 'fa-book'}} text-3xl "></i>
            <div class="ladgerTitle text-base mt-1">{{$ledgerDefineRecord->title}}</div>
            <div class="lastUpdate text-sm"><i
                    class="fas fa-clock mr-1"></i>{{$ledgerDefineRecord->updated_at->format('Y-m-d')}}
            </div>
        </button>
    @endforeach
</div>

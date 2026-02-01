<div
    class="grid sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-5 2xl:grid-cols-7 3xl:grid-cols-8 4xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center ">

    @foreach($folderRecords as $fKey => $folderRecord)
        <div
            class="p-4 rounded-lg shadow-lg bg-secondary text-secondary-content hover:shadow-secondary hover:focus-secondary hover:opacity-100 min-w-36 relative grid
            {{in_array($folderRecord->id, $selectedFolderIds) ? 'opacity-90' : 'opacity-60'}}"
            wire:key="folder_{{$folderRecord->id}}">
            <div class="absolute  place-self-center top-4">
                <div class="indicator">
                    <div class="indicator-item indicator-top indicator-center w-32">
                        @php
                            $count = $folderRecord->descendantLedgerDefinesCount();
                        @endphp
                        @if($count > 0)
                            <span class="badge bg-base-200 text-secondary space-x-1"><i
                                    class="fas fa-book"></i><span>{{ $count }}</span></span>
                        @endif
                    </div>
                    <div class="flex justify-center items-center">
                        <button class="btn btn-ghost"
                                wire:click.prevent="toggleFolderId({{ $folderRecord->id }})"
                                wire:key="selected_folder_{{$folderRecord->id}}">
                                <span class="fa-layers fa-fw text-3xl">
                                    <i class="fa-solid {{in_array($folderRecord->id, $selectedFolderIds) ? 'fa-folder-open' : 'fa-folder'}} "></i>
                                    <span class="fa-layers-text text-secondary" data-fa-transform="shrink-8 down-1"
                                          style="font-weight:900">{{$folderRecord->descendantCount()}}</span>
                                </span>
                        </button>
                        {{-- 階層移動ボタン --}}
                        <button class="btn btn-ghost"
                                wire:click.prevent="changeCurrentFolder({{$folderRecord->id}})"
                                wire:key="enter_folder_{{$folderRecord->id}}">
                                <i class="text-3xl fa-solid fa-right-to-bracket"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="ledgerTitle text-base mt-11 mb-2 break-all">{{$folderRecord->title}}</div>
            <div class="lastUpdate text-sm absolute bottom-0 my-1 place-self-center"><i
                    class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
        </div>
    @endforeach

    @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
        <div
            class="cursor-pointer p-4 rounded-lg shadow-lg bg-accent hover:shadow-accent hover:opacity-100 {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'opacity-90' : 'opacity-60'}}  min-w-36 relative grid"
                wire:click.prevent="toggleLedgerDefineId({{ $ledgerDefineRecord->id }})"
                wire:key="selected_ledger_{{$ledgerDefineRecord->id}}">

            <i class=" place-self-center fa-solid {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'fa-book-open' : 'fa-book'}} text-3xl "></i>
            <div class="ledgerTitle text-base mt-2 mb-2 break-all">{{$ledgerDefineRecord->title}}

                @if($ledgerDefineRecord->ledgers()->count()==0)
                    <a href="{{ route('ledger.create', ['tenant' => tenant()?->id, 'ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
                       class="btn btn-xs btn-neutral tooltip items-center pt-1"
                       target="ledgerCreate_{{$ledgerDefineRecord->id}}}}"
                       data-tip="{{__('ledger.create')}}"
                    ><i class="fas fa-circle-plus"></i>
                    </a>
                @endif
            </div>
            <div class="lastUpdate text-sm absolute bottom-0 my-1 place-self-center">
                <i class="fas fa-clock mr-1"></i>{{$ledgerDefineRecord->updated_at->format('Y-m-d')}}
            </div>
        </div>
    @endforeach
</div>

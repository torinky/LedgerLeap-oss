<div class="card bg-warning/50 h-full">
    <div class="bg-warning text-warning-content/70 rounded-t-box px-4 mb-4 font-bold ">
        <x-ledger.livewire-breadcrumbs
            :breadcrumbs="$breadcrumbs"
        ></x-ledger.livewire-breadcrumbs>
    </div>

    <div class="card-body pt-0">


        <div
            class="grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-5 2xl:grid-cols-7 3xl:grid-cols-8 4xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center ">

            @foreach($folderRecords as $fKey => $folderRecord)
                <div class="p-4 rounded-lg shadow-lg bg-secondary text-secondary-content hover:shadow-secondary hover:focus-secondary hover:opacity-100 min-w-36 relative grid
            {{in_array($folderRecord->id, $selectedFolderIds) ? 'opacity-90' : 'opacity-60'}}">
                    <div class="absolute place-self-center top-1">
                        <div class="indicator">
                            {{--                <div class="flex justify-center items-center ">--}}
                            <a href="{{ route('folder.edit', ['folderId'=>$folderRecord->id]) }}"
                               class="btn btn-ghost"
                               target="folderEdit_{{$folderRecord->id}}}}">
                                {{--                        <i class="fa-solid fa-folder "></i>--}}

                                <span class="fa-layers fa-fw">
                                    <i class="fa-solid fa-folder text-3xl" data-fa-transform="left-5 "></i>
                                    <i class="fa-solid  fa-pencil text-2xl text-base-100/70"
                                       data-fa-transform=" right-5 up-3"></i>
                                </span>
                            </a>

                            <a href="#" class="btn btn-ghost"
                               wire:click="changeCurrentFolder({{$folderRecord->id}})">
                                <i class="text-3xl fa-solid fa-right-to-bracket"></i></a>
                        </div>
                    </div>
                    <div class="ledgerTitle text-base mt-7 mb-2 break-all">{{$folderRecord->title}}</div>
                    <div class="lastUpdate text-sm absolute bottom-0 my-1 place-self-center"><i
                            class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
                </div>
            @endforeach

            @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
                <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
                   target="ledgerDefineEdit_{{$ledgerDefineRecord->id}}}}"
                   class="tooltip cursor-pointer p-4 rounded-lg shadow-lg bg-accent hover:shadow-accent hover:opacity-100
                   {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'opacity-90' : 'opacity-60'}}  min-w-36 relative grid"
                   data-tip="{{__('ledger.edit')}}"
                >
                    <div class="flex justify-center items-center">
                        {{--                    <i class="fa-solid fa-book text-3xl "></i>--}}
                        <span class="fa-layers fa-fw">
                            <i class="fa-solid fa-book text-3xl" data-fa-transform="left-5 "></i>
                            <i class="fa-solid  fa-pencil text-2xl text-secondary-content/70"
                               data-fa-transform=" right-5 up-3"></i>
                        </span>

                    </div>
                    <div class="ledgerTitle text-base mt-2 mb-2 break-all">{{$ledgerDefineRecord->title}}</div>
                    <div class="lastUpdate text-sm absolute bottom-0 my-1 place-self-center">
                        <i class="fas fa-clock mr-1"></i>{{$ledgerDefineRecord->updated_at->format('Y-m-d')}}
                    </div>
                </a>
            @endforeach

        </div>
        {{--    <div class="divider"></div>--}}

        @if($ledgerDefineRecords->count() == 0)
            @include('components.ledger.alert',[
            'message'=>__('ledger.define.create_message'),
            'icon'=> 'fa-circle-info',
            'type'=>'warning',
            'refreshParentWindow'=>false,
            ])
        @endif
    </div>

    <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center">
        <div class="card bg-base-300 opacity-70 hover:opacity-100 transition-opacity">
            <div class="card-body">
                <div class="card-actions place-items-center">
                    <a href="{{ route('folder.createWithFolderId',$currentFolderId)}}"
                       class="btn btn-primary btn-lg mx-3"
                       target="folderCreate">
                        <span class="fa-layers fa-fw mr-2">
                            <i class="fa-solid fa-folder text-2xl" data-fa-transform="left-6 "></i>
                            <i class="fa-solid  fa-plus-circle text-base-100/70"
                               data-fa-transform=" right-6 up-10"></i>
                        </span>
                        {{__('ledger.folder.create')}}
                    </a>

                    <a href="{{ route('ledgerDefine.createWithFolderId',$currentFolderId)}}"
                       class="btn btn-primary btn-lg mx-3"
                       target="ledgerDefineCreate"
                    >
                        <span class="fa-layers fa-fw mr-2">
                            <i class="fa-solid fa-book text-2xl" data-fa-transform="left-6 "></i>
                            <i class="fa-solid  fa-plus-circle text-base-100/70"
                               data-fa-transform=" right-6 up-10"></i>
                        </span>
                        {{__('ledger.define.create')}}
                    </a>

                    <a href="{{ route('ledgersByFolderId',$currentFolderId)}}"
                       class="btn btn-outline btn-info btn-sm ml-10"
                       target="ledgersByFolderId">
                        <i class="fa-solid fa-arrow-circle-right mr-1"></i>
                        {{__('ledger.folder.goto_ledger')}}
                    </a>

                </div>

                <div class="mt-5 flex justify-center">
                    <button class="btn btn-error btn-outline btn-xs opacity-70 hover:opacity-100"
                            wire:click="fixFolderTree()"
                    >
                        <i class="fas fa-toolbox mr-2"></i>
                        {{__('ledger.folder.fix')}}
                    </button>


                </div>
            </div>
        </div>
    </div>


</div>

<div>
    <x-ledger.livewire-breadcrumbs
        :breadcrumbs="$breadcrumbs"
    ></x-ledger.livewire-breadcrumbs>

    <div class="flex flex-row my-5 space-5 justify-end">
        <a href="{{ route('folder.createWithFolderId',$currentFolderId)}}"
           class="btn btn-outline btn-secondary btn-sm mx-3"
           target="folderCreate">
            <i class="fa-solid fa-plus-circle mr-1"></i>
            {{__('ledger.folder.create')}}
        </a>
        <a href="{{ route('ledgerDefine.createWithFolderId',$currentFolderId)}}"
           class="btn btn-outline btn-secondary btn-sm mx-3"
           target="ledgerDefineCreate">
            <i class="fa-solid fa-plus-circle mr-1"></i>
            {{__('ledger.define.create')}}
        </a>
        <a href="{{ route('ledgersByFolderId',$currentFolderId)}}"
           class="btn btn-outline btn-info btn-sm mx-3"
           target="ledgersByFolderId">
            <i class="fa-solid fa-list-alt mr-1"></i>
            {{__('ledger.folder.goto_ledger')}}
        </a>

    </div>

    <div
        class="grid sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center leading-6 bg-stripes-purple rounded-lg ">

        @foreach($folderRecords as $fKey => $folderRecord)
            <div class="p-4 rounded-lg shadow-lg bg-secondary hover:bg-secondary-focus">
                <div class="flex justify-center items-center ">
                    <a href="{{ route('folder.edit', ['folderId'=>$folderRecord->id]) }}" class="btn btn-ghost"
                       target="folderEdit_{{$folderRecord->id}}}}">
                        <i class="swap-off fa-solid fa-folder text-3xl "></i>
                    </a>

                    <a href="#" class="btn btn-ghost" wire:click="changeCurrentFolder({{$folderRecord->id}})"><i
                            class="text-3xl fa-solid fa-right-to-bracket"></i></a>
                </div>
                <div class="ledgerTitle text-base mt-1">{{$folderRecord->title}}</div>
                <div class="lastUpdate text-sm"><i
                        class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
            </div>
        @endforeach

        @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
            <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
               target="ledgerDefineEdit_{{$ledgerDefineRecord->id}}}}"
               class="p-4 rounded-lg shadow-lg bg-accent hover:bg-accent-focus">
                <div class="flex justify-center items-center">
                    <i class="fa-solid fa-book text-3xl "></i>
                </div>
                <div class="ledgerTitle text-base mt-1">{{$ledgerDefineRecord->title}}</div>
                <div class="lastUpdate text-sm"><i
                        class="fas fa-clock mr-1"></i>{{$ledgerDefineRecord->updated_at->format('Y-m-d')}}
                </div>
            </a>
        @endforeach

    </div>

    {{--    <div class="divider"></div>--}}

    @if($ledgerDefineRecords->count() > 0)
        {{--
                {!! $ledgerDefineRecords->links() !!}
                <table class="relative table table-zebra table-compact table-auto table-pin-rows table-pin-cols max-h-fit">
                    <thead>
                    <tr>
                        <th>id</th>
                        <th>title</th>
                        <th>define</th>
                        <th>created</th>
                        <th>modified</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($ledgerDefineRecords as $ledgerDefineRecord)
                        <tr class="hover">
                            <td class="border justify-center w-32 space-y-2">
                                <div>
                                    --}}
        {{--                            {{$ledger->id}}--}}{{--

                                    <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
                                       class="btn btn-outline btn-primary btn-sm w-28"
                                       target="ledgerDefineEdit_{{$ledgerDefineRecord->id}}}}">
                                        <i class="fas fa-pencil mr-1"></i>
                                        {{__('edit')}}</a>

                                </div>
                                <div>
                                    <a href="{{route('ledgerByDefineId',['ledgerDefineId'=>$ledgerDefineRecord->id])}}"
                                       class="btn btn-outline btn-info btn-sm w-28"
                                       target="ledgerView_{{$ledgerDefineRecord->id}}}}">
                                        <i class="fas fa-table-list mr-1"></i>
                                        {{__('view')}}</a>

                                </div>

                            </td>
                            <td>
                                <x-ledgerDefine.breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerDefineRecord->id]"
                                                            :thisLedgerDefine="$ledgerDefineRecord"></x-ledgerDefine.breadcrumbs>

                            </td>
                            <td>
                                <table class="w-full">
                                    <tr>
                                        <th>name</th>
                                        <th>type</th>
                                    </tr>
                                    @foreach($ledgerDefineRecord->column_define as $oneColumn)
                                        <tr>
                                            <td>{{$oneColumn->name}}</td>
                                            <td>{{$oneColumn->type}}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                            <td>{{$ledgerDefineRecord->created_at}}</td>
                            <td>{{$ledgerDefineRecord->updated_at}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {!! $ledgerDefineRecords->links() !!}
        --}}
    @else
        @include('components.ledger.alert',[
        'message'=>__('Select Folder'),
        'icon'=> 'fa-circle-info',
        'type'=>'warning',
        'refreshParentWindow'=>false,
        ])
    @endif

</div>

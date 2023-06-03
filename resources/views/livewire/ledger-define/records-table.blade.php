<div>
    <x-ledgerDefine.breadcrumbs
        :breadcrumbs="$breadcrumbs"
    ></x-ledgerDefine.breadcrumbs>

    <div class="m-5">
        <a href="{{ route('folder.createWithFolderId',$currentFolder->id)}}"
           class="btn btn-outline btn-secondary btn-sm mx-3"
           target="folderCreate">
            <i class="fa-solid fa-plus-circle mr-1"></i>
            {{__('create folder')}}
        </a>
    </div>

    <div
        class="grid sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center leading-6 bg-stripes-purple rounded-lg">

        @foreach($folderRecords as $fKey => $folderRecord)
            <div>
                <a href="{{route('ledgerDefinesByFolderId',['folderId'=>$folderRecord->id])}}">
                    <div class="p-4 rounded-lg shadow-lg bg-purple-500 hover:bg-purple-600">
                        <div class="flex justify-center items-center">
                            <label class="swap">
                                <input type="checkbox" value="{{$folderRecord->id}}"
                                       wire:model="selectedFolderIds.{{$fKey}}" style="display: none"/>
                                <i class="swap-off fa-solid fa-folder text-3xl "></i>
                                <i class="swap-on fa-solid fa-folder-open text-3xl "></i>
                            </label>
                            {{--                        <input type="checkbox" checked="checked" class="checkbox ml-5"/>--}}
                        </div>
                        <div class="ladgerTitle text-base mt-1">{{$folderRecord->title}}</div>
                        <div class="lastUpdate text-sm"><i
                                class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>


                    </div>
                </a>
                <a href="{{ route('folder.edit', ['folderId'=>$folderRecord->id]) }}"
                   class="btn btn-outline btn-primary btn-sm mt-2"
                   target="folderEdit_{{$folderRecord->id}}}}">
                    <i class="fas fa-pencil mr-1"></i>
                    {{__('edit')}}</a>

            </div>
        @endforeach
    </div>

    <div class="divider"></div>
    <div class="m-5">
        <a href="{{ route('ledgerDefine.createWithFolderId',$currentFolder->id)}}"
           class="btn btn-outline btn-secondary btn-sm mx-3"
           target="ledgerDefineCreate">
            <i class="fa-solid fa-plus-circle mr-1"></i>
            {{__('create ledger')}}
        </a>
    </div>

    @if($ledgerDefineRecords->count() > 0)
        {!! $ledgerDefineRecords->links() !!}
        <table class="mx-2 table table-zebra table-compact w-full">
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
                    <td class="border justify-center w-32">
                        {{--                            {{$ledger->id}}--}}
                        <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
                           class="btn btn-outline btn-primary btn-sm"
                           target="ledgerDefineEdit_{{$ledgerDefineRecord->id}}}}">
                            <i class="fas fa-pencil mr-1"></i>
                            {{__('edit')}}</a>

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
    @else
        @include('components.ledger.alert',[
        'message'=>__('Select Folder'),
        'icon'=> 'fa-circle-info',
        'type'=>'warning',
        'refreshParentWindow'=>false,
        ])
    @endif

</div>

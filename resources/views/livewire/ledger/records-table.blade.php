<div>

    <div class="w-full flex pb-10 justify-center items-center">
        <div class="w-3/6 mx-1">
            <label>
                <input wire:model.debounce.800ms="search" type="text"
                       class="input input-bordered input-lg input-primary w-full"
                       placeholder="Search content...">
            </label>
        </div>
        <div class="w-1/6 relative mx-1">
            {{--
                        <select wire:model="orderBy"
                                class="select select-bordered w-full max-w-xs"
                                id="grid-state">
                            <option value="id">ID</option>
                            <option value="content->0">col1</option>
                            <option value="content->1">col2</option>
                            <option value="created_at">created</option>
                        </select>
            --}}
            {{--
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                </svg>
                            </div>
            --}}
        </div>
        <div class="w-1/6 relative mx-1">
            <select wire:model="orderAsc"
                    class="select select-bordered w-full max-w-xs"
                    id="grid-state">
                <option value="1">Ascending</option>
                <option value="0">Descending</option>
            </select>
            {{--
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                </svg>
                            </div>
            --}}
        </div>
        <div class="w-1/6 relative mx-1">
            <select wire:model="perPage"
                    class="select select-bordered w-full max-w-xs"
                    id="grid-state">
                <option>10</option>
                <option>25</option>
                <option>50</option>
                <option>100</option>
            </select>
            {{--
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                                </svg>
                            </div>
            --}}
        </div>
    </div>

    <x-ledger.livewire-breadcrumbs
        :breadcrumbs="$breadcrumbs"
    ></x-ledger.livewire-breadcrumbs>

    {{--
        <div class="flex flex-row">
            <livewire:folder.tag :folderId="$currentFolderId" :wire:key="$currentFolderId"/>
        </div>
    --}}


    <div
        class="grid sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 2xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center leading-6 bg-stripes-purple rounded-lg">

        @foreach($folderRecords as $fKey => $folderRecord)
            {{--            <a href="{{route('ledgersByFolderId',['folderId'=>$folderRecord->id])}}">--}}
            {{--            <button wire:click.self="changeCurrentFolder({{$folderRecord->id}})">--}}
            {{--            <button class="p-4 rounded-lg shadow-lg bg-secondary hover:bg-secondary-focus" wire:click.self="changeCurrentFolder({{$folderRecord->id}})">--}}
            <button class="p-2 rounded-lg shadow-lg bg-secondary hover:bg-secondary-focus">
                <div class="flex justify-center items-center ">
                    <label class="swap btn btn-ghost">
                        <input type="checkbox" value="{{$folderRecord->id}}"
                               wire:model="selectedFolderIds.{{$fKey}}" style="display: none"/>
                        <i class="swap-off fa-solid fa-folder text-3xl "></i>
                        <i class="swap-on fa-solid fa-folder-open text-3xl "></i>
                    </label>

                    <a href="#" class="btn btn-ghost" wire:click="changeCurrentFolder({{$folderRecord->id}})"><i
                            class="text-3xl fa-solid fa-right-to-bracket"></i></a>
                    {{--                        <input type="checkbox" checked="checked" class="checkbox ml-5"/>--}}
                </div>
                <div class="ladgerTitle text-base mt-1">{{$folderRecord->title}}</div>
                <div class="lastUpdate text-sm"><i
                        class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
            </button>
        @endforeach

        @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
            {{--            <a href="{{route('ledgerByDefineId',['ledgerDefineId'=>$ledgerDefineRecord->id])}}">--}}
            {{--                <div wire:click.self="$set('selectedLedgerDefineIds.{{$dKey}}',{{$ledgerDefineRecord->id}})" class="p-4 rounded-lg shadow-lg bg-accent hover:bg-accent-focus">--}}
            {{--                <div class="p-4 rounded-lg shadow-lg bg-accent hover:bg-accent-focus" wire:click.self="toggleLedgerDefineOpen({{$ledgerDefineRecord->id}})">--}}
            <button class="p-4 rounded-lg shadow-lg bg-accent hover:bg-accent-focus">
                <div class="flex justify-center items-center">

                    <label class="swap btn btn-ghost">
                        <input type="checkbox" value="{{$ledgerDefineRecord->id}}"
                               wire:model="selectedLedgerDefineIds.{{$dKey}}" style="display: none"/>
                        <i class="swap-off fa-solid fa-book text-3xl "></i>
                        <i class="swap-on text-3xl fa-solid fa-book-open"></i>
                    </label>
                </div>
                <div class="ladgerTitle text-base mt-1">{{$ledgerDefineRecord->title}}</div>
                    <div class="lastUpdate text-sm"><i
                            class="fas fa-clock mr-1"></i>{{$ledgerDefineRecord->updated_at->format('Y-m-d')}}
                    </div>
            </button>
        @endforeach

            {{--        <div class="p-4 rounded-lg bg-purple-300">04</div>--}}
            {{--        <div class="p-4 rounded-lg bg-purple-300">05</div>--}}
    </div>

    <div class="divider"></div>

    <div wire:loading class=" text-center ">
        <span class="loading loading-dots loading-lg"></span>
    </div>

    <div class="">
        @if($ledgerRecords->count() > 0)

            {!! $ledgerRecords->links() !!}

            @php($defineId = null)
            @foreach($ledgerRecords as $lKey=> $ledgerRecord)

                @if($ledgerRecord->define && $defineId!=$ledgerRecord->define->id)
                    @if($lKey!=0)
                        </tbody></table></div>
    <div class="divider"></div>
    @endif

        <div class="">

            <div class="flex flex-row justify-between">
                <div class="flex-relative ">
                    <x-ledger.breadcrumbs :breadcrumbs="$breadcrumbsPerLedgerDefine[$ledgerRecord->define->id]"
                                          :thisLedgerDefine="$ledgerRecord->define"></x-ledger.breadcrumbs>
                </div>
                <div class="">
                    <a href="{{ route('ledger.create', ['ledgerDefineId'=>$ledgerRecord->define->id]) }}"
                       class="btn btn-outline btn-info btn-sm relative inline-flex my-2 w-32"
                       target="ledgerCreate_{{$ledgerRecord->define->id}}}}"><i class="fas fa-circle-plus mr-1"></i>
                        {{__('create')}}</a>
                    <a href="{{ route('ledgerDefine.edit', ['ledgerDefineId'=>$ledgerRecord->define->id]) }}"
                       class="btn btn-outline btn-primary btn-sm relative inline-flex my-2 w-32"
                       target="ledgerDefineEdit_{{$ledgerRecord->define->id}}}}"><i
                            class="fas fa-gears mr=1"></i> {{__('setting')}}</a>
                </div>
            </div>
            <div class="flex flex-row">
                <livewire:ledger-define.tags :ledgerDefineId="$ledgerRecord->define->id"
                                             :wire:key="'ledger_define_tag-'.$ledgerRecord->define->id"/>
            </div>
            <div class="overflow-x-auto max-h-screen">
                <table class="relative table table-zebra table-compact table-auto w-full table-pin-rows table-pin-cols">
                    <thead>
                    <tr class="hover">
                        <th class="text-center px-4 py-2 tracking-wider"
                            wire:click="sort('id')"
                            style="width:7rem;"
                        >
                            @if($orderBy == 'id')
                                @if($orderAsc)
                                    <i class="fas fa-chevron-up"></i>
                                @else
                                    <i class="fas fa-chevron-down"></i>
                                @endif
                            @endif
                        </th>
                        @foreach($ledgerRecord->define->column_define as $cKey=>$column_define)
                            <td class="px-4 py-2 tracking-wider"
                                wire:click.self="sort('content->{{ (string)$column_define->id }}')">
                                <div>
                                    {{$column_define->name}}
                                    @if($orderBy == 'content->'.(string)$column_define->id)
                                        @if($orderAsc)
                                            <i class="fas fa-chevron-down"></i>
                                        @else
                                            <i class="fas fa-chevron-up"></i>
                                        @endif
                                    @endif
                                </div>
                                <input
                                    wire:change="contentsFilter({{$ledgerRecord->define->id}},{{$column_define->id}},$event.target.value)"
                                    type="text"
                                    class="input input-bordered input-sm w-full max-w-xs"
                                    placeholder="Search {{$column_define->name}}...">
                            </td>
                        @endforeach

                        <td class="px-4 py-2 tracking-wider"
                            wire:click="sort('updated_at')"
                        >{{__('updated at')}}
                            @if($orderBy == 'updated_at')
                                @if($orderAsc)
                                    <i class="fas fa-chevron-down"></i>
                                @else
                                    <i class="fas fa-chevron-up"></i>
                                @endif
                            @endif
                        </td>
                    </tr>
                    </thead>
                    <tbody>
                    @endif
                    {{--                        @dump($ledgerRecord)--}}
                    {{--                        @dump($ledgerRecord->define)--}}
                    <tr class="hover">
                        <th class=" border justify-center">
                            <div>
                                <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                                   class="btn btn-outline btn-primary btn-sm my-1 w-28"
                                   target="ledgerEdit_{{$ledgerRecord->define->id}}}}">
                                    <i class="fas fa-pencil mr-1"></i>
                                    {{__('edit')}}</a>

                            </div>

                            <div>
                                <a href="{{ route('ledger.show', ['ledgerId'=>$ledgerRecord->id]) }}"
                                   class="btn btn-outline btn-info btn-sm my-1 w-28"
                                   target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                                    <i class="fas fa-table-list mr-1"></i>
                                    {{__('detail')}}</a>

                            </div>

                        </th>
                        @foreach($ledgerRecord->define->column_define as $cKey=>$columnDefine)
                            @isset($ledgerRecord->content[$columnDefine->id])
                                {{--                                <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]) }}</td>--}}
                                <td class="border px-4 py-2">{{ ColumnHtml::show($columnDefine,$ledgerRecord->content[$columnDefine->id]) }}</td>
                            @else
                                <td class="border px-4 py-2 text-center">-</td>
                            @endif
                        @endforeach
                        {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}--}}
                        <td class="border px-4 py-2">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}
                            <span
                                class="text-gray-500">{{JpDatetime::date('(bk)',$ledgerRecord->updated_at->timestamp)}}</span>
                            <br/>( {{ $ledgerRecord->updated_at->diffForHumans() }} )
                        </td>
                        {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td>--}}
                    </tr>
                        <?php $defineId = $ledgerRecord->define->id; ?>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    {!! $ledgerRecords->links() !!}
    @else
        {{--
                    <x-ledger.alert :message="__('Select Ledger or Folder')">
                        @slot('icon')
                            fa-circle-info
                        @endslot
                    </x-ledger.alert>
        --}}

            @include('components.ledger.alert',[
            'message'=>__('Select Ledger or Folder'),
            'icon'=> 'fa-circle-info',
            'type'=>'warning',
            'refreshParentWindow'=>false,
            ])

        @endif
    </div>

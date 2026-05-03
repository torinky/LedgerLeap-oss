@php
    use App\Models\LedgerDefine;
@endphp
<div class="card bg-warning/50 h-full relative overflow-hidden">
    {{-- ドロワー: parentComponentId を渡すことでツリーが直接 changeCurrentFolder を呼ぶ --}}
    <x-slot:drawer>
        <div class="w-full min-w-0" wire:loading.class="opacity-50" wire:target="changeCurrentFolder">
            <livewire:folder.tree
                :parentComponentId="$componentId"
                :currentFolderId="$currentFolderId"
                :selectedFolderIds="$selectedFolderIds"
                wire:key="folder-tree-ledger-define-stable" />
        </div>
        <div wire:loading.delay wire:target="changeCurrentFolder" class="p-4 space-y-3">
            @foreach (range(1, 5) as $i)
                <div class="flex items-center gap-2">
                    <div class="h-4 w-4 bg-base-content/10 rounded shimmer"></div>
                    <div class="h-4 bg-base-content/10 rounded w-3/4 shimmer"></div>
                </div>
            @endforeach
        </div>
    </x-slot:drawer>

    {{-- Tier 1: フォルダ切り替え時のグローバルオーバーレイ（1秒以上かかった場合のみ） --}}
    <div wire:loading.delay.longest wire:target="changeCurrentFolder"
        class="fixed inset-0 z-[200] flex items-center justify-center pointer-events-none">
        <x-element.loading-overlay tier="1" manual message="{{ __('ledger.loading') }}"
            class="!static !inset-auto !m-0" />
    </div>

    <div class="bg-warning text-warning-content/70 rounded-t-box px-4 mb-4 font-bold ">
        <x-ledger.livewire-breadcrumbs
            :breadcrumbs="$breadcrumbs"
        ></x-ledger.livewire-breadcrumbs>
    </div>

    <div class="card-body pt-0">
        {{-- Skeleton Grid during folder change --}}
        <div wire:loading.delay wire:target="changeCurrentFolder">
            <div class="grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-5 2xl:grid-cols-7 3xl:grid-cols-8 4xl:grid-cols-10 grid-flow-row-dense gap-4">
                @foreach(range(1, 10) as $i)
                    <x-folder.folder-avatar-skeleton />
                @endforeach
            </div>
        </div>

        <div wire:loading.remove.delay wire:target="changeCurrentFolder"
            class="grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-5 2xl:grid-cols-7 3xl:grid-cols-8 4xl:grid-cols-10 grid-flow-row-dense gap-4 text-white text-center ">

            @foreach($folderRecords as $fKey => $folderRecord)
                @php
                    $canUpdateFolder = auth()->user()->can('update', $folderRecord);
                @endphp
                <div class="p-4 rounded-lg shadow-lg bg-secondary text-secondary-content hover:shadow-secondary hover:focus-secondary hover:opacity-100 min-w-36 relative grid
            {{in_array($folderRecord->id, $selectedFolderIds) ? 'opacity-90' : 'opacity-60'}}">
                    <div class="absolute place-self-center top-1">
                        <div class="indicator">
                            @if($canUpdateFolder)
                                <a
                                    href="{{ route('folder.edit', ['tenant' => $this->tenantId, 'folder' => $folderRecord]) }}"
                                    class="btn btn-ghost tooltip flex items-center"
                                    data-tip="{{__('ledger.folder.edit')}}"
                                    target="folderEdit_{{$folderRecord->id}}}}"
                                >

                                    <span class="relative inline-flex items-center justify-center w-10 h-10">
                                        <i class="fa-solid fa-folder text-3xl"></i>
                                        <i class="fa-solid fa-pencil text-xl text-base-100/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                                    </span>
                                </a>
                            @else
                                <a
                                    class="btn btn-ghost opacity-30 tooltip flex items-center"
                                    data-tip="{{__('ledger.folder.not_allow_edit')}}"
                                    target="folderEdit_{{$folderRecord->id}}}}"
                                >

                                    <span class="relative inline-flex items-center justify-center w-10 h-10">
                                        <i class="fa-solid fa-folder text-3xl"></i>
                                    </span>
                                </a>

                            @endif

                            <a href="#" class="btn btn-ghost"
                               wire:click="changeCurrentFolder({{$folderRecord->id}})" @click="$dispatch('navigation-start')">
                                <i class="text-3xl fa-solid fa-right-to-bracket"></i></a>
                        </div>
                    </div>
                    <div class="ledgerTitle text-base mt-7 mb-2 break-all">{{$folderRecord->title}}</div>
                    <div class="lastUpdate text-sm absolute bottom-0 my-1 place-self-center"><i
                            class="fas fa-clock mr-1"></i>{{$folderRecord->updated_at->format('Y-m-d')}}</div>
                </div>
            @endforeach

            @foreach($ledgerDefineRecords as $dKey => $ledgerDefineRecord)
                @php
                    $canUpdateLedgerDefine = auth()->user()->can('update', $ledgerDefineRecord);
                @endphp
                <a
                    target="ledgerDefineEdit_{{$ledgerDefineRecord->id}}}}"
                    @if($canUpdateLedgerDefine)
                        data-tip="{{__('ledger.edit')}}"
                    {{--                    wire:click="changeCurrentLedgerDefine({{$ledgerDefineRecord->id}})"--}}
                    href="{{ route('ledgerDefine.edit', ['tenant' => $this->tenantId, 'ledgerDefineId'=>$ledgerDefineRecord->id]) }}"
                    class="tooltip cursor-pointer p-4 rounded-lg shadow-lg bg-accent hover:shadow-accent hover:opacity-100
                       {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'opacity-90' : 'opacity-60'}}  min-w-36 relative grid"
                    @else
                        data-tip="{{__('ledger.define.not_allow_edit')}}"
                    class="tooltip cursor-pointer p-4 rounded-lg shadow-lg bg-neutral/50 hover:shadow-accent hover:opacity-100
                       {{in_array($ledgerDefineRecord->id, $selectedLedgerDefineIds) ? 'opacity-90' : 'opacity-60'}}  min-w-36 relative grid"
                    disabled
                    @endif

                >
                    <div class="flex justify-center items-center">
                        {{--                    <i class="fa-solid fa-book text-3xl "></i>--}}
                        <span class="relative inline-flex items-center justify-center w-10 h-10">
                            <i class="fa-solid fa-book text-3xl"></i>
                            <i class="fa-solid fa-pencil text-xl text-secondary-content/90 absolute -top-1 -right-1 drop-shadow-md"></i>
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
            'icon'=> 'information-circle',
            'type'=>'warning',
            'refreshParentWindow'=>false,
            ])
        @endif
    </div>

    {{-- 統一アクションバー（透過・ホバー＆スライドアップ対応） --}}
    <div class="mx-auto w-full lg:w-2/3 fixed bottom-0 lg:bottom-4 inset-x-0 z-50 lg:px-4 transition-transform duration-300 ease-in-out"
         x-data="{ expanded: false, isLg: window.innerWidth >= 1024 }"
         @resize.window="isLg = window.innerWidth >= 1024"
         :style="(!isLg && !expanded) ? 'transform: translateY(calc(100% - 3.5rem));' : 'transform: translateY(0);'"
         @click.outside="if(!isLg) expanded = false"
    >
        <div class="shadow-[0_-10px_40px_rgba(0,0,0,0.1)] lg:shadow-md bg-base-300 transition-opacity duration-300 opacity-100 lg:opacity-[0.65] lg:hover:opacity-100 rounded-t-3xl lg:rounded-box border-t border-base-200 lg:border-none overflow-hidden flex flex-col">
            {{-- タブレット用引き上げタブ (Edge-to-Edge) --}}
            <div class="lg:hidden w-full flex flex-col items-center justify-center cursor-pointer h-14 bg-base-300 hover:bg-base-200 active:bg-base-200 transition-colors border-b border-base-content/10 flex-shrink-0" @click="expanded = !expanded">
                <div class="w-20 h-1.5 bg-base-content/30 rounded-full mb-2"></div>
                <div class="flex items-center text-base-content/80 text-sm font-bold tracking-wider gap-2">
                    <i class="fa-solid fa-chevron-up transition-transform duration-300" :class="expanded ? 'rotate-180' : ''"></i>
                    <span x-text="expanded ? '{{ __('ledger.action_bar_close') }}' : '{{ __('ledger.action_bar_open') }}'"></span>
                </div>
            </div>

            <div class="p-4 lg:p-4 pb-8 lg:pb-4 overflow-y-auto max-h-[60vh]">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-center gap-4">
                        @can('create',$currentFolder)
                            <a href="{{ route('folder.create',['tenant' => $this->tenantId, 'parentId' => $currentFolderId])}}"
                               class="btn btn-primary btn-lg shadow-md px-6"
                               target="folderCreate">
                                <span class="relative inline-flex items-center justify-center w-8 h-8 mr-2">
                                    <i class="fa-solid fa-folder text-2xl"></i>
                                    <i class="fa-solid fa-plus-circle text-lg text-base-100/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                                </span>
                                {{__('ledger.folder.create')}}
                            </a>
                        @else
                            <div class="tooltip" data-tip="{{__('ledger.folder.not_allow_create')}}">
                                <div class="btn btn-neutral btn-lg opacity-50 shadow-md px-6">
                                    <span class="relative inline-flex items-center justify-center w-8 h-8 mr-2">
                                        <i class="fa-solid fa-folder text-2xl"></i>
                                        <i class="fa-solid fa-plus-circle text-lg text-base-100/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                                    </span>
                                    {{__('ledger.folder.create')}}
                                </div>
                            </div>
                        @endcan

                        @can('create', [\App\Models\LedgerDefine::class,$currentFolder])
                            <a href="{{ route('ledgerDefine.createWithFolderId',['tenant' => $this->tenantId, 'folderId' => $currentFolderId])}}"
                               class="btn btn-primary btn-lg shadow-md px-6"
                               target="ledgerDefineCreate"
                            >
                                <span class="relative inline-flex items-center justify-center w-8 h-8 mr-2">
                                    <i class="fa-solid fa-book text-2xl"></i>
                                    <i class="fa-solid fa-plus-circle text-lg text-base-100/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                                </span>
                                {{__('ledger.define.create')}}
                            </a>
                        @else
                            <div class="tooltip" data-tip="{{__('ledger.define.not_allow_create')}}">
                                <div class="btn btn-neutral btn-lg opacity-50 shadow-md px-6">
                                    <span class="relative inline-flex items-center justify-center w-8 h-8 mr-2">
                                        <i class="fa-solid fa-book text-2xl"></i>
                                        <i class="fa-solid fa-plus-circle text-lg text-base-100/90 absolute -top-1 -right-1 drop-shadow-md"></i>
                                    </span>
                                    {{__('ledger.define.create')}}
                                </div>
                            </div>
                        @endcan

                        <a href="{{ route('ledgersByFolderId',['tenant' => $this->tenantId, 'folderId' => $currentFolderId])}}"
                           class="btn btn-outline btn-info md:ml-6"
                           target="ledgersByFolderId">
                            <i class="fa-solid fa-arrow-circle-right mr-1"></i>
                            {{__('ledger.folder.goto_ledger')}}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

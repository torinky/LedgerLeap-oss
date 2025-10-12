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

    <x-ledger.search :hasWorkflowEnabled="$hasWorkflowEnabled" />

    <div class="bg-base-300 text-base-content/70 rounded-box px-4 mb-4 font-bold ">
        <x-ledger.livewire-breadcrumbs
            :breadcrumbs="$breadcrumbs"
        />
    </div>

    {{-- ★★★ 新規追加: フォルダ概要パネル ★★★ --}}
    @if($currentFolder)
        <div class="card bg-base-200/50 shadow-sm mb-4">
            <div class="card-body p-4 flex flex-row items-center justify-between">
                <div>
                    <h2 class="card-title text-base-content">
                        <i class="fas fa-folder text-warning"></i>
                        {{ $currentFolder->title }}
                    </h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('ledger.access_and_permissions.your_access_level') }}:
                        @if($currentUserPermissionForFolder)
                            <span class="badge badge-sm badge-{{ $currentUserPermissionForFolder->getColor() }} text-{{ $currentUserPermissionForFolder->getColor() }}-content">
                            {{ $currentUserPermissionForFolder->getLabel() }}
                        </span>
                        @else
                            <span class="badge badge-sm badge-outline">{{ __('ledger.access_and_permissions.no_direct_access') }}</span>
                        @endif
                    </p>
                </div>
                <div class="card-actions">
                    <x-mary-button
                            wire:click="openPermissionModal('Folder', {{ $currentFolder->id }}, '{{ $currentFolder->title }}')"
                            label="{{ __('ledger.access_and_permissions.title') }}"
                            icon="o-shield-check"
                            class="btn-sm btn-outline"
                            spinner
                    />
                    <x-mary-button
                            wire:click="openActivityModal('Folder', {{ $currentFolder->id }}, '{{ $currentFolder->title }}')"
                            label="{{ __('ledger.activity.title') }}"
                            icon="o-clock"
                            class="btn-sm btn-outline"
                            spinner
                    />
                </div>
            </div>
        </div>
    @endif

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
        :currentTenantId="$currentTenantId"
    />


    <div class="divider"></div>
    <div class="info-block  sticky top-20 z-40 space-y-2">
        @php
            $displayLevelOptions = [
                ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
            ];
        @endphp
        <div class="mb-4 flex justify-end">
            <x-mary-group
                wire:model.live="displayLevel"
                :options="$displayLevelOptions"
                class="[&_label]:btn-ghost [&_input:checked+label]:!btn-primary"
                option-value="id"
                option-label="name"
            />
        </div>
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
                        <div class="stack z-0">
                            <div class="badge badge-primary opacity-70 badge-lg h-8 flex items-stretch tooltip"
                                 data-tip="{{implode( ' / ',$synonyms[$keyword] )}}"
                            >
                                <div class="self-center space-x-2 font-bold">
                                    {{$keyword}}
                                </div>
                            </div>
                            <div class="badge badge-primary opacity-50 badge-lg h-8 flex items-stretch shadow">
                                <div class="self-center space-x-2 font-bold">
                                    {{$keyword}}
                                </div>
                            </div>


                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="flex justify-center space-x-4 items-center">

            @if(!empty($selectedFolderIds))
                <div class="badge badge-info bg-info/90 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.folder.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-folder-open text-info-content/50"></i><span
                            class="font-bold">@php echo count($selectedFolderIds) @endphp</span>
                    </div>
                </div>
                <i class="fas fa-filter text-info-content/50 fa-rotate-270"></i>
            @endif
            @if(!empty($selectedLedgerDefineIds))
                <div class="badge badge-info bg-info/60 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.define.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-book-open text-info-content/50"></i><span
                            class="font-bold">@php echo count($selectedLedgerDefineIds) @endphp</span>
                    </div>
                </div>
                <i class="fas fa-filter text-info-content/50 fa-rotate-270"></i>
            @endif
            @if(!empty($totalRecords))
                <div class="badge badge-info bg-info/30 tooltip h-8 flex items-stretch min-w-16"
                     data-tip="{{__('ledger.opened_count')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-list"></i><span class="font-bold">@php echo $totalRecords @endphp</span>
                    </div>
                </div>
            @endif
            @if(!empty($search))
                <div class="badge badge-primary badge-sm tooltip h-8 flex items-stretch"
                     data-tip="{{__('ledger.scoring.sorted_by_score')}}">
                    <div class="self-center space-x-2">
                        <i class="fas fa-sort-amount-down"></i>
                        <span class="text-xs">{{__('ledger.scoring.score_order')}}</span>
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
                @php
                    $canManage = auth()->user()->can('update', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                    $canCreate = auth()->user()->can('ledgerCreate', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                    $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                    $canView = auth()->user()->can('ledgerView', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                @endphp
                <div class="card bg-base100 shadow-xl my-10" wire:key="ledger_record_{{$ledgerDefineId}}">
                    <div class="card-body pt-0 px-0">
                        <x-ledgerDefine.header
                            :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
                            :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine"
                            :search="$search"
                            :filter="$filter"
                            :keywords="$keywords"
                            :canManage="$canManage"
                            :canCreate="$canCreate"
                            :canView="$canView"
                            :ledgerDefineId="$ledgerDefineId"
                            :ledgerDefineRecordsKeyById="$ledgerDefineRecordsKeyById"
                            :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                            :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null"
                            :currentTenantId="$currentTenantId"
                        />

                        <div class="overflow-x-auto max-h-screen" wire:key="ledgerDefine_block-{{$ledgerDefineId}}">
                            <table
                                class="relative table table-zebra table-compact table-auto table-pin-rows table-pin-cols max-h-fit">
                                <thead>
                                <x-ledger.table-header
                                    :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]"
                                    :orderBy="$orderBy"
                                    :orderAsc="$orderAsc"
                                    :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                                />
                                </thead>
                                <tbody>
                                @foreach($ledgerDefineAndRecords as $ledgerRecordValues)
                                    <x-ledger.table-row
                                        :ledgerRecord="$ledgerRecordValues"
                                        :highlightKeyword="$search"
                                        :canUpdate="$canUpdate"
                                        :canView="$canView"
                                        :allAttachments="$allAttachments"
                                        :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                                        :currentTenantId="$currentTenantId"
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
            'icon'=> 'cursor-arrow-ripple',
            'type'=>'warning',
            'refreshParentWindow'=>false,
        ])

    @endif

    {{-- ★★★ モーダル定義 ★★★ --}}
    <x-mary-modal wire:model="showPermissionModal" class="backdrop-blur"
        boxClass="w-11/12 max-w-5xl my-4"
    >
        <x-mary-header :title="$modalTitle" icon="o-shield-check" separator />
        @if($showPermissionModal)
            @livewire('common.permission-display', [
            'resourceId' => $modalResourceId,
            'resourceType' => $modalResourceType
            ], key('permission-modal-'.$modalResourceId.'-'.$modalResourceType))
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('Close') }}" icon="o-x-circle" @click="$wire.showPermissionModal = false" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal wire:model="showActivityModal" class="backdrop-blur"
          boxClass="w-11/12 max-w-5xl my-4"
    >
        <x-mary-header :title="$modalTitle" icon="o-clock" separator />
        @if($showActivityModal)
            @livewire('common.activity-history-display', [
            'resourceId' => $modalResourceId,
            'resourceType' => $modalResourceType,
            // 台帳定義の場合、フォルダのアクティビティも表示すると便利かもしれない
            'includeRelatedResources' => ($modalResourceType === 'LedgerDefine')
            ], key('activity-modal-'.$modalResourceId.'-'.$modalResourceType))
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('Close') }}" icon="o-x-circle" @click="$wire.showActivityModal = false" />
        </x-slot:actions>
    </x-mary-modal>
</div>

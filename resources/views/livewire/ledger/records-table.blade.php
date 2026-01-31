@php
    $searchTargets = 'search,useTechnicalTerm,useSynonym,useSemanticSearch';
    $filterTargets = 'filterStatus,perPage,orderBy,orderAsc'; // displayLevel,setDisplayLevelを分離
    $recordFilterTargets = 'displayLevel,setDisplayLevel';
    // $folderNavTargets: IndexManager側での $navTargets と同期させて RecordsTable 内の表示を制御
    $folderNavTargets = 'changeCurrentFolder,toggleFolderId,toggleLedgerDefineId,focusLedgerDefine,gotoPage,nextPage,previousPage';
    // RecordsTable 内部で個別に隠蔽/表示を制御するための全ターゲット
    $allTargets = $searchTargets . ',' . $filterTargets . ',' . $folderNavTargets;
@endphp

<div class="relative">
    {{-- Search section was here, moved to IndexManager --}}

    {{-- Breadcrumbs Section (Handled by parent skeleton but kept functional for deep updates) --}}
    <div class="px-4 mt-4">
        <div class="bg-base-300 text-base-content/70 rounded-box px-4 mb-4 font-bold">
            <x-ledger.livewire-breadcrumbs :breadcrumbs="$breadcrumbs" />
        </div>
    </div>

    {{-- Navigation Panels Section (Tier 2 loading targets selection activity) --}}
    <div class="px-4 relative group/nav min-h-[60px]">
        {{-- Tier 2 overlay for selection toggles - doesn't hide content --}}
        <x-element.loading-overlay tier="2" target="selectedFolderIds,selectedLedgerDefineIds,toggleFolderId,toggleLedgerDefineId" />

        <div>
            {{-- ★★★ 新規追加: フォルダ概要パネル ★★★ --}}
            @if ($currentFolder)
                <div class="card bg-base-200/50 shadow-sm mb-4">
                    <div class="card-body p-4 flex flex-row items-center justify-between">
                        <div>
                            <h2 class="card-title text-base-content text-lg">
                                <i class="fas fa-folder text-warning"></i>
                                {{ $currentFolder->title }}
                            </h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('ledger.access_and_permissions.your_access_level') }}:
                                @if ($currentUserPermissionForFolder)
                                    <span
                                        class="badge badge-sm badge-{{ $currentUserPermissionForFolder->getColor() }} text-{{ $currentUserPermissionForFolder->getColor() }}-content font-bold">
                                        {{ $currentUserPermissionForFolder->getLabel() }}
                                    </span>
                                @else
                                    <span
                                        class="badge badge-sm badge-outline">{{ __('ledger.access_and_permissions.no_direct_access') }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="card-actions">
                            <x-mary-button
                                wire:click="openPermissionModal('Folder', {{ $currentFolder->id }}, '{{ $currentFolder->title }}')"
                                label="{{ __('ledger.access_and_permissions.title') }}" icon="o-shield-check"
                                class="btn-sm btn-outline btn-ghost" spinner />
                            <x-mary-button
                                wire:click="openActivityModal('Folder', {{ $currentFolder->id }}, '{{ $currentFolder->title }}')"
                                label="{{ __('ledger.activity.title') }}" icon="o-clock" class="btn-sm btn-outline btn-ghost" spinner />
                        </div>
                    </div>
                </div>
            @endif

            <x-folder.folder-and-ledger-panels :folderRecords="$folderRecords" :selectedFolderIds="$selectedFolderIds" :ledgerDefineRecords="$ledgerDefineRecords" :selectedLedgerDefineIds="$selectedLedgerDefineIds"
                :currentTenantId="$currentTenantId" />
        </div>
    </div>

    <div class="divider px-4 opacity-50"></div>

    {{-- Info & Results Section --}}
    <div class="px-4 relative min-h-[400px]">
        {{-- Record level overlay for granular filters like displayLevel --}}
        <x-element.loading-overlay tier="2" :target="$recordFilterTargets" />

        {{-- Always show info-block --}}
        <div class="info-block sticky top-24 z-10 space-y-2 py-2 bg-base-200/50 backdrop-blur-sm rounded-box px-4 shadow-sm border border-base-300/30 mb-6">
            @php
                $displayLevelOptions = [
                    ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                    ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                    ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                ];
            @endphp
            <div class="flex flex-wrap items-center justify-end gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold opacity-50 uppercase tracking-widest">{{ __('ledger.form.display_level') }}</span>
                    {{-- Reactive prop への直接 model binding を避けるため、Alpine.js で制御する --}}
                    <div x-data="{ level: {{ $displayLevel }} }" x-init="$watch('level', value => $wire.$parent.updateDisplayLevel(value))">
                        <x-mary-group
                            x-model="level"
                            :options="$displayLevelOptions"
                            class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary" option-value="id"
                            option-label="name" />
                    </div>
                </div>
            </div>

            @if (!empty($highlights))
                <div class="flex flex-wrap gap-2 items-center justify-center pt-2">
                    <span class="text-xs"><i class="fas fa-search mr-1 opacity-50"></i>{{ __('ledger.searched') }}</span>
                    @foreach ($keywords as $keyword)
                        <div class="badge {{ empty($synonyms[$keyword]) ? 'badge-neutral' : 'badge-primary' }} badge-md gap-2 py-3 shadow-sm border-none">
                            <span class="font-bold">{{ $keyword }}</span>
                            @if (!empty($synonyms[$keyword]))
                                <div class="tooltip tooltip-bottom" data-tip="{{ implode(' / ', $synonyms[$keyword]) }}">
                                    <i class="fas fa-layer-group text-[10px] opacity-70"></i>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-center flex-wrap gap-4 items-center pt-1" wire:loading.class="opacity-50" wire:target="{{ $allTargets }}">
                @if (!empty($selectedFolderIds))
                    <div class="badge badge-info bg-info/90 tooltip h-8 flex items-stretch min-w-16 shadow-sm border-none"
                        data-tip="{{ __('ledger.folder.opened_count') }}">
                        <div class="self-center flex items-center gap-2 text-info-content/80">
                            <i class="fas fa-folder-open text-info-content/50"></i>
                            <span class="font-bold">{{ count($selectedFolderIds) }}</span>
                        </div>
                    </div>
                    <i class="fas fa-filter text-info/30 fa-rotate-270 text-[10px]"></i>
                @endif

                @if (!empty($selectedLedgerDefineIds))
                    <div class="badge badge-info bg-info/60 tooltip h-8 flex items-stretch min-w-16 shadow-sm border-none"
                        data-tip="{{ __('ledger.define.opened_count') }}">
                        <div class="self-center flex items-center gap-2 text-info-content/80">
                            <i class="fas fa-book-open text-info-content/50"></i>
                            <span class="font-bold">{{ count($selectedLedgerDefineIds) }}</span>
                        </div>
                    </div>
                    <i class="fas fa-filter text-info/30 fa-rotate-270 text-[10px]"></i>
                @endif

                @if (!empty($totalRecords))
                    <div class="badge badge-info bg-info/30 tooltip h-8 flex items-stretch min-w-16 shadow-sm border-none"
                        data-tip="{{ __('ledger.opened_count') }}">
                        <div class="self-center flex items-center gap-2 text-info-content/80">
                            <i class="fas fa-list opacity-50"></i>
                            <span class="font-bold">{{ $totalRecords }}</span>
                        </div>
                    </div>
                @endif

                @if ($orderBy === 'composite_score' && !empty($search))
                    <div class="badge badge-primary bg-primary/80 tooltip h-8 flex items-stretch shadow-sm border-none"
                        data-tip="{{ __('ledger.scoring.sorted_by_score') }}">
                        <div class="self-center flex items-center gap-2 text-primary-content/90">
                            <i class="fas fa-sort-amount-down text-[10px]"></i>
                            <span class="text-xs font-bold">{{ __('ledger.scoring.score_order') }}</span>
                        </div>
                    </div>
                @endif

                @if ($orderBy !== 'default' && !empty($defaultSortColumns))
                    <x-mary-button wire:click="sort('default')" label="{{ __('ledger.actions.reset_sort') }}"
                        icon="o-arrow-path" class="btn-xs btn-outline btn-info h-8" spinner />
                @endif
            </div>
        </div>

        <div>
            <div class="records-list-container">
                @if ($totalRecords > 0)
                    <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center pointer-events-none">
                        <div class="card bg-base-300 opacity-70 transition-all hover:opacity-100 shadow-xl pointer-events-auto ring-1 ring-base-content/5">
                            <div class="card-body p-2">
                                {!! $ledgerRecords->links('components.ledger.pagination-links', ['position' => 'top']) !!}
                            </div>
                        </div>
                    </div>

                    @foreach ($ledgerRecordsGroupByDefineIds as $ledgerDefineId => $ledgerDefineAndRecords)
                        @php
                            $canManage = auth()->user()->can('update', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                            $canCreate = auth()->user()->can('ledgerCreate', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                            $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                            $canView = auth()->user()->can('ledgerView', $ledgerDefineRecordsKeyById[$ledgerDefineId]);
                        @endphp
                        <div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden" wire:key="ledger_record_{{ $ledgerDefineId }}">
                            <div class="card-body pt-0 px-0">
                                <x-ledgerDefine.header :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]" :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine" :search="$search"
                                    :filter="$filter" :keywords="$keywords" :canManage="$canManage" :canCreate="$canCreate"
                                    :canView="$canView" :ledgerDefineId="$ledgerDefineId" :ledgerDefineRecordsKeyById="$ledgerDefineRecordsKeyById" :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                                    :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null" :currentTenantId="$currentTenantId" />

                                <div class="overflow-x-auto" wire:key="ledgerDefine_block-{{ $ledgerDefineId }}">
                                    @php
                                        $displayColumns = $filteredColumnDefines[$ledgerDefineId] ?? [];
                                        if ($displayColumns instanceof \Illuminate\Support\Collection) {
                                            $displayColumns = $displayColumns->toArray();
                                        }
                                        $displayColumnsWithMock = $displayColumns;
                                        if (\App\Services\Ledger\MockAttachmentService::isEnabled()) {
                                            $mockDef = (object) \App\Services\Ledger\MockAttachmentService::getMockColumnDefine();
                                            $mockDef->label = '添付(モック)';
                                            $displayColumnsWithMock = array_merge($displayColumns, [$mockDef]);
                                        }
                                    @endphp
                                    <table class="table table-zebra table-compact table-auto table-pin-rows table-pin-cols w-full">
                                        <thead>
                                            <x-ledger.table-header :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]" :orderBy="$orderBy" :orderAsc="$orderAsc"
                                                :filteredColumnDefines="$displayColumnsWithMock" :defaultSortColumns="$defaultSortColumns" />
                                        </thead>
                                        <tbody>
                                            @foreach ($ledgerDefineAndRecords as $ledgerRecordValues)
                                                <x-ledger.table-row :ledgerRecord="$ledgerRecordValues" :highlightKeyword="$search" :canUpdate="$canUpdate"
                                                    :canView="$canView" :allAttachments="$allAttachments" :filteredColumnDefines="$displayColumnsWithMock"
                                                    :currentTenantId="$currentTenantId" />
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div class="mt-8">
                        {!! $ledgerRecords->links('components.ledger.pagination-links', ['position' => 'bottom']) !!}
                    </div>
                @else
                    @include('components.ledger.alert', [
                        'message' => __('ledger.select_message'),
                        'icon' => 'cursor-arrow-ripple',
                        'type' => 'warning',
                        'refreshParentWindow' => false,
                    ])
                @endif
            </div>
        </div>
    </div>

    {{-- ★★★ モーダル定義 ★★★ --}}
    <x-mary-modal wire:model="showPermissionModal" class="backdrop-blur" boxClass="w-11/12 max-w-5xl my-4">
        <x-mary-header :title="$modalTitle" icon="o-shield-check" separator />
        @if ($showPermissionModal)
            @livewire('common.permission-display', [
                'resourceId' => $modalResourceId,
                'resourceType' => $modalResourceType,
            ])
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('Close') }}" icon="o-x-circle" @click="$wire.showPermissionModal = false" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal wire:model="showActivityModal" class="backdrop-blur" boxClass="w-11/12 max-w-5xl my-4">
        <x-mary-header :title="$modalTitle" icon="o-clock" separator />
        @if ($showActivityModal)
            @livewire('common.activity-history-display', [
                'resourceId' => $modalResourceId,
                'resourceType' => $modalResourceType,
                // 台帳定義の場合、フォルダのアクティビティも表示すると便利かもしれない
                'includeRelatedResources' => $modalResourceType === 'LedgerDefine',
            ])
        @endif
        <x-slot:actions>
            <x-mary-button label="{{ __('Close') }}" icon="o-x-circle" @click="$wire.showActivityModal = false" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- 添付ファイルのドロワーを一覧ページにも常駐配置 --}}
    <livewire:attached-file.file-inspector />
</div>

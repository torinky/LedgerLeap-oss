@php
    $searchTargets = 'search,useTechnicalTerm,useSynonym,useSemanticSearch';
    $filterTargets = 'filterStatus,perPage,orderBy,orderAsc,filter'; // sort, sortRequested を削除
    $recordFilterTargets = 'displayLevel,setDisplayLevel';
    // $folderNavTargets: IndexManager側での $navTargets と同期させて RecordsTable 内の表示を制御
    $folderNavTargets =
        'changeCurrentFolder,toggleFolderId,toggleLedgerDefineId,focusLedgerDefine,gotoPage,nextPage,previousPage';
    // RecordsTable 内部で個別に隠蔽/表示を制御するための全ターゲット
    // 子コンポーネント内での Tier 2 オーバーレイ（ぼかし）の対象となるアクション
    // 頻繁な入力アクション（search, filter, filterUpdated）は、入力中に出ると邪魔なため除外されていたが、
    // ユーザーへのフィードバックを優先するため filter, updateFilterFromChild を追加
    $heavyNavTargets =
        'gotoPage,nextPage,previousPage,perPage,changeCurrentFolder,toggleFolderId,toggleLedgerDefineId,focusLedgerDefine';
    $itemActionTargets = 'sortRequested,displayLevel,setDisplayLevel,orderBy,orderAsc,filterStatus,filter,updateFilterFromChild';
    $allTargets = $heavyNavTargets . ',' . $itemActionTargets;
@endphp

<div class="relative">

    {{-- Info & Results Section --}}
    <div class="px-4 relative min-h-[400px]">
        {{-- Record level overlay for granular filters --}}
        <x-element.loading-overlay tier="2" :target="$allTargets" />

        <div>
            <div class="records-list-container">
                @if ($totalRecords > 0)
                    <div class="z-20 fixed bottom-4 left-0 right-0 mx-auto flex justify-center pointer-events-none">
                        <div
                            class="card bg-base-300 opacity-70 transition-all hover:opacity-100 shadow-xl pointer-events-auto ring-1 ring-base-content/5">
                            <div class="card-body p-2">
                                {!! $ledgerRecords->links('components.ledger.pagination-links', ['position' => 'top']) !!}
                            </div>
                        </div>
                    </div>

                    @foreach ($ledgerRecordsGroupByDefineIds as $ledgerDefineId => $ledgerDefineAndRecords)
                        @php
                            $ledgerDefine = $ledgerDefineRecordsKeyById[$ledgerDefineId] ?? null;
                            if (!$ledgerDefine) {
                                \Log::warning('RecordsTable: ledgerDefine not found for ID', ['id' => $ledgerDefineId]);
                                continue;
                            }
                            $canManage = auth()->user()->can('update', $ledgerDefine);
                            $canCreate = auth()
                                ->user()
                                ->can('ledgerCreate', $ledgerDefine);
                            $canUpdate = auth()
                                ->user()
                                ->can('ledgerUpdate', $ledgerDefine);
                            $canView = auth()->user()->can('ledgerView', $ledgerDefine);
                            \Log::info('RecordsTable render loop: permissions', [
                                'ledgerDefineId' => $ledgerDefineId,
                                'canView' => $canView,
                                'canUpdate' => $canUpdate,
                                'user' => auth()->user()->email
                            ]);
                        @endphp
                        <div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden"
                            wire:key="ledger_record_{{ $ledgerDefineId }}">
                            <div class="card-body pt-0 px-0">
                                <x-ledgerDefine.header :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]" :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine" :search="$search"
                                    :filter="$filter" :keywords="$keywords" :canManage="$canManage" :canCreate="$canCreate"
                                    :canView="$canView" :ledgerDefineId="$ledgerDefineId" :ledgerDefineRecordsKeyById="$ledgerDefineRecordsKeyById" :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                                    :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null" :currentTenantId="$currentTenantId" />

                                <div class="overflow-x-auto max-h-[70vh]" wire:key="ledgerDefine_block-{{ $ledgerDefineId }}">
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
                                    <table
                                        class="table table-zebra table-compact table-auto table-pin-rows table-pin-cols w-full">
                                        <thead>
                                            <x-ledger.table-header :ledgerDefine="$ledgerDefineRecordsKeyById[$ledgerDefineId]" :orderBy="$orderBy"
                                                :orderAsc="$orderAsc" :filteredColumnDefines="$displayColumnsWithMock" :defaultSortColumns="$defaultSortColumns" />
                                        </thead>
                                        <tbody>
                                            @foreach ($ledgerDefineAndRecords as $ledgerRecordValues)
                                                <x-ledger.table-row :ledgerRecord="$ledgerRecordValues" :highlightKeyword="$search"
                                                    :canUpdate="$canUpdate" :canView="$canView" :allAttachments="$allAttachments"
                                                    :filteredColumnDefines="$displayColumnsWithMock" :currentTenantId="$currentTenantId" />
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

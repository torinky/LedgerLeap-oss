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

<div class="relative" x-data="confidentialityScrollTracker()"
    x-on:file-inspector-selection-applied.window="if ($event.detail.selectedLedgerId) { $nextTick(() => { const row = document.getElementById('ledger-row-' + $event.detail.selectedLedgerId); if (row) { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); row.focus({ preventScroll: true }); } }); }"
    x-init="init()"
    x-on:ledger-sections-rendered.window="init()">

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
                            $canCreate = auth()->user()->can('ledgerCreate', $ledgerDefine);
                            $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerDefine);
                            $canView = auth()->user()->can('ledgerView', $ledgerDefine);
                            \Log::info('RecordsTable render loop: permissions', [
                                'ledgerDefineId' => $ledgerDefineId,
                                'canView' => $canView,
                                'canUpdate' => $canUpdate,
                                'user' => auth()->user()->email
                            ]);
                        @endphp
                        <x-ledger.records-section
                            :ledgerDefineId="$ledgerDefineId"
                            :records="$ledgerDefineAndRecords"
                            :ledgerDefine="$ledgerDefine"
                            :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine"
                            :scoreStats="$scoreStatsByDefineId[$ledgerDefineId] ?? null"
                            :overallStats="$overallStatsByDefineId[$ledgerDefineId] ?? null"
                            :filteredColumnDefines="$filteredColumnDefines[$ledgerDefineId]"
                            :search="$search"
                            :filter="$filter"
                            :keywords="$keywords"
                            :canManage="$canManage"
                            :canCreate="$canCreate"
                            :canUpdate="$canUpdate"
                            :canView="$canView"
                            :ledgerDefineRecordsKeyById="$ledgerDefineRecordsKeyById"
                            :orderBy="$orderBy"
                            :orderAsc="$orderAsc"
                            :defaultSortColumns="$defaultSortColumns"
                            :currentTenantId="$currentTenantId"
                            :selectedFileId="$selectedFileId"
                            :selectedLedgerId="$selectedLedgerId"
                            :selectedColumnId="$selectedColumnId"
                        />
                    @endforeach
{{--
                    <div class="mt-8">
                        {!! $ledgerRecords->links('components.ledger.pagination-links', ['position' => 'bottom']) !!}
                    </div>
--}}
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

<script>
function confidentialityScrollTracker() {
    return {
        observer: null,
        lastRatio: {},

        init() {
            // 古い Observer をクリーンアップ（Livewire 更新時の再実行対策）
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
            this.lastRatio = {};

            if (! ('IntersectionObserver' in window)) {
                return;
            }

            this.$nextTick(() => {
                try {
                    const sections = this.$el.querySelectorAll('[data-ledger-define-section]');
                    if (sections.length === 0) {
                        return;
                    }

                    // 単一セクションの場合も、そのセクションの LedgerDefine ID を通知する
                    if (sections.length === 1) {
                        const id = sections[0].getAttribute('data-ledger-define-section');
                        Livewire.dispatch('confidentialitySectionChanged', {
                            ledgerDefineId: parseInt(id, 10),
                        });
                        return;
                    }

                    this.observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            const id = entry.target.getAttribute('data-ledger-define-section');
                            this.lastRatio[id] = entry.intersectionRatio;
                        });

                        let bestId = null;
                        let bestRatio = 0;
                        for (const [id, ratio] of Object.entries(this.lastRatio)) {
                            if (ratio > bestRatio) {
                                bestRatio = ratio;
                                bestId = id;
                            }
                        }

                        if (bestId !== null) {
                            Livewire.dispatch('confidentialitySectionChanged', {
                                ledgerDefineId: parseInt(bestId, 10),
                            });
                        }
                    },
                    {
                        root: null,
                        threshold: [0, 0.25, 0.5, 0.75, 1.0],
                    }
                );

                sections.forEach((section) => this.observer.observe(section));
                } catch (e) {
                    console.error('[confidentialityScrollTracker] error:', e);
                }
            });
        },
    };
}
</script>

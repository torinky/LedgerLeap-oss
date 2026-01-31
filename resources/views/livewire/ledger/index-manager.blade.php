<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    @php
        // IndexManager で監視すべき主要なアクションとプロパティ
        // メソッド名とイベント名の両方を網羅することで、どの起動経路でもローディングが表示されるようにする
        $loadingMethods =
            'changeCurrentFolder,search,orderBy,orderAsc,filterStatus,selectedLedgerDefineIds,selectedFolderIds,displayLevel,perPage,gotoPage,nextPage,previousPage,focusLedgerDefine,toggleFolderId,toggleLedgerDefineId,updateDisplayLevel,sort';
        $loadingEvents =
            'currentFolderChangeRequested,focusLedgerDefineRequested,folderIdToggled,ledgerDefineIdToggled,displayLevelRequested';
        $loadingTargets = $loadingMethods . ',' . $loadingEvents;
    @endphp

    <div class="relative min-h-screen">
        {{-- Tier 1: Global Loading Overlay --}}
        {{-- .delay.longest (1秒) を使用し、非常に重い通信の時のみ中央にスピナーを表示する --}}
        <div wire:loading.delay.longest wire:target="{{ $loadingTargets }}"
            class="fixed inset-0 z-[200] flex items-center justify-center pointer-events-none">
            {{-- 指定されたコンポーネントを使用。!static !inset-auto で確実に中央へ固定 --}}
            <x-element.loading-overlay tier="1" manual message="{{ __('ledger.loading') }}"
                class="!static !inset-auto !m-0" />
        </div>

        <x-slot:drawer>
            {{-- Tree Content: 瞬時に真っ白にならないよう opacity で制御。クリックを妨げない。 --}}
            <div wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                <livewire:folder.tree :currentFolderId="$currentFolderId" :selectedFolderIds="$selectedFolderIds" :parentComponentId="$this->getId()"
                    wire:key="folder-tree-stable" />
            </div>
            {{-- Tree Skeleton: 通信中（200ms〜）のみ表示 --}}
            <div wire:loading.delay wire:target="{{ $loadingTargets }}" class="p-4 space-y-3">
                @foreach (range(1, 5) as $i)
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-4 bg-base-content/10 rounded shimmer"></div>
                        <div class="h-4 bg-base-content/10 rounded w-{{ [1, 2, 3][rand(0, 2)] }}/4 shimmer"></div>
                    </div>
                @endforeach
            </div>
        </x-slot:drawer>

        {{-- Always visible search section moved from RecordsTable --}}
        <div class="px-4 pt-4 sticky top-0 z-10 bg-base-200/80 backdrop-blur-md pb-4 rounded-b-2xl">
            <x-ledger.search :hasWorkflowEnabled="$hasWorkflowEnabled" :orderBy="$orderBy" :orderByLabel="$orderByLabel" :useSemanticSearch="$useSemanticSearch"
                :defaultSortColumns="$defaultSortColumns" />
        </div>

        <div class="container max-w-full px-0 md:px-4 mt-4">
            {{-- Main Content: 瞬時の表示切り替えでチラつかないよう、一定時間(200ms)経過後に隠す --}}
            <div wire:loading.remove.delay wire:target="{{ $loadingTargets }}">
                <livewire:ledger.records-table :search="$search" :orderBy="$orderBy" :orderAsc="$orderAsc" :filterStatus="$filterStatus"
                    :filter="$filter" :selectedLedgerDefineIds="$selectedLedgerDefineIds" :selectedFolderIds="$selectedFolderIds" :currentFolderId="$currentFolderId" :displayLevel="$displayLevel"
                    :useSemanticSearch="$useSemanticSearch" :useSynonym="$useSynonym" :useTechnicalTerm="$useTechnicalTerm" :perPage="$perPage" :defaultSortColumns="$defaultSortColumns"
                    :hasWorkflowEnabled="$hasWorkflowEnabled"
                    :wire:key="'records-table-'.md5(json_encode([$search, $currentFolderId, $useSemanticSearch, $selectedLedgerDefineIds, $orderBy]))" />
            </div>

            {{-- Mega Skeleton: 通信開始時に即座に表示して視覚的フィードバックを提供（.delayを削除） --}}
            <div wire:loading wire:target="{{ $loadingTargets }}" class="w-full">
                <div class="px-4">
                    {{-- Breadcrumb Skeleton --}}
                    <div class="h-10 bg-base-300 rounded-box w-full shimmer mb-4"></div>

                    {{-- Folder Info Skeleton --}}
                    <div class="card bg-base-200/50 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="h-8 bg-base-content/10 rounded-lg w-1/4 shimmer"></div>
                        </div>
                    </div>

                    {{-- Records Skeleton --}}
                    <div class="records-list-container mt-6">
                        @foreach (range(1, 2) as $i)
                            <div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden">
                                <div class="card-body pt-0 px-0">
                                    <div
                                        class="bg-base-300 mt-0 px-4 py-4 rounded-t-box flex items-center gap-4 shimmer">
                                        <div class="h-8 w-8 bg-base-content/10 rounded-full"></div>
                                        <div class="h-6 bg-base-content/10 rounded-lg w-1/3"></div>
                                    </div>
                                    <x-element.skeleton-table rows="8" cols="10" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

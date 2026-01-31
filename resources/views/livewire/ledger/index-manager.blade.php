<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    @php
        // IndexManager で監視すべき主要なアクションとプロパティ
        $loadingTargets = 'changeCurrentFolder,search,orderBy,orderAsc,filterStatus,selectedLedgerDefineIds,selectedFolderIds,displayLevel,perPage,gotoPage,nextPage,previousPage,focusLedgerDefine,toggleFolderId,toggleLedgerDefineId,updateDisplayLevel,sort';
    @endphp

    <div class="relative min-h-screen">
        {{-- Tier 1: Global Loading Overlay (Blocking Spinner) --}}
        {{-- .delay.long (1s) を追加し、通信が長引く場合のみ表示。ターゲットも限定。 --}}
        <x-element.loading-overlay wire:target="{{ $loadingTargets }}" wire:loading.delay.long tier="1" message="{{ __('ledger.loading') }}" />

        <x-slot:drawer>
            {{-- Tree Content: 以前のツリーを隠して Skeleton に切り替えるが、短時間は切り替えないよう .delay を検討したいが、 --}}
            {{-- wire:loading.remove には .delay が無いため、即座に隠れてしまう。 --}}
            {{-- 煩わしさを軽減するため、ツリー側は wire:loading.remove を外して、薄くするだけにする。 --}}
            <div wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                <livewire:folder.tree
                    :currentFolderId="$currentFolderId"
                    :selectedFolderIds="$selectedFolderIds"
                    wire:key="folder-tree-stable"
                />
            </div>
            {{-- Tree Skeleton: 通信中（200ms〜）のみ表示 --}}
            <div wire:loading.delay wire:target="{{ $loadingTargets }}" class="p-4 space-y-3">
                @foreach (range(1, 5) as $i)
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-4 bg-base-content/10 rounded shimmer"></div>
                        <div class="h-4 bg-base-content/10 rounded w-{{ [1,2,3][rand(0,2)] }}/4 shimmer"></div>
                    </div>
                @endforeach
            </div>
        </x-slot:drawer>

        {{-- Always visible search section moved from RecordsTable --}}
        <div class="px-4 pt-4 sticky top-0 z-10 bg-base-200/80 backdrop-blur-md pb-4 rounded-b-2xl">
            <x-ledger.search
                :hasWorkflowEnabled="$hasWorkflowEnabled"
                :orderBy="$orderBy"
                :orderByLabel="$orderByLabel"
                :useSemanticSearch="$useSemanticSearch"
                :defaultSortColumns="$defaultSortColumns"
            />
        </div>

        <div class="container max-w-full px-0 md:px-4 mt-4">
            {{-- Main Content: 瞬時の移動で画面が消えないよう、.remove ではなく opacity 制御か delay を併用 --}}
            <div wire:loading.remove.delay wire:target="{{ $loadingTargets }}">
                <livewire:ledger.records-table
                    :search="$search"
                    :orderBy="$orderBy"
                    :orderAsc="$orderAsc"
                    :filterStatus="$filterStatus"
                    :filter="$filter"
                    :selectedLedgerDefineIds="$selectedLedgerDefineIds"
                    :selectedFolderIds="$selectedFolderIds"
                    :currentFolderId="$currentFolderId"
                    :displayLevel="$displayLevel"
                    :useSemanticSearch="$useSemanticSearch"
                    :useSynonym="$useSynonym"
                    :useTechnicalTerm="$useTechnicalTerm"
                    :perPage="$perPage"
                    wire:key="records-table-stable"
                />
            </div>

            {{-- Mega Skeleton: 通信中（200ms〜）のみ表示。 --}}
            <div wire:loading.delay wire:target="{{ $loadingTargets }}" class="w-full">
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
                                    <div class="bg-base-300 mt-0 px-4 py-4 rounded-t-box flex items-center gap-4 shimmer">
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


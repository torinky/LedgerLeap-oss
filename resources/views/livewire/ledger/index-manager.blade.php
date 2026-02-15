<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    @php
        // IndexManager で監視すべき主要なアクションとプロパティ
        // 重い処理（フォルダ切り替え、検索など）: スケルトンを表示する対象
        $heavyMethods = 'changeCurrentFolder,search';
        $heavyEvents = 'currentFolderChangeRequested';
        $heavyTargets = $heavyMethods . ',' . $heavyEvents;

        // 軽い処理（表示レベル変更、ソート、フィルタ、箇別のトグルなど）: 現在の表示を維持し、オーバーレイ/透過のみ行う対象
        $lightMethods =
            'displayLevel,updateDisplayLevel,sort,filter,updateFilterFromChild,filterStatus,perPage,orderBy,orderAsc,gotoPage,nextPage,previousPage,selectedLedgerDefineIds,selectedFolderIds,focusLedgerDefine,toggleFolderId,toggleLedgerDefineId,openPermissionModal,openActivityModal';
        // 子コンポーネントからの通知などは IndexManager 全体の透過 (opacity-50) で対応し、
        // 頻繁に発生するイベント (recordsUpdated) や個別のフィルタ (filterUpdated) は Tier 1 / Tier 2 の対象外にする
        $lightEvents =
            'displayLevelRequested,sortRequested,perPageUpdated,focusLedgerDefineRequested,folderIdToggled,ledgerDefineIdToggled,openPermissionModalRequested,openActivityModalRequested';
        $lightTargets = $lightMethods . ',' . $lightEvents;

        $allLoadingTargets = $heavyTargets . ',' . $lightTargets;
    @endphp

    <div class="relative min-h-screen">
        {{-- Tier 1: Global Loading Overlay --}}
        {{-- .delay.longest (1秒) を使用し、非常に重い通信の時のみ中央にスピナーを表示する --}}
        <div wire:loading.delay.longest wire:target="{{ $allLoadingTargets }}"
            class="fixed inset-0 z-[200] flex items-center justify-center pointer-events-none">
            {{-- 指定されたコンポーネントを使用。!static !inset-auto で確実に中央へ固定 --}}
            <x-element.loading-overlay tier="1" manual message="{{ __('ledger.loading') }}"
                class="!static !inset-auto !m-0" />
        </div>

        <x-slot:drawer>
            {{-- Tree Content: 瞬時に真っ白にならないよう opacity で制御。クリックを妨げない。 --}}
            <div wire:loading.class="opacity-50" wire:target="{{ $allLoadingTargets }}">
                <livewire:folder.tree :currentFolderId="$currentFolderId" :selectedFolderIds="$selectedFolderIds" :parentComponentId="$this->getId()"
                    wire:key="folder-tree-stable" />
            </div>
            {{-- Tree Skeleton: ヘビーな通信中（200ms〜）のみ表示 --}}
            <div wire:loading.delay wire:target="{{ $heavyTargets }}" class="p-4 space-y-3">
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
            {{-- ★★★ Info & Results Section (常に表示、Loading 時に透過) ★★★ --}}
            <div class="px-4" wire:loading.class="opacity-50" wire:target="{{ $allLoadingTargets }}">
                <div
                    class="info-block sticky top-24 z-10 space-y-2 py-2 bg-base-200/50 backdrop-blur-sm rounded-box px-4 shadow-sm border border-base-300/30 mb-6">
                    @php
                        $displayLevelOptions = [
                            ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                            ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                            ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                        ];
                    @endphp
                    <div class="flex flex-wrap items-center justify-end gap-4">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs font-bold opacity-50 uppercase tracking-widest">{{ __('ledger.form.display_level') }}</span>
                            <div x-data="{ level: {{ $displayLevel }} }" x-init="$watch('level', value => $wire.updateDisplayLevel(value))">
                                <x-mary-group x-model="level" :options="$displayLevelOptions"
                                    class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary"
                                    option-value="id" option-label="name" />
                            </div>
                        </div>
                    </div>

                    @if (!empty($this->highlights))
                        <div class="flex flex-wrap gap-2 items-center justify-center pt-2">
                            <span class="text-xs"><i
                                    class="fas fa-search mr-1 opacity-50"></i>{{ __('ledger.searched') }}</span>
                            @foreach ($this->keywords as $keyword)
                                <div
                                    class="badge {{ empty($this->synonyms[$keyword]) ? 'badge-neutral' : 'badge-primary' }} badge-md gap-2 py-3 shadow-sm border-none">
                                    <span class="font-bold">{{ $keyword }}</span>
                                    @if (!empty($this->synonyms[$keyword]))
                                        <div class="tooltip tooltip-bottom"
                                            data-tip="{{ implode(' / ', $this->synonyms[$keyword]) }}">
                                            <i class="fas fa-layer-group text-[10px] opacity-70"></i>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex justify-center flex-wrap gap-4 items-center pt-1">
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

                        {{-- totalRecords: レンダリングをブロックしないように条件分岐 --}}
                        @if ($this->totalRecords > 0)
                            <div class="badge badge-info bg-info/30 tooltip h-8 flex items-stretch min-w-16 shadow-sm border-none"
                                data-tip="{{ __('ledger.opened_count') }}">
                                <div class="self-center flex items-center gap-2 text-info-content/80">
                                    <i class="fas fa-list opacity-50"></i>
                                    <span class="font-bold">{{ $this->totalRecords }}</span>
                                </div>
                            </div>
                        @elseif(!empty($search) || !empty($filter))
                            {{-- 検索・フィルタ中はスケルトンを表示（計算中） --}}
                            <div
                                class="badge badge-ghost bg-base-300 h-8 flex items-stretch min-w-16 shadow-sm border-none animate-pulse">
                                <div class="self-center flex items-center gap-2">
                                    <i class="fas fa-list opacity-30"></i>
                                    <span class="font-bold opacity-30">...</span>
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

                {{-- Breadcrumbs Section --}}
                <div class="mt-4">
                    <div class="bg-base-300 text-base-content/70 rounded-box px-4 mb-4 font-bold">
                        <x-ledger.livewire-breadcrumbs :breadcrumbs="$this->breadcrumbs" />
                    </div>
                </div>

                {{-- Navigation Panels Section --}}
                <div class="relative group/nav min-h-[60px]">
                    {{-- Tier 2 overlay for selection toggles - doesn't hide content --}}
                    <x-element.loading-overlay tier="2"
                        target="selectedFolderIds,selectedLedgerDefineIds,toggleFolderId,toggleLedgerDefineId" />

                    <div>
                        @if ($this->currentFolder)
                            <div class="card bg-base-200/50 shadow-sm mb-4">
                                <div class="card-body p-4 flex flex-row items-center justify-between">
                                    <div>
                                        <h2 class="card-title text-base-content text-lg">
                                            <i class="fas fa-folder text-warning"></i>
                                            {{ $this->currentFolder->title }}
                                        </h2>
                                        <p class="text-sm text-base-content/70">
                                            {{ __('ledger.access_and_permissions.your_access_level') }}:
                                            @if ($this->currentUserPermissionForFolder)
                                                <span
                                                    class="badge badge-sm badge-{{ $this->currentUserPermissionForFolder->getColor() }} text-{{ $this->currentUserPermissionForFolder->getColor() }}-content font-bold">
                                                    {{ $this->currentUserPermissionForFolder->getLabel() }}
                                                </span>
                                            @else
                                                <span
                                                    class="badge badge-sm badge-outline">{{ __('ledger.access_and_permissions.no_direct_access') }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="card-actions">
                                        <x-mary-button
                                            wire:click="openPermissionModal('Folder', {{ $this->currentFolder->id }}, '{{ $this->currentFolder->title }}')"
                                            label="{{ __('ledger.access_and_permissions.title') }}"
                                            icon="o-shield-check" class="btn-sm btn-outline btn-ghost" spinner />
                                        <x-mary-button
                                            wire:click="openActivityModal('Folder', {{ $this->currentFolder->id }}, '{{ $this->currentFolder->title }}')"
                                            label="{{ __('ledger.activity.title') }}" icon="o-clock"
                                            class="btn-sm btn-outline btn-ghost" spinner />
                                    </div>
                                </div>
                            </div>
                        @endif

                        <x-folder.folder-and-ledger-panels :folderRecords="$this->folderRecords" :selectedFolderIds="$selectedFolderIds" :ledgerDefineRecords="$this->ledgerDefineRecords"
                            :selectedLedgerDefineIds="$selectedLedgerDefineIds" :currentTenantId="$currentTenantId" />
                    </div>
                </div>

                <div class="divider opacity-50"></div>
            </div>

            {{-- Result Area: heavy な時はスケルトン、light な時は透過 --}}
            <div class="px-4">
                {{-- Main Content: ヘビーな通信時は一定時間(200ms)経過後に隠す。ライトな通信時は表示を維持しつつ透明度だけ変える。 --}}
                <div wire:loading.remove.delay wire:target="{{ $heavyTargets }}">
                    <div wire:loading.class="opacity-50 pointer-events-none" wire:target="{{ $lightTargets }}">
                        <livewire:ledger.records-table :search="$search" :orderBy="$orderBy" :orderAsc="$orderAsc"
                            :filterStatus="$filterStatus" :filter="$filter" :selectedLedgerDefineIds="$selectedLedgerDefineIds" :selectedFolderIds="$selectedFolderIds"
                            :currentFolderId="$currentFolderId" :displayLevel="$displayLevel" :useSemanticSearch="$useSemanticSearch" :useSynonym="$useSynonym"
                            :useTechnicalTerm="$useTechnicalTerm" :perPage="$perPage" :defaultSortColumns="$defaultSortColumns" :hasWorkflowEnabled="$hasWorkflowEnabled"
                            :keywords="$this->keywords" :highlights="$this->highlights" :synonyms="$this->synonyms"
                            wire:key="ledger-records-table-stable" />
                    </div>
                </div>

                {{-- Mega Skeleton: 通信開始時に即座に表示して視覚的フィードバックを提供（ヘビーな通信のみ） --}}
                <div wire:loading wire:target="{{ $heavyTargets }}" class="w-full">
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
</div>

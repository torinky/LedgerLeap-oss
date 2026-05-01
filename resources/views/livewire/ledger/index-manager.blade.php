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

    $adminAnnouncements = app(\App\Services\AdminAnnouncementService::class)->notificationCenterAnnouncements();
@endphp

<div>
    @push('scripts')
        @vite(['resources/js/ledgerIndex.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    {{-- 秘密区分スタンプ（モックアップ） --}}
    <x-ledger.confidentiality-stamp level="confidential" :scopes="[['name' => '人事部'], ['name' => '経営層']]" />

    {{--
        台帳リスト初期化中オーバーレイ
        - Alpine.js x-data で制御。livewire:navigated を @window で受け取り非表示にする。
        - Livewireのルート<div>内に配置することで Livewire のレンダリングに含まれる。
        - position:fixed のためビューポート全体をカバーし、ボタン等を pointer-events:none で素通しする。
        - フォールバック: 8秒後にタイムアウトで強制非表示。
        - x-show + x-transition で opacity フェードアウト。
        - x-data内でfunction記法を使用（Bladeコンパイラのクロージャ誤解釈を回避）
    --}}
    <div id="ledger-init-overlay"
         x-data="ledgerInitOverlay()"
         x-show="visible"
         x-transition:leave="transition-opacity duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:ledger-init-overlay:timing.window="console.debug('[ledger-init-overlay]', $event.detail)"
         x-on:livewire:navigated.window.once="hide('livewire:navigated')"
         x-on:livewire:load.window.once="hide('livewire:load')"
         x-init="startFallbackTimer()"
         class="fixed inset-0 z-150 bg-base-100/75 backdrop-blur-sm flex items-end justify-center pb-10 pointer-events-none">
        <div class="flex items-center gap-3 bg-base-200/90 backdrop-blur rounded-full px-5 py-2 shadow-lg border border-base-300">
            <span class="loading loading-spinner loading-sm text-primary"></span>
            <span class="text-xs font-bold tracking-wider opacity-75 text-base-content">
                {{ __('ledger.loading') }}
            </span>
        </div>
    </div>

    <div class="relative min-h-screen">
        @if (! empty($adminAnnouncements))
            <x-admin.announcement-stack
                :announcements="$adminAnnouncements"
                :banner-sync-offset="false"
                :banner-respect-dismissed="true"
                :banner-dismissible="true"
                banner-container-class="m-0"
            />
        @endif

        {{-- Tier 1: Global Loading Overlay --}}
        {{-- .delay.longest (1秒) を使用し、非常に重い通信の時のみ中央にスピナーを表示する --}}
        <div wire:loading.delay.longest wire:target="{{ $allLoadingTargets }}"
             class="fixed inset-0 z-200 flex items-center justify-center pointer-events-none">
            {{-- 指定されたコンポーネントを使用。!static !inset-auto で確実に中央へ固定 --}}
            <x-element.loading-overlay tier="1" manual message="{{ __('ledger.loading') }}"
                                       class="static! inset-auto! m-0!"/>
        </div>

        <x-slot:drawer>
            {{--
                Sprint 7: w-full min-w-0 を付与し ul.menu（flex column コンテナ）内で
                flex child としての幅制約を機能させる。これがないと min-width: auto で
                コンテンツ幅（深い階層では400px超）に拡張してしまう。
                Tree Content: 瞬時に真っ白にならないよう opacity で制御。クリックを妨げない。
            --}}
            <div class="w-full min-w-0" wire:loading.class="opacity-50" wire:target="{{ $allLoadingTargets }}">
                <livewire:folder.tree :currentFolderId="$currentFolderId" :selectedFolderIds="$selectedFolderIds"
                                      :parentComponentId="$this->getId()"
                                      wire:key="folder-tree-stable"/>
            </div>
            {{-- Tree Skeleton: ヘビーな通信中（200ms〜）のみ表示 --}}
            <div wire:loading.delay wire:target="{{ $heavyTargets }}" class="p-4 space-y-3">
                @foreach (range(1, 5) as $i)
                    <div class="flex items-center gap-2">
                        <div class="h-4 w-4 bg-base-content/10 rounded shimmer"></div>
                        <div class="h-4 bg-base-content/10 rounded w-{{ [1, 2, 3][random_int(0, 2)] }}/4 shimmer"></div>
                    </div>
                @endforeach
            </div>
        </x-slot:drawer>

        {{-- Always visible search section moved from RecordsTable --}}
        <div class="sticky top-0 z-10 bg-base-100/80 backdrop-blur-sm px-4 pt-2 pb-2">
            <x-ledger.search :hasWorkflowEnabled="$hasWorkflowEnabled" :orderBy="$orderBy" :orderByLabel="$orderByLabel"
                             :useSemanticSearch="$useSemanticSearch"
                             :defaultSortColumns="$defaultSortColumns" :search="$search" :perPage="$perPage"
                             :displayLevel="$displayLevel"
                             :totalRecords="$this->totalRecords" :totalRecordsLoaded="$this->totalRecordsLoaded"
                             :useSynonym="$useSynonym"
                             :useTechnicalTerm="$useTechnicalTerm" :orderAsc="$orderAsc" :filterStatus="$filterStatus"/>
        </div>

        <div class="container max-w-full px-0 md:px-4 mt-4">
            {{-- ★★★ Info & Results Section (常に表示、Loading 時に透過) ★★★ --}}
            <div class="px-4" wire:loading.class="opacity-50" wire:target="{{ $allLoadingTargets }}">
                <div
                        class="info-block sticky top-24 z-10 space-y-2 py-2 bg-base-200/50 backdrop-blur-sm rounded-box px-4 shadow-sm border border-base-300/30 mb-6 items-center">

                    @php
                        $hasSearchKeywords = ! empty($this->highlights);
                        $hasSearchTags = ! empty($tags);
                        $searchGridClass = '';
                        if ($hasSearchKeywords && $hasSearchTags) {
                            $searchGridClass = 'md:grid-cols-3';
                        } elseif ($hasSearchKeywords || $hasSearchTags) {
                            $searchGridClass = 'md:grid-cols-2';
                        }
                    @endphp

                    <div class="grid grid-cols-1 {{ $searchGridClass }}">

                        @if (!empty($this->highlights))
                            <div class="flex flex-wrap gap-2 items-center justify-center pb-2 md:pb-0">
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

                        @if (!empty($tags))
                            <div class="flex flex-wrap gap-2 items-center justify-center pb-2 md:pb-0">
                                <span class="text-xs">
                                    <i class="fas fa-tag mr-1 opacity-50"></i>{{ __('ledger.search_tag_active') }}
                                </span>
                                @foreach ($tags as $tag)
                                    <div class="badge badge-secondary badge-md gap-2 py-3 shadow-sm border-none">
                                        <span class="font-bold">#{{ $tag }}</span>
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

                            <div
                                    wire:ignore
                                    x-data="{
                        totalRecords: {{ (int) $totalRecords }},
                        totalRecordsLoaded: {{ $totalRecordsLoaded ? 'true' : 'false' }},
                    }"
                                    @ledger-records-count-updated.window="totalRecords = $event.detail.total; totalRecordsLoaded = true"
                            >
                                <div
                                        class="badge badge-info bg-info/30 tooltip h-8 flex items-stretch min-w-16 shadow-sm border-none"
                                        data-tip="{{ __('ledger.opened_count') }}"
                                        x-show="totalRecordsLoaded"
                                        x-cloak>
                                    <div class="self-center flex items-center gap-2 text-info-content/80">
                                        <i class="fas fa-list opacity-50"></i>
                                        <span class="font-bold" x-text="totalRecords">{{ $totalRecords }}</span>
                                    </div>
                                </div>
                                <div
                                        class="badge badge-ghost bg-base-300 h-8 flex items-stretch min-w-16 shadow-sm border-none animate-pulse"
                                        data-tip="{{ __('ledger.opened_count') }}"
                                        x-show="!totalRecordsLoaded"
                                        x-cloak>
                                    <div class="self-center flex items-center gap-2">
                                        <i class="fas fa-list opacity-30"></i>
                                        <span class="font-bold opacity-30">...</span>
                                    </div>
                                </div>
                            </div>

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
                                <x-mary-button wire:click="sort('default')"
                                               label="{{ __('ledger.actions.reset_sort') }}"
                                               icon="o-arrow-path" class="btn-xs btn-outline btn-info h-8" spinner/>
                            @endif
                        </div>

                    </div>

                </div>

                {{-- Consolidated Navigation & Folder Info Card --}}
                <x-mary-card shadow class="bg-base-100 border border-base-300 overflow-visible relative group/nav">
                    {{-- Tier 2 overlay for stable navigation UX --}}
                    <x-element.loading-overlay tier="2"
                                               target="{{ $heavyTargets }},selectedFolderIds,selectedLedgerDefineIds,toggleFolderId,toggleLedgerDefineId"/>

                    <x-slot:title>
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-start w-full">
                            {{-- Title & Breadcrumbs Area --}}
                            <div class="space-y-3 min-w-0">
                                {{-- Breadcrumbs embedded in card header --}}
                                <div class="bg-base-200/50 text-base-content/60 rounded-lg px-3 py-1 text-sm font-medium w-fit max-w-full overflow-hidden">
                                    <x-ledger.livewire-breadcrumbs :breadcrumbs="$this->breadcrumbs" />
                                </div>

                                {{-- Folder Title & Badge Area --}}
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    @if($this->currentFolder)
                                        <h2 class="text-xl md:text-2xl font-black tracking-tighter text-base-content flex items-center gap-2 truncate">
                                            <i class="fas fa-folder text-warning/80"></i>
                                            {{ $this->currentFolder->title }}
                                        </h2>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            @if ($this->currentUserPermissionForFolder)
                                                <div class="badge badge-{{ $this->currentUserPermissionForFolder->getColor() }} badge-md font-bold px-3 py-0.5 shadow-sm">
                                                    {{ $this->currentUserPermissionForFolder->getLabel() }}
                                                </div>
                                            @else
                                                <div class="badge badge-outline badge-md opacity-50 font-medium">
                                                    {{ __('ledger.access_and_permissions.no_direct_access') }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <h2 class="text-xl md:text-2xl font-black tracking-tighter text-base-content flex items-center gap-2">
                                            <i class="fas fa-home text-primary/80"></i>
                                            {{ __('ledger.folder.root') }}
                                        </h2>
                                    @endif
                                </div>
                            </div>

                            {{-- Actions Area --}}
                            @if($this->currentFolder)
                                <div class="flex flex-wrap md:flex-nowrap items-center gap-2 pt-1 md:pt-0">
                                    <x-mary-button
                                        wire:click="openPermissionModal('Folder', {{ $this->currentFolder->id }}, '{{ $this->currentFolder->title }}')"
                                        label="{{ __('ledger.access_and_permissions.title') }}"
                                        icon="o-shield-check"
                                        class="btn-sm btn-ghost bg-base-200/50 hover:bg-base-300 border-none font-bold"
                                        spinner />
                                    <x-mary-button
                                        wire:click="openActivityModal('Folder', {{ $this->currentFolder->id }}, '{{ $this->currentFolder->title }}')"
                                        label="{{ __('ledger.activity.title') }}"
                                        icon="o-clock"
                                        class="btn-sm btn-ghost bg-base-200/50 hover:bg-base-300 border-none font-bold"
                                        spinner />
                                </div>
                            @endif
                        </div>
                    </x-slot:title>

                    {{-- Navigation Panels Area --}}
                    <div class="mt-2 text-base-content">
                        <x-folder.folder-and-ledger-panels :folderRecords="$this->folderRecords"
                                                           :selectedFolderIds="$selectedFolderIds"
                                                           :ledgerDefineRecords="$this->ledgerDefineRecords"
                                                           :selectedLedgerDefineIds="$selectedLedgerDefineIds"
                                                           :currentTenantId="$currentTenantId"/>
                    </div>
                </x-mary-card>

                <div class="divider opacity-30 my-6"></div>
            </div>

            {{-- Result Area: heavy な時はスケルトン、light な時は透過 --}}
            <div class="px-4">
                {{-- Main Content: ヘビーな通信時は一定時間(200ms)経過後に隠す。ライトな通信時は表示を維持しつつ透明度だけ変える。 --}}
                <div wire:loading.remove.delay wire:target="{{ $heavyTargets }}">
                    <div wire:loading.class="opacity-50 pointer-events-none" wire:target="{{ $lightTargets }}">
                        <livewire:ledger.records-table :search="$search" :orderBy="$orderBy" :orderAsc="$orderAsc"
                                                       :filterStatus="$filterStatus" :filter="$filter"
                                                       :selectedLedgerDefineIds="$selectedLedgerDefineIds"
                                                       :selectedFolderIds="$selectedFolderIds"
                                                       :currentFolderId="$currentFolderId" :displayLevel="$displayLevel"
                                                       :useSemanticSearch="$useSemanticSearch" :useSynonym="$useSynonym"
                                                       :useTechnicalTerm="$useTechnicalTerm" :perPage="$perPage"
                                                       :defaultSortColumns="$defaultSortColumns"
                                                       :hasWorkflowEnabled="$hasWorkflowEnabled"
                                                       :keywords="$this->keywords" :tags="$tags" :highlights="$this->highlights"
                                                       :synonyms="$this->synonyms"
                                                       wire:key="ledger-records-table-stable"/>
                    </div>
                </div>

                {{-- Mega Skeleton: 通信開始時に即座に表示して視覚的フィードバックを提供（ヘビーな通信のみ） --}}
                <div wire:loading wire:target="{{ $heavyTargets }}" class="w-full">
                    <div class="px-4">
                        {{-- Unified Navigation Card Skeleton --}}
                        <div class="card bg-base-100 border border-base-300 shadow-sm mb-6">
                            <div class="card-body p-6 space-y-6">
                                <div class="space-y-3">
                                    {{-- Breadcrumb skeleton --}}
                                    <div class="h-6 bg-base-300 rounded-lg w-1/3 shimmer"></div>
                                    {{-- Title & Badge skeleton --}}
                                    <div class="flex items-center gap-4">
                                        <div class="h-8 bg-base-300 rounded-lg w-1/2 shimmer"></div>
                                        <div class="h-6 bg-base-300 rounded-full w-20 shimmer"></div>
                                    </div>
                                </div>
                                {{-- Panel grid skeleton --}}
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    @foreach(range(1, 4) as $i)
                                        <div class="h-16 bg-base-200 rounded-xl shimmer"></div>
                                    @endforeach
                                </div>
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
                                        <x-element.skeleton-table rows="8" cols="10"/>
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


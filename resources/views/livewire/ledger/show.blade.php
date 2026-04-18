<div>
    @php
        use App\Enums\WorkflowStatus;
        // Navigation targets that should affect the entire tab content area
        $tabNavTargets = 'selectedTab,switchToHistoryTab,activateCompareWithPrevious';
        // 現在のタブ再訪時のローディング判定に使う簡易トリガー
        $tabSwitchTargets = 'selectedTab';
        // Detail-specific filter targets that should only affect the record content
        $recordFilterTargets = 'displayLevel,showChanges,targetDiffId,baseDiffId';
        // ワークフロー履歴タブのタイトル
        $historyTabTitle = $ledgerRecord->define->workflow_enabled
            ? __('ledger.tab.workflow_history')
            : __('ledger.history_title');
    @endphp

    {{-- Alpine.store を確実に初期化するためのスクリプト --}}
    <script>
        // Alpine.jsの初期化イベント内でストアを登録
        document.addEventListener('alpine:init', () => {
            console.log('[LedgerState] alpine:init event fired, registering store...');

            // 既に登録済みかチェック
            try {
                if (Alpine.store('ledgerState')) {
                    console.log('[LedgerState] Store already exists, skipping');
                    return;
                }
            } catch (e) {
                // ストアが存在しない場合は登録を続行
            }

            Alpine.store('ledgerState', {
                states: JSON.parse(localStorage.getItem('ledger_collapsed_states') || '{}'),
                currentLedgerId: null,

                init(ledgerId) {
                    console.log('[LedgerState] init() called with ledgerId:', ledgerId);
                    this.currentLedgerId = ledgerId;
                    if (!this.states[ledgerId]) {
                        this.states[ledgerId] = {};
                        console.log('[LedgerState] Created new state for ledgerId:', ledgerId);
                    } else {
                        console.log('[LedgerState] Loaded existing state for ledgerId:', ledgerId, this
                            .states[ledgerId]);
                    }
                },

                reload() {
                    console.log('[LedgerState] reload() called, refreshing from localStorage');
                    this.states = JSON.parse(localStorage.getItem('ledger_collapsed_states') || '{}');
                    if (this.currentLedgerId) {
                        console.log('[LedgerState] Reloaded state for ledgerId:', this.currentLedgerId, this
                            .states[this.currentLedgerId]);
                    }
                },

                isCollapsed(groupName, isRequired = false) {
                    if (!this.currentLedgerId) {
                        console.log('[LedgerState] isCollapsed() called but currentLedgerId is null');
                        return false;
                    }
                    const ledgerStates = this.states[this.currentLedgerId];
                    
                    // 1. 個別設定があれば最優先
                    if (ledgerStates[groupName] !== undefined) {
                        return ledgerStates[groupName];
                    }
                    
                    // 2. グローバル設定があればそれに従う
                    if (ledgerStates['__global__'] !== undefined) {
                        return ledgerStates['__global__'];
                    }

                    // 3. デフォルト
                    const defaultValue = !isRequired;
                    return defaultValue;
                },

                toggle(groupName, isRequired = false) {
                    if (!this.currentLedgerId) {
                        console.log('[LedgerState] toggle() called but currentLedgerId is null');
                        return;
                    }
                    const newValue = !this.isCollapsed(groupName, isRequired);
                    this.states[this.currentLedgerId][groupName] = newValue;
                    localStorage.setItem('ledger_collapsed_states', JSON.stringify(this.states));
                    console.log('[LedgerState] toggle(' + groupName + ') to:', newValue,
                        'Saved to localStorage');
                },

                expandAll(isExpand) {
                    if (!this.currentLedgerId) return;
                    console.log('[LedgerState] expandAll():', isExpand);
                    
                    // 個別のステータスをすべてクリアし、グローバル設定のみをセットする
                    // これにより、すべての（未ロードのもの含む）グループがこのフラグを初期値として参照するようになる
                    this.states[this.currentLedgerId] = {
                        '__global__': !isExpand
                    };
                    
                    localStorage.setItem('ledger_collapsed_states', JSON.stringify(this.states));
                }
            });

            console.log('[LedgerState] Alpine.store registered successfully');
        });
    </script>

    {{-- Tier 1: Global Loading removed to keep tab headers interactive during transitions --}}

    <div class="p-0 rounded-b-xl sm:w-full relative"
         x-data="{
             activeTab: @js($selectedTab),
             loadedTabs: @js($loadedTabs),
             relatedCount: @js($relatedCount),

             init() {
                 {{-- Livewire側でselectedTab / relatedCount が変更された場合にAlpineへ同期する --}}
                 $wire.$watch('selectedTab', (value) => {
                     this.activeTab = value;
                     this.markLoaded(value);
                     this.updateUrl(value);
                 });

                 $wire.$watch('relatedCount', (value) => {
                     this.relatedCount = value;
                 });
             },

             isLoaded(tab) {
                 return this.loadedTabs.includes(tab);
             },

             markLoaded(tab) {
                 if (!this.loadedTabs.includes(tab)) {
                     this.loadedTabs.push(tab);
                 }
             },

             updateUrl(tab) {
                 const url = new URL(window.location.href);
                 url.searchParams.set('tab', tab);
                 window.history.replaceState({}, '', url);
             },

             switchTab(tab) {
                 if (this.activeTab === tab) {
                     return;
                 }

                 this.activeTab = tab;
                 this.updateUrl(tab);

                 if (!this.isLoaded(tab)) {
                     this.markLoaded(tab);
                     {{-- 初回訪問時のみ Livewire に通知して DOM を追加する --}}
                     $wire.call('notifyTabChange', tab);
                 }
             }
         }"
         x-cloak>
        {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
             台帳ヘッダー: 常に表示される基本情報（パンくず、メタ、タイトル、説明）
             ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
        <x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
            <x-slot:title>
                <div class="flex flex-col w-full">
                    <div class="flex items-center gap-3 w-full">
                        <div class="flex-shrink-0 hidden md:block">
                            <i class="fas fa-list text-info/60 text-xl"></i>
                        </div>
                        <div class="flex flex-col min-w-0 w-full">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                                <div class="min-w-0">
                                    <x-ledger.livewire-breadcrumbs
                                        :thisLedgerDefine="$ledgerDefineRecord"
                                        :breadcrumbs="$breadcrumbs"
                                        :isLivewire="false" />
                                </div>

                                {{-- Metadata Area: Breadcrumb line integration --}}
                                <div class="flex flex-wrap items-center gap-3 text-xs md:text-sm shrink-0 bg-base-200/60 p-1.5 rounded-lg border border-base-300">
                                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-primary/10 border border-primary/20">
                                        <span class="text-primary font-bold uppercase tracking-tighter text-sm">{{ __('ledger.version') }}</span>
                                        <span class="font-bold text-primary">{{ $ledgerRecord->version }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/30">
                                        <x-mary-icon name="o-user" class="w-4 h-4 text-base-content/40" />
                                        <x-ledger.user-card-popover :user="$ledgerRecord->modifier" />
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                        <x-mary-icon name="o-calendar" class="w-3.5 h-3.5" />
                                        <span>{{ $ledgerRecord->updated_at->format('Y-m-d H:i') }}</span>
                                    </div>
                                </div>
                            </div>
                            <h2 class="text-lg md:text-xl font-black tracking-tighter text-base-content flex items-center gap-2 truncate">
                                <i class="fas fa-book-open text-base-content/30 text-lg md:hidden"></i>
                                <span class="truncate">{{ $ledgerDefineRecord->title }}</span>
                            </h2>
                        </div>
                    </div>
                </div>
            </x-slot:title>

            @if($ledgerDefineRecord->detail_description)
                <div class="mt-4 text-base-content" x-data="{ expanded: false }">
                    <div class="bg-base-200/70 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/90">
                        <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                            <div class="font-bold text-sm flex items-center gap-2">
                                <x-mary-icon name="o-information-circle" class="w-4 h-4 text-info" />
                                {{ __('ledger.description') }} / {{ __('ledger.guideline') }}
                            </div>
                            <span class="inline-flex transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                                <x-mary-icon name="o-chevron-down" class="w-4 h-4" />
                            </span>
                        </div>
                        <div x-show="expanded" x-collapse>
                            <div class="pt-3 mt-2 border-t border-base-300">
                                @php
                                    $detailDescriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                        app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->detail_description),
                                        null,
                                        $ledgerDefineRecord
                                    );
                                @endphp
                                <div class="prose prose-sm text-sm leading-relaxed max-w-none prose-p:my-2 prose-headings:mb-2 prose-headings:mt-4">
                                    {!! $detailDescriptionHtml !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </x-mary-card>

        {{-- タブボタン: wire:ignore でAlpine状態がLivewireのDOM diffingでリセットされるのを防ぐ --}}
        <div wire:ignore>
            <div role="tablist" class="tabs tabs-lift tabs-xl">
                <button role="tab" class="tab"
                    :class="{ 'tab-active': activeTab === 'details' }"
                    @click="switchTab('details')">
                    <x-mary-icon name="o-document-text" class="mr-1.5 w-4 h-4" />
                    {{ __('ledger.tab.details') }}
                </button>
                <button role="tab" class="tab"
                    :class="{ 'tab-active': activeTab === 'history' }"
                    @click="switchTab('history')">
                    <x-mary-icon name="o-list-bullet" class="mr-1.5 w-4 h-4" />
                    {{ $historyTabTitle }}
                </button>
                <button role="tab" class="tab"
                    :class="{ 'tab-active': activeTab === 'activity' }"
                    @click="switchTab('activity')">
                    <x-mary-icon name="o-clock" class="mr-1.5 w-4 h-4" />
                    {{ __('ledger.tab.activity_history') }}
                </button>
                <button role="tab" class="tab"
                    :class="{ 'tab-active': activeTab === 'permissions' }"
                    @click="switchTab('permissions')">
                    <x-mary-icon name="o-shield-check" class="mr-1.5 w-4 h-4" />
                    {{ __('ledger.tab.access_and_permissions') }}
                </button>
                <button role="tab" class="tab"
                    :class="{ 'tab-active': activeTab === 'related' }"
                    @click="switchTab('related')">
                    <x-mary-icon name="o-link" class="mr-1.5 w-4 h-4" />
                    <span>{{ __('ledger.tab.related') }}</span>
                    <span class="badge badge-neutral badge-sm ml-1" x-cloak x-show="relatedCount > 0" x-text="relatedCount"></span>
                </button>
            </div>
        </div>

        {{-- タブコンテンツエリア --}}
        <div class="flex flex-col mb-40 mt-4">

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- 基本情報タブ                                                 --}}
            {{-- 常にロード済み（mount()で loadedTabs に初期追加される）       --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'details'"
                 class="space-y-4 relative min-h-[400px]">

                {{-- Tier 2: Overall tab/record loading --}}
                <x-element.loading-overlay tier="2" :target="$recordFilterTargets" :delay="false">

                    <x-mary-card title="{{ __('ledger.tab.details') }}" shadow icon="o-document-text" class="bg-base-100 mb-6">
                        <x-slot:menu>
                            @php
                                $displayLevelOptions = [
                                    ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                                    ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                                    ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                                ];
                            @endphp
                            <div class="flex items-center gap-1 mr-2">
                                <x-mary-group wire:model.live="displayLevel" :options="$displayLevelOptions"
                                    class="[&_label]:btn-ghost [&_input:checked+label]:!btn-primary" option-value="id"
                                    option-label="name" wire:key="details-display-level-group" />
                                <div class="tooltip" data-tip="{{ __('ledger.workflow.guide.display_level') }}">
                                    <x-mary-icon name="o-question-mark-circle"
                                        class="w-4 h-4 text-base-content/40 cursor-help" />
                                </div>
                            </div>
                            <div class="flex items-center gap-1 border-l border-base-300 pl-3">
                                <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}" tight
                                    class="text-xs" />
                                <div class="tooltip" data-tip="{{ __('ledger.workflow.guide.details_compare') }}">
                                    <x-mary-icon name="o-question-mark-circle"
                                        class="w-4 h-4 text-base-content/40 cursor-help" />
                                </div>
                            </div>
                        </x-slot:menu>

                        <div class="relative min-h-[300px]">
                            <livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" :canView="$canView" :allAttachments="$currentLedgerAttachments"
                                :highlight="$highlight" :displayLevel="$displayLevel" :showChanges="$showChanges" :targetDiffId="$targetDiffId" :baseDiffId="null"
                                wire:key="diff-viewer-{{ $ledgerRecord->id }}-{{ $ledgerRecord->updated_at?->timestamp }}"
                                lazy />
                        </div>
                    </x-mary-card>

                    {{-- Skeleton for internal state changes --}}
                    <div class="card bg-base-100 shadow-xl opacity-0 absolute top-0 left-0 w-full -z-10"
                         wire:loading.class="opacity-100 z-10">
                        <div class="card-body">
                            {{-- ... existing skeleton ... --}}
                            <div class="flex items-center justify-between mb-4">
                                <div class="h-6 bg-base-300 rounded w-32 shimmer"></div>
                                <div class="flex gap-2">
                                    <div class="h-8 bg-base-200 rounded w-24 shimmer"></div>
                                    <div class="h-8 bg-base-200 rounded w-24 shimmer"></div>
                                </div>
                            </div>
                            <div class="space-y-6">
                                @foreach(range(1, 3) as $group)
                                    <div class="space-y-3">
                                        <div class="h-5 bg-base-300 rounded w-40 shimmer"></div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @foreach(range(1, 4) as $field)
                                                <div class="space-y-2">
                                                    <div class="h-4 bg-base-200 rounded w-24 shimmer"></div>
                                                    <div class="h-10 bg-base-100 border border-base-300 rounded shimmer"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-element.loading-overlay>

                {{-- Actual content stays mounted; loading is layered on top only --}}
                <div class="space-y-4">
                    @if ($ledgerRecord->define->workflow_enabled)
                        <livewire:ledger.workflow-status-card :ledgerRecord="$ledgerRecord"
                            wire:key="status-card-{{ $ledgerRecord->id }}-{{ $ledgerRecord->updated_at?->timestamp }}" />
                    @endif

                    <x-mary-card title="{{ __('ledger.details') }}" shadow separator icon="o-document-text">
                        <x-slot:menu>
                            @php
                                $displayLevelOptions = [
                                    ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                                    ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                                    ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                                ];
                            @endphp
                            <div class="flex items-center gap-1 mr-2 pr-2 border-r border-base-300">
                                <div class="flex items-center gap-2 bg-base-200/50 px-3 py-1 rounded-full border border-base-300 transition-all hover:bg-base-200/80">
                                    <x-mary-toggle 
                                        x-data="{ 
                                            active: false,
                                            check() { 
                                                const id = $store.ledgerState.currentLedgerId;
                                                this.active = !($store.ledgerState.states[id]?.['__global__'] ?? true);
                                            }
                                        }"
                                        x-init="
                                            check();
                                            setInterval(() => check(), 500);
                                        "
                                        @click="$store.ledgerState.expandAll(!active)"
                                        ::checked="active"
                                        tight
                                        label="{{ __('ledger.column.expand_all') }}"
                                        class="toggle-xs toggle-primary text-sm font-black text-base-content/40 uppercase tracking-widest"
                                    />
                                </div>
                                <div class="flex items-center gap-1 ml-1">
                                    <x-mary-group wire:model.live="displayLevel" :options="$displayLevelOptions"
                                        class="[&_label]:btn-ghost [&_input:checked+label]:!btn-primary" option-value="id"
                                        option-label="name" wire:key="details-display-level-group" />
                                    <div class="tooltip" data-tip="{{ __('ledger.workflow.guide.display_level') }}">
                                        <x-mary-icon name="o-question-mark-circle"
                                            class="w-4 h-4 text-base-content/40 cursor-help" />
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 border-l border-base-300 pl-3">
                                <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}" tight
                                    class="text-xs" />
                                <div class="tooltip" data-tip="{{ __('ledger.workflow.guide.details_compare') }}">
                                    <x-mary-icon name="o-question-mark-circle"
                                        class="w-4 h-4 text-base-content/40 cursor-help" />
                                </div>
                            </div>
                        </x-slot:menu>

                        <div class="relative min-h-[300px]">
                            {{-- Record content only loading is now handled by the parent's skeleton --}}
                            <livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" :canView="$canView" :allAttachments="$currentLedgerAttachments"
                                :highlight="$highlight" :displayLevel="$displayLevel" :showChanges="$showChanges" :targetDiffId="$targetDiffId" :baseDiffId="null"
                                wire:key="diff-viewer-{{ $ledgerRecord->id }}-{{ $ledgerRecord->updated_at?->timestamp }}"
                                lazy />
                        </div>

                        <div class="mt-8 pt-4 border-t border-base-200">
                            <div class="flex flex-col md:flex-row md:items-center justify-end gap-4 text-sm">
                                <div class="flex flex-wrap items-center gap-3">
                                    {{-- ナッジリンク: 直前比較 --}}
                                    @if (!$this->isComparingWithPrevious())
                                        <x-mary-button icon="o-arrow-path" :label="__('ledger.diff.nudge_view_changes')"
                                            wire:click="activateCompareWithPrevious"
                                            class="btn-sm btn-ghost text-primary hover:bg-primary/10" />
                                    @endif

                                    {{-- ナッジリンク: 履歴タブへ --}}
                                    <x-mary-button icon="o-clock" :label="__('ledger.diff.nudge_view_history')" wire:click="switchToHistoryTab"
                                        class="btn-sm btn-ghost text-base-content/60" />
                                </div>
                            </div>
                        </div>
                    </x-mary-card>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- ワークフロー履歴タブ                                         --}}
            {{-- 初回訪問まで DOM に追加しない（@if isTabLoaded）             --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'history'"
                 class="shadow-md relative min-h-[400px]">
                @if ($this->isTabLoaded('history'))
                    <livewire:ledger.ledger-history-manager :ledgerId="$ledgerRecord->id" :displayLevel="$displayLevel" :highlight="$highlight"
                        wire:key="history-manager-{{ $ledgerRecord->id }}" />
                @else
                    {{-- 初回訪問前はスケルトンローディングを表示 --}}
                    <x-element.loading-overlay tier="2" :manual="true" />
                @endif
            </div>


            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- 総合活動履歴タブ                                             --}}
            {{-- 初回訪問まで DOM に追加しない（@if isTabLoaded）             --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'activity'"
                 class="shadow-md relative min-h-[400px]">
                @if ($this->isTabLoaded('activity'))
                    {{-- テスト実行時はレンダリングしない --}}
                    @if (app()->environment() !== 'testing')
                        <livewire:common.activity-history-display :resourceId="$ledgerRecord->id" resourceType="Ledger"
                            :includeRelatedResources="true" :hiddenColumns="['subject']" wire:key="activity-history-{{ $ledgerRecord->id }}" />
                    @else
                        <div id="activity-history-placeholder-for-testing"></div>
                    @endif
                @else
                    <x-element.loading-overlay tier="2" :manual="true" />
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- アクセスと権限タブ                                           --}}
            {{-- 初回訪問まで DOM に追加しない（@if isTabLoaded）             --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'permissions'"
                 class="shadow-md relative min-h-[400px]">
                @if ($this->isTabLoaded('permissions'))
                    {{-- テスト実行時はレンダリングしない --}}
                    @if (app()->environment() !== 'testing')
                        <livewire:common.permission-display :resourceId="$ledgerRecord->id" resourceType="Ledger"
                            wire:key="permission-display-{{ $ledgerRecord->id }}" />
                    @else
                        <div id="permission-display-placeholder-for-testing"></div>
                    @endif
                @else
                    <x-element.loading-overlay tier="2" :manual="true" />
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- 関連案件タブ                                                 --}}
            {{-- @if isTabLoaded + defer: DOM追加と同時に即時ロード開始        --}}
            {{-- 2回目以降は x-show のみで切替（Livewireリクエスト不要）      --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'related'"
                 class="shadow-md relative min-h-[400px]">
                @if ($this->isTabLoaded('related'))
                    {{-- defer: DOMに追加された瞬間に即時ロード開始（lazy とは異なりビューポート不要） --}}
                    <livewire:ledger.related-ledgers
                        :ledgerId="$ledgerRecord->id"
                        :displayLevel="$displayLevel"
                        wire:key="related-ledgers-{{ $ledgerRecord->id }}"
                        defer />
                @else
                    {{-- 初回訪問前のスケルトン --}}
                    <div class="space-y-4 p-2 w-full animate-pulse">
                        <div class="flex items-center gap-4 p-3 bg-base-200/40 rounded-lg">
                            <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
                            <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
                        </div>
                        <x-element.skeleton-table rows="5" cols="5" />
                    </div>
                @endif
            </div>

        </div>{{-- end タブコンテンツエリア --}}

        {{-- フッターパネル (アクションボタン集約) --}}
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-20">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                {{-- 透明度調整 --}}
                <div class="card-body p-4">
                    <livewire:ledger.workflow-action-buttons :ledgerRecord="$ledgerRecord"
                        wire:key="action-buttons-{{ $ledgerRecord->id }}-{{ $ledgerRecord->updated_at?->timestamp }}" />
                    @livewire('workflow.workflow-comment-modal', ['ledgerId' => $ledgerRecord->id], 'workflow-comment-modal-show')


                </div>

            </div>

        </div>
    </div>

    {{-- 添付ファイルのファイルインスペクタを常駐配置 --}}
    <livewire:attached-file.file-inspector :isInLedgerDetailPage="true" />

    {{-- ロールバック確認モーダル --}}
    <livewire:ledger.rollback-confirm-modal />
</div>

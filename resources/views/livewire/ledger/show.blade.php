<div>
    @php
        use App\Enums\WorkflowStatus;
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
                    if (ledgerStates[groupName] !== undefined) {
                        console.log('[LedgerState] isCollapsed(' + groupName + '):', ledgerStates[
                            groupName]);
                        return ledgerStates[groupName];
                    }
                    const defaultValue = !isRequired;
                    console.log('[LedgerState] isCollapsed(' + groupName + ') using default:', defaultValue,
                        '(isRequired=' + isRequired + ')');
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
                }
            });

            console.log('[LedgerState] Alpine.store registered successfully');
        });
    </script>

    <div class="p-0 rounded-b-xl sm:w-full"> {{-- パディング調整 --}}

        {{-- タブ UI の導入 --}}
        <x-mary-tabs wire:model="selectedTab" activeClass="border-b-0" labelDivClass="tabs tabs-lift tabs-xl ml-4"
            tabsClass="flex flex-col mb-10" class="w-full">
            {{-- 下にマージン追加 --}}

            {{-- 基本情報タブ --}}
            <x-mary-tab name="details" label="{{ __('ledger.tab.details') }}" icon="o-document-text"
                class="shadow-lg space-y-4">
                {{--                <x-mary-header title="{{ __('ledger.tab.details') }}" icon="o-document-text"/> --}}

                @if ($ledgerRecord->define->workflow_enabled)
                    <livewire:ledger.workflow-status-card :ledgerRecord="$ledgerRecord"
                        wire:key="status-card-{{ $ledgerRecord->id }}" />
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
                        <x-mary-group wire:model.live="displayLevel" :options="$displayLevelOptions"
                            class="[&_label]:btn-ghost [&_input:checked+label]:!btn-primary" option-value="id"
                            option-label="name" wire:key="details-display-level-group" />
                        <div class="flex items-center gap-1">
                            <x-mary-toggle wire:model.live="showChanges" label="{{ __('ledger.show_diff') }}" tight
                                class="text-xs" />
                            <x-mary-icon name="o-question-mark-circle" class="w-4 h-4 text-base-content/40 cursor-help"
                                x-tooltip="{{ __('ledger.workflow.guide.details_compare') }}" />
                        </div>
                    </x-slot:menu>

                    {{-- 新しい LedgerDiffViewer コンポーネント --}}
                    <livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" :canView="$canView" :allAttachments="$currentLedgerAttachments"
                        :highlight="$highlight" :displayLevel="$displayLevel" :showChanges="$showChanges" :targetDiffId="$targetDiffId"
                        wire:key="diff-viewer-{{ $ledgerRecord->id }}" lazy />

                    {{-- フッター集約情報（編集者情報・ナッジ） --}}
                    <div class="mt-6 pt-4 border-t border-base-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 text-sm">
                            <div class="space-y-2">
                                {{-- 本バージョンの情報 --}}
                                <div class="flex items-center gap-2">
                                    <span
                                        class="badge badge-outline badge-xs">{{ __('ledger.diff.current_version') }}</span>
                                    <span class="font-semibold text-success">Ver.{{ $ledgerRecord->version }}</span>
                                    <span class="text-base-content/50">|</span>
                                    <x-ledger.user-card-popover :user="$ledgerRecord->modifier" />
                                    <span
                                        class="text-xs text-base-content/50">({{ $ledgerRecord->updated_at->format('Y-m-d H:i') }})</span>
                                </div>

                                {{-- 比較対象（過去バージョン）の情報 --}}
                                @if ($showChanges && $comparisonTargetDiffModel)
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="badge badge-outline badge-xs">{{ __('ledger.diff.comparison_target') }}</span>
                                        <span
                                            class="font-semibold text-error">Ver.{{ $comparisonTargetDiffModel->version }}</span>
                                        <span class="text-base-content/50">|</span>
                                        @if ($comparisonTargetDiffModel->modifier)
                                            <x-ledger.user-card-popover :user="$comparisonTargetDiffModel->modifier" />
                                        @else
                                            <span class="text-base-content/50">?</span>
                                        @endif
                                        <span
                                            class="text-xs text-base-content/50">({{ $comparisonTargetDiffModel->created_at->format('Y-m-d H:i') }})</span>
                                    </div>
                                @endif
                            </div>

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

            </x-mary-tab>


            @php
                $historyTabTitle = $ledgerRecord->define->workflow_enabled
                    ? __('ledger.tab.workflow_history')
                    : __('ledger.history_title');
            @endphp
            {{-- ワークフロー履歴タブ --}}
            <x-mary-tab name="history" class="shadow-md" label="{{ $historyTabTitle }}" icon="o-list-bullet">
                <livewire:ledger.ledger-history-manager :ledgerId="$ledgerRecord->id" :displayLevel="$displayLevel" :highlight="$highlight"
                    :targetDiffId="$targetDiffId" wire:key="history-manager-{{ $ledgerRecord->id }}" />
            </x-mary-tab>


            {{-- ★★★ 総合活動履歴タブ ★★★ --}}
            <x-mary-tab name="activity" label="{{ __('ledger.tab.activity_history') }}" icon="o-clock"
                class="shadow-md">
                {{-- テスト実行時はレンダリングしない --}}
                @if (app()->environment() !== 'testing')
                    <livewire:common.activity-history-display :resourceId="$ledgerRecord->id" resourceType="Ledger"
                        :includeRelatedResources="true" :hiddenColumns="['subject']" wire:key="activity-history-{{ $ledgerRecord->id }}" />
                @else
                    <div id="activity-history-placeholder-for-testing"></div>
                @endif
            </x-mary-tab>

            {{-- ★★★ アクセスと権限タブ ★★★ --}}
            <x-mary-tab name="permissions" label="{{ __('ledger.tab.access_and_permissions') }}" icon="o-shield-check"
                class="shadow-md">
                {{-- テスト実行時はレンダリングしない --}}
                @if (app()->environment() !== 'testing')
                    <livewire:common.permission-display :resourceId="$ledgerRecord->id" resourceType="Ledger"
                        wire:key="permission-display-{{ $ledgerRecord->id }}" />
                @else
                    <div id="permission-display-placeholder-for-testing"></div>
                @endif
            </x-mary-tab>

        </x-mary-tabs>

        {{-- フッターパネル (アクションボタン集約) --}}
        <div class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3 z-20">
            <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                {{-- 透明度調整 --}}
                <div class="card-body p-4">
                    <livewire:ledger.workflow-action-buttons :ledgerRecord="$ledgerRecord"
                        wire:key="action-buttons-{{ $ledgerRecord->id }}" />
                    @livewire('workflow.workflow-comment-modal', ['ledgerId' => $ledgerRecord->id], key('workflow-comment-modal-show'))


                    {{-- 戻し理由入力モーダル --}}
                    {{--
        <x-mary-modal wire:model="returnToDraftModal"
          title="{{ __('ledger.workflow.return_to_draft_reason') }}">
        <x-mary-textarea label="{{ __('ledger.workflow.comments') }}" wire:model="returnComment"
                 placeholder="{{ __('ledger.workflow.return_reason_placeholder') }}"
                 hint="{{ __('ledger.workflow.optional_comment') }}" rows="3"/>
        <x-slot:actions>
        <x-mary-button label="{{ __('actions.cancel') }}" @click="$wire.returnToDraftModal = false"/>
        <x-mary-button label="{{ __('ledger.workflow.return_to_draft') }}" class="btn-warning"
                   wire:click="returnTaskToDraft" spinner/>
        </x-slot:actions>
        </x-mary-modal>
        --}}


                </div>

            </div>

        </div>
    </div>
    {{-- 添付ファイルのファイルインスペクタを常駐配置 --}}
    <livewire:attached-file.file-inspector :isInLedgerDetailPage="true" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6" x-data="{
    focusedIndex: 0,
    historyCount: {{ count($history) }},
    selectionAnnouncement: '',
    focusRow(index) {
        this.focusedIndex = index;
        const row = this.$refs['history-row-' + index];
        if (row) row.focus();
    },
    announceSelection(isSelected, version) {
        if (isSelected) {
            this.selectionAnnouncement = `{{ __('ledger.diff.selection_added', ['version' => '__VERSION__']) }}`.replace('__VERSION__', version);
        } else {
            this.selectionAnnouncement = `{{ __('ledger.diff.selection_removed', ['version' => '__VERSION__']) }}`.replace('__VERSION__', version);
        }
        setTimeout(() => { this.selectionAnnouncement = ''; }, 1000);
    },
    handleKeyDown(event, diffId, index, isCurrentlySelected, version) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (index < this.historyCount - 1) this.focusRow(index + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (index > 0) this.focusRow(index - 1);
        } else if (event.key === ' ' || event.key === 'Enter') {
            event.preventDefault();
            $wire.toggleSelection(diffId).then(() => {
                this.announceSelection(!isCurrentlySelected, version);
            });
        }
    }
}">
    {{-- 左側: 承認履歴テーブル (4/12 or 5/12) --}}
    <!-- スクリーンリーダー用の選択状態通知 -->
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="selectionAnnouncement"></div>
    <div
        class="lg:col-span-4 xl:col-span-3 h-[calc(100vh-250px)] min-h-[400px] overflow-y-auto border border-base-300 rounded-xl bg-base-100 shadow-sm custom-scrollbar sticky top-4">
        <div
            class="p-4 border-b border-base-200 bg-base-200/30 sticky top-0 z-10 backdrop-blur-md flex flex-wrap items-center justify-between gap-4">
            <h3 class="font-bold flex items-center gap-2 whitespace-nowrap">
                <x-mary-icon name="o-clock" class="w-5 h-5" />
                {{ __('ledger.history_list') }}
            </h3>

            @php
                $displayLevelOptions = [
                    ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                    ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                    ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                ];
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <x-mary-group wire:model.live="historyDisplayLevel" :options="$displayLevelOptions"
                    class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary" option-value="id"
                    option-label="name" wire:key="history-display-level-group" />
            </div>
        </div>

        <div class="divide-y divide-base-200" role="list" aria-label="{{ __('ledger.history_list') }}">
            <div wire:loading wire:target="toggleSelection,historyDisplayLevel" class="w-full">
                <x-element.skeleton-input-form rows="3" />
            </div>

            <div wire:loading.remove wire:target="toggleSelection,historyDisplayLevel">
                @forelse($history as $index => $diff)
                    @php
                        $isBase = $baseDiffId === $diff->id;
                        $isTarget = $targetDiffId === $diff->id;
                        $isSelected = $isBase || $isTarget;
                    @endphp
                    <div class="p-4 hover:bg-base-200 cursor-pointer transition-all duration-200 relative {{ $isSelected ? 'bg-primary/5' : '' }} focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        role="listitem" tabindex="0" aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                        aria-label="{{ __('ledger.diff.version_label', ['version' => $diff->version, 'date' => $diff->created_at->format('Y-m-d H:i'), 'user' => $diff->modifier?->name ?? '不明']) }}{{ $isSelected ? '、選択済み' : '' }}"
                        x-ref="history-row-{{ $index }}"
                        @click="$wire.toggleSelection({{ $diff->id }}).then(() => announceSelection({{ $isSelected ? 'false' : 'true' }}, {{ $diff->version }}))"
                        @keydown="handleKeyDown($event, {{ $diff->id }}, {{ $index }}, {{ $isSelected ? 'true' : 'false' }}, {{ $diff->version }})"
                        wire:loading.class="opacity-50 pointer-events-none" wire:key="history-row-{{ $diff->id }}">

                        @if ($isBase)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
                        @elseif($isTarget)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-error"></div>
                        @endif

                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold flex items-center gap-1">
                                    Ver.{{ $diff->version }}
                                    @if ($diff->version === $ledgerRecord->version)
                                        <span
                                            class="badge badge-primary badge-xs">{{ __('ledger.diff.current_version') }}</span>
                                    @endif
                                    @if ($isBase)
                                        <span class="badge badge-primary badge-outline badge-xs gap-1 pl-1.5">
                                            <x-mary-icon name="o-check-circle" class="w-3 h-3" />
                                            {{ __('ledger.diff.base') }}
                                        </span>
                                    @endif
                                    @if ($isTarget)
                                        <span class="badge badge-error badge-outline badge-xs gap-1 pl-1.5">
                                            <x-mary-icon name="o-arrows-right-left" class="w-3 h-3" />
                                            {{ __('ledger.diff.compare_to') }}
                                        </span>
                                    @endif
                                </span>
                                <span
                                    class="text-[10px] text-base-content/50">{{ $diff->created_at->format('Y-m-d H:i') }}</span>
                            </div>

                            <div class="flex flex-col md:flex-row gap-2 mt-1">
                                {{-- 左側: ステータス・編集者 --}}
                                <div class="flex flex-col gap-1.5 min-w-[120px] shrink-0">
                                    {{-- ワークフロー状態 --}}
                                    @if ($diff->status)
                                        <div>
                                            <span class="badge badge-xs {{ $diff->status->colorClass() }} gap-1">
                                                {{ $diff->status->label() }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- 編集者 (Editor) --}}
                                    @if ($diff->modifier)
                                        <div class="flex items-center gap-1.5 text-xs text-base-content/80" title="{{ __('ledger.workflow.label.editor') }}">
                                            <x-mary-icon name="o-pencil" class="w-3 h-3 text-base-content/50" />
                                            <span class="sr-only">{{ __('ledger.workflow.label.editor') }}:</span>
                                            <x-ledger.user-card-popover :user="$diff->modifier" />
                                        </div>
                                    @endif

                                    {{-- 承認者 (Approver) --}}
                                    @if ($diff->status === \App\Enums\WorkflowStatus::APPROVED && $diff->approver)
                                        <div class="flex items-center gap-1.5 text-xs text-base-content/80" title="{{ __('ledger.workflow.approved_by') }}">
                                            <x-mary-icon name="o-check-circle" class="w-3 h-3 text-success" />
                                            <span class="sr-only">{{ __('ledger.workflow.approved_by') }}:</span>
                                            <x-ledger.user-card-popover :user="$diff->approver" />
                                        </div>
                                    @endif
                                </div>

                                {{-- 右側: コメント & アクション --}}
                                <div class="flex-1 flex flex-col justify-between gap-2 min-w-0">
                                    @if ($diff->comments)
                                        <div
                                            class="text-[10px] text-base-content/60 bg-base-200/50 p-1.5 rounded flex flex-col gap-1 h-full">
                                            @php
                                                $commentParts = explode("\n--- system-info ---\n", $diff->comments);
                                                $userComment = $commentParts[0] ?? '';
                                                $systemInfo = $commentParts[1] ?? null;
                                            @endphp

                                            <div class="flex items-start gap-1">
                                                <x-mary-icon name="o-chat-bubble-left" class="w-3 h-3 mt-0.5 shrink-0" />
                                                <span class="line-clamp-2 break-all"
                                                    title="{{ $userComment }}">{{ $userComment }}</span>
                                            </div>

                                            @if ($systemInfo)
                                                <div class="mt-auto pt-1 border-t border-base-content/5 w-full">
                                                    <span
                                                        class="badge badge-ghost badge-xs text-xs opacity-70 font-normal w-full justify-start h-auto py-0.5">
                                                        <x-mary-icon name="o-information-circle"
                                                            class="w-3 h-3 mr-1 opacity-50" />
                                                        {{ $systemInfo }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        {{-- コメントがない場合のスペース確保（レイアウト崩れ防止）または非表示 --}}
                                    @endif

                                    {{-- アクションボタンエリア --}}
                                    <div class="flex justify-end gap-2 mt-auto">
                                        @if ($isTarget && $canRollback && $diff->version !== $ledgerRecord->version)
                                            @if ($isContentIdentical ?? false)
                                                <span class="badge badge-ghost badge-xs gap-1 opacity-50 cursor-help"
                                                    title="{{ __('ledger.diff.identical_content_hint') }}">
                                                    <x-mary-icon name="o-check" class="w-3 h-3" />
                                                    {{ __('ledger.diff.identical_content') }}
                                                </span>
                                            @else
                                                <x-mary-button label="{{ __('ledger.rollback.button_label') }}"
                                                    class="btn-xs btn-outline btn-error" icon="o-arrow-uturn-left"
                                                    @click.stop="$dispatch('ledger.rollback.open-modal', { ledgerId: {{ $ledgerId }}, targetDiffId: {{ $diff->id }}, expectedVersion: {{ $ledgerRecord->version }} })"
                                                    wire:key="rollback-btn-{{ $diff->id }}" />
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-base-content/30 italic">
                        {{ __('ledger.history_empty') }}
                    </div>
                @endforelse

                {{-- 無限スクロールトリガー --}}
                @if ($hasMore)
                    <div x-intersect.once="$wire.loadMore()" class="p-8 flex justify-center">
                        <span wire:loading class="loading loading-spinner loading-md text-primary"></span>
                    </div>
                @else
                    <div class="p-8 text-center text-xs text-base-content/30 italic">
                        {{ __('ledger.history_end') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 右側: 差分ビューア (8/12 or 7/12) --}}
    <div class="lg:col-span-8 xl:col-span-9 space-y-6 relative min-h-[400px]">
        <x-element.loading-overlay tier="2" message="{{ __('ledger.loading') }}" />

        {{-- 計算中のスケルトン表示 --}}
        <div wire:loading wire:target="toggleSelection,historyDisplayLevel" class="w-full space-y-6 animate-pulse">
            <div class="card bg-base-100 border border-base-300 shadow-sm">
                <div class="card-body p-6 space-y-8">
                    {{-- ヘッダー部分のスケルトン --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-base-200">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-8 bg-base-300 rounded-lg"></div>
                            <div class="w-32 h-6 bg-base-200 rounded"></div>
                        </div>
                        <div class="flex gap-2">
                            <div class="w-24 h-8 bg-base-200 rounded-full"></div>
                            <div class="w-24 h-8 bg-base-200 rounded-full"></div>
                        </div>
                    </div>

                    {{-- 比較情報のスケルトン --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-base-200/30 rounded-xl">
                        <div class="space-y-2">
                            <div class="w-20 h-4 bg-base-300 rounded"></div>
                            <div class="w-full h-12 bg-base-100 rounded-lg border border-base-200"></div>
                        </div>
                        <div class="space-y-2">
                            <div class="w-20 h-4 bg-base-300 rounded"></div>
                            <div class="w-full h-12 bg-base-100 rounded-lg border border-base-200"></div>
                        </div>
                    </div>

                    {{-- コンテンツ部分のスケルトン（複数項目） --}}
                    <div class="space-y-6">
                        @foreach (range(1, 4) as $i)
                            <div class="space-y-3">
                                <div class="w-32 h-5 bg-base-300 rounded"></div>
                                <div class="w-full h-20 bg-base-200/50 rounded-xl"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- 通常コンテンツ（読み込み中は非表示） --}}
        <div wire:loading.remove wire:target="toggleSelection,historyDisplayLevel">
            @if ($baseDiff)
                <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                    <div class="card-body p-0">
                        <livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" :comparisonTargetDiff="$targetDiff" :displayLevel="$historyDisplayLevel"
                            :showChanges="isset($targetDiffId)" :canView="true" :highlight="$highlight" :baseMeta="$baseMeta" :targetMeta="$targetMeta"
                            :baseDiffId="$baseDiffId" :targetDiffId="$targetDiffId" :useFallback="false" :showInduction="false"
                            :allAttachments="$allAttachments"
                            wire:key="history-viewer-{{ $baseDiffId }}-{{ $targetDiffId }}" />
                    </div>
                </div>
            @else
                <div
                    class="flex flex-col items-center justify-center h-[400px] border-2 border-dashed border-base-300 rounded-2xl bg-base-200/30 text-base-content/30">
                    <x-mary-icon name="o-arrow-left" class="w-12 h-12 mb-4 animate-pulse" />
                    <p class="font-medium">{{ __('ledger.diff.select_version_hint') }}</p>
                </div>
            @endif
        </div>
    </div>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }
    </style>
</div>

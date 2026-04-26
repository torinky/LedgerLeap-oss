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
    <x-mary-card
        shadow
        title="{{ __('ledger.history_list') }}"
        icon="o-clock"
        class="lg:col-span-4 xl:col-span-3 h-[calc(100vh-250px)] min-h-[400px] overflow-y-auto bg-base-100 border border-base-300 shadow-sm custom-scrollbar sticky top-4"
        body-class="p-0"
    >
        <x-slot:menu>
            <span class="badge badge-neutral badge-sm">{{ count($history) }}</span>
        </x-slot:menu>

        <div class="relative">
            <x-element.loading-overlay tier="2" target="toggleSelection,historyDisplayLevel" :delay="false" />

            <div class="divide-y divide-base-200" role="list" aria-label="{{ __('ledger.history_list') }}" wire:loading.class="opacity-50 pointer-events-none" wire:target="toggleSelection,historyDisplayLevel">
                @forelse($history as $index => $diff)
                    @php
                        $isBase = $baseDiffId === $diff->id;
                        $isTarget = $targetDiffId === $diff->id;
                        $isSelected = $isBase || $isTarget;
                    @endphp
                    <div class="p-4 cursor-pointer transition-all duration-200 relative rounded-none border-l-4 {{ $isSelected ? 'bg-primary/5 border-l-primary shadow-sm' : 'hover:bg-base-200 border-l-transparent' }} focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        role="listitem" tabindex="0" aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                        aria-label="{{ __('ledger.diff.version_label', ['version' => $diff->version, 'date' => $diff->created_at->format('Y-m-d H:i'), 'user' => $diff->modifier?->name ?? __('ledger.unknown_user')]) }}{{ $isSelected ? '、選択済み' : '' }}"
                        x-ref="history-row-{{ $index }}"
                        @click="$wire.toggleSelection({{ $diff->id }}).then(() => announceSelection({{ $isSelected ? 'false' : 'true' }}, {{ $diff->version }}))"
                        @keydown="handleKeyDown($event, {{ $diff->id }}, {{ $index }}, {{ $isSelected ? 'true' : 'false' }}, {{ $diff->version }})"
                        wire:loading.class="opacity-50 pointer-events-none" wire:target="toggleSelection,historyDisplayLevel" wire:key="history-row-{{ $diff->id }}">

                        @if ($isBase)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
                        @elseif($isTarget)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-error"></div>
                        @endif

                        <div class="flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-base md:text-lg font-bold flex items-center gap-1.5 flex-wrap leading-tight">
                                    {{ __('ledger.version') }}{{ $diff->version }}
                                    @if ($diff->version === $ledgerRecord->version)
                                        <span class="badge badge-primary badge-outline badge-xs tooltip tooltip-bottom"
                                            data-tip="{{ __('ledger.diff.current_version') }}"
                                            title="{{ __('ledger.diff.current_version') }}"
                                            aria-label="{{ __('ledger.diff.current_version') }}">
                                            <x-mary-icon name="o-sparkles" class="w-3 h-3" />
                                            <span class="sr-only">{{ __('ledger.diff.current_version') }}</span>
                                        </span>
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
                                <span class="badge badge-ghost badge-sm text-sm text-base-content/60">
                                    {{ $diff->created_at->format('Y-m-d H:i') }}
                                </span>
                            </div>

                            <div class="flex flex-col md:flex-row gap-3">
                                {{-- 左側: ステータス・編集者 --}}
                                <div class="flex flex-col gap-1.5 min-w-[140px] shrink-0">
                                    {{-- ワークフロー状態 --}}
                                    @if ($diff->status)
                                        <div>
                                            <span class="badge badge-sm {{ $diff->status->colorClass() }} gap-1">
                                                {{ $diff->status->label() }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- 編集者 (Editor) --}}
                                    @if ($diff->modifier)
                                        <div class="flex items-center gap-1.5 text-sm md:text-base text-base-content/80" title="{{ __('ledger.workflow.label.editor') }}">
                                            <x-mary-icon name="o-pencil" class="w-4 h-4 text-base-content/50" />
                                            <span class="sr-only">{{ __('ledger.workflow.label.editor') }}:</span>
                                            <x-ledger.user-card-popover :user="$diff->modifier" />
                                        </div>
                                    @endif

                                    {{-- 承認者 (Approver) --}}
                                    @if ($diff->status === \App\Enums\WorkflowStatus::APPROVED && $diff->approver)
                                        <div class="flex items-center gap-1.5 text-sm md:text-base text-base-content/80" title="{{ __('ledger.workflow.approved_by') }}">
                                            <x-mary-icon name="o-check-circle" class="w-4 h-4 text-success" />
                                            <span class="sr-only">{{ __('ledger.workflow.approved_by') }}:</span>
                                            <x-ledger.user-card-popover :user="$diff->approver" />
                                        </div>
                                    @endif
                                </div>

                                {{-- 右側: コメント & アクション --}}
                                <div class="flex-1 flex flex-col justify-between gap-2 min-w-0">
                                    @if ($diff->comments)
                                        <div
                                            class="text-base md:text-lg text-base-content/70 bg-base-200/50 p-3 rounded-xl border border-base-200 flex flex-col gap-2 h-full">
                                            @php
                                                $commentParts = explode("\n--- system-info ---\n", $diff->comments);
                                                $userComment = $commentParts[0] ?? '';
                                                $systemInfo = $commentParts[1] ?? null;
                                            @endphp

                                            <div class="flex items-start gap-1.5">
                                                <x-mary-icon name="o-chat-bubble-left" class="w-5 h-5 mt-0.5 shrink-0" />
                                                <span class="line-clamp-2 break-all"
                                                    title="{{ $userComment }}">{{ $userComment }}</span>
                                            </div>

                                            @if ($systemInfo)
                                                <div class="mt-auto pt-2 border-t border-base-content/5 w-full">
                                                    <span
                                                        class="badge badge-ghost badge-sm text-sm opacity-70 font-normal w-full justify-start h-auto py-0.5">
                                                        <x-mary-icon name="o-information-circle"
                                                            class="w-4 h-4 mr-1 opacity-50" />
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
                                                <span class="badge badge-ghost badge-sm gap-1 opacity-50 cursor-help"
                                                    title="{{ __('ledger.diff.identical_content_hint') }}">
                                                    <x-mary-icon name="o-check" class="w-4 h-4" />
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
                    <div class="p-8 text-center text-sm md:text-base text-base-content/30 italic">
                        {{ __('ledger.history_empty') }}
                    </div>
                @endforelse

                {{-- 無限スクロールトリガー --}}
                @if ($hasMore)
                    <div x-intersect.once="$wire.loadMore()" class="p-8 flex justify-center">
                        <span wire:loading wire:target="loadMore" class="loading loading-spinner loading-md text-primary"></span>
                    </div>
                @else
                    <div class="p-8 text-center text-sm md:text-base text-base-content/30 italic">
                        {{ __('ledger.history_end') }}
                    </div>
                @endif
            </div>
        </div>
    </x-mary-card>

    {{-- 右側: 差分ビューア (8/12 or 7/12) --}}
    <x-mary-card
        shadow
        title="{{ __('ledger.workflow.history_detail') }}"
        icon="o-document-text"
        class="lg:col-span-8 xl:col-span-9 relative min-h-[400px] bg-base-100 border border-base-300 shadow-sm"
        body-class="py-4 px-0 space-y-4"
    >
        <x-slot:menu>
            @php
                $displayLevelOptions = [
                    ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                    ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                    ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
                ];
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 rounded-full border border-base-300/70 bg-base-100/70 px-3 py-1.5">
                    <x-mary-group wire:model.live="historyDisplayLevel" :options="$displayLevelOptions"
                        class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary" option-value="id"
                        option-label="name" wire:key="history-display-level-group" />
                </div>

                <div
                    class="flex items-center gap-2 rounded-full border border-base-300/70 bg-base-100/70 px-3 py-1.5"
                    x-data="{
                        active: false,
                        check() {
                            const id = $store.ledgerState.currentLedgerId;
                            this.active = !($store.ledgerState.states[id]?.['__global__'] ?? true);
                        }
                    }"
                    x-init="check(); const interval = setInterval(() => check(), 500); $el.addEventListener('destroy', () => clearInterval(interval));">
                    <x-mary-toggle @click="$store.ledgerState.expandAll(!active)" x-model="active" tight
                        label="{{ __('ledger.column.expand_all') }}"
                        class="toggle-sm toggle-primary text-sm md:text-base font-black text-base-content/40 uppercase tracking-widest" />
                </div>
            </div>
        </x-slot:menu>

        <div class="relative">
            <x-element.loading-overlay tier="2" target="toggleSelection,historyDisplayLevel" :delay="false">
                <x-element.skeleton-input-form rows="4" />
            </x-element.loading-overlay>

            {{-- 通常コンテンツ（読み込み中も DOM を維持） --}}
            <div wire:loading.class="opacity-50 pointer-events-none" wire:target="toggleSelection,historyDisplayLevel" class="space-y-4">
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
    </x-mary-card>
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: hsl(var(--b1) / 1);
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: hsl(var(--b3) / 1);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: hsl(var(--b2) / 1);
        }
    </style>
</div>

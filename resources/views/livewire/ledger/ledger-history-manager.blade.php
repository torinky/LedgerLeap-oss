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
        class="lg:col-span-4 xl:col-span-3 h-[calc(100vh-250px)] overflow-y-auto border border-base-300 rounded-xl bg-base-100 shadow-sm custom-scrollbar sticky top-4">
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
            @foreach ($history as $index => $diff)
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
                    wire:key="history-row-{{ $diff->id }}">

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
                            </span>
                            <span
                                class="text-[10px] text-base-content/50">{{ $diff->created_at->format('Y-m-d H:i') }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-xs">
                            <div class="avatar placeholder">
                                <div class="bg-neutral text-neutral-content rounded-full w-5 h-5">
                                    <span
                                        class="text-[8px]">{{ mb_substr($diff->modifier?->name ?? '?', 0, 1) }}</span>
                                </div>
                            </div>
                            <span class="truncate">{{ $diff->modifier?->name }}</span>
                        </div>

                        @if ($diff->comment)
                            <div
                                class="text-[10px] text-base-content/70 italic line-clamp-2 bg-base-200/50 p-1.5 rounded">
                                {{ $diff->comment }}
                            </div>
                        @endif

                        <div class="flex gap-1 mt-1">
                            @if ($isBase)
                                <span
                                    class="badge badge-primary badge-outline badge-xs">{{ __('ledger.diff.base') }}</span>
                            @endif
                            @if ($isTarget)
                                <span
                                    class="badge badge-error badge-outline badge-xs">{{ __('ledger.diff.compare_to') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- 無限スクロールトリガー --}}
            @if ($hasMore)
                <div x-intersect="$wire.loadMore()" class="p-8 flex justify-center">
                    <span wire:loading class="loading loading-spinner loading-md text-primary"></span>
                </div>
            @else
                <div class="p-8 text-center text-xs text-base-content/30 italic">
                    {{ __('ledger.history_end') }}
                </div>
            @endif
        </div>
    </div>

    {{-- 右側: 差分ビューア (8/12 or 7/12) --}}
    <div class="lg:col-span-8 xl:col-span-9 space-y-6">
        @if ($baseDiff)
            <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                <div class="card-body p-0">
                    <livewire:ledger.ledger-diff-viewer :ledgerRecord="$ledgerRecord" :comparisonTargetDiff="$targetDiff" :displayLevel="$historyDisplayLevel"
                        :showChanges="isset($targetDiffId)" :canView="true" :highlight="$highlight" :baseMeta="$baseMeta" :targetMeta="$targetMeta"
                        :baseDiffId="$baseDiffId" :targetDiffId="$targetDiffId" :useFallback="false"
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

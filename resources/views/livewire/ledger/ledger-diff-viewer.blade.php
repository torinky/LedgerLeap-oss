<div class="space-y-6 relative min-h-[300px]" x-data x-init="console.log('[LedgerDiffViewer] Initializing with ledgerId:', {{ $ledgerRecord->id }});
if ($store.ledgerState) {
    console.log('[LedgerDiffViewer] Alpine store found, calling init()');
    $store.ledgerState.init({{ $ledgerRecord->id }});
} else {
    console.error('[LedgerDiffViewer] Alpine.store(ledgerState) is not available!');
}">
    {{-- Tier 2 loading is handled by parent (Show.php) to use skeleton --}}

    @if ($showChanges)
        <div class="space-y-3">
            <div class="flex items-center justify-between px-4 py-2 bg-base-200/50 rounded-lg border border-base-300">
                <div class="flex items-center gap-4 text-sm">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-outline badge-sm">{{ __('ledger.diff.comparing') }}</span>
                            <span class="font-bold text-success">Ver.{{ $currentVersion }}</span>
                            <x-mary-icon name="o-arrow-long-left" class="w-4 h-4" />
                            @if ($pastVersion)
                                <span class="font-bold text-error">Ver.{{ $pastVersion }}</span>
                            @else
                                <span class="font-bold text-success">{{ __('ledger.diff.not_exist') }}</span>
                            @endif
                        </div>

                        {{-- 比較対象（過去バージョン）のステータスを表示 --}}
                        @if ($targetMeta && isset($targetMeta['status']))
                            @php
                                $status = $targetMeta['status'];
                            @endphp
                            <span class="badge badge-sm {{ $status->colorClass() }} gap-1">
                                {{ $status->label() }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($showInduction)
                    <div class="flex items-center gap-2">
                        {{-- 履歴タブへの誘導リンク --}}
                        <x-mary-button icon="o-clock" :label="__('ledger.diff.nudge_view_history')" wire:click="$dispatch('switchToHistoryTab')"
                            class="btn-xs btn-ghost text-base-content/60 hover:text-primary" />
                    </div>
                @endif
            </div>

            {{-- 変更がない場合の通知（上部に配置） --}}
            @if (!$hasChangedColumns)
                <div class="alert alert-success shadow-sm py-3 px-4 flex items-center gap-3">
                    <x-mary-icon name="o-check-circle" class="w-5 h-5" />
                    <div class="flex flex-col">
                        <span class="text-sm font-bold">{{ __('ledger.diff.no_changes') }}</span>
                        <span class="text-[10px] opacity-70">{{ __('ledger.diff.identical_content') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($displayData as $group)
            @php
                // グループ単位でのプレースホルダー表示判定（ループの外で1回だけ計算）
                $showIdenticalPlaceholder = $showChanges &&
                    !$hasChangedColumns &&
                    !collect($group['columns'])->contains('is_omitted', true);
            @endphp

            <div class="collapse collapse-arrow bg-base-100 border border-base-200 shadow-sm" x-data="{
                isOpen: {{ $group['is_required_group'] ? 'true' : 'false' }},
                initialized: false,
                groupName: '{{ $group['group_name'] }}',
                isRequired: {{ $group['is_required_group'] ? 'true' : 'false' }},
                ledgerId: {{ $ledgerRecord->id }}
            }"
                x-init="console.log('[Group: {{ $group['group_name'] }}] Initializing, isRequired:', {{ $group['is_required_group'] ? 'true' : 'false' }});
                
                // 初期状態をストアから取得
                const loadFromStore = () => {
                    if ($store.ledgerState && $store.ledgerState.currentLedgerId) {
                        const stored = $store.ledgerState.isCollapsed(groupName, isRequired);
                        isOpen = !stored;
                        console.log('[Group: {{ $group['group_name'] }}] Loaded from store, isOpen:', isOpen);
                    } else {
                        console.error('[Group: {{ $group['group_name'] }}] Store not available for initial load');
                    }
                };
                
                loadFromStore();
                
                // 初期化完了フラグを立てる
                initialized = true;
                
                // Alpine Store と連携（初期化後のみ保存）
                $watch('isOpen', value => {
                    if (!initialized) {
                        console.log('[Group: {{ $group['group_name'] }}] Skipping save during initialization');
                        return;
                    }
                
                    console.log('[Group: {{ $group['group_name'] }}] isOpen changed to:', value);
                    if ($store.ledgerState && $store.ledgerState.currentLedgerId) {
                        $store.ledgerState.states[$store.ledgerState.currentLedgerId][groupName] = !value;
                        localStorage.setItem('ledger_collapsed_states', JSON.stringify($store.ledgerState.states));
                        console.log('[Group: {{ $group['group_name'] }}] Saved to localStorage, collapsed:', !value);
                    } else {
                        console.error('[Group: {{ $group['group_name'] }}] Cannot save state - store not available');
                    }
                });
                
                // localStorageの変更を監視
                window.addEventListener('storage', (e) => {
                    if (e.key === 'ledger_collapsed_states' && e.newValue) {
                        console.log('[Group: {{ $group['group_name'] }}] localStorage changed, reloading');
                        const states = JSON.parse(e.newValue);
                        if (states[ledgerId] && states[ledgerId][groupName] !== undefined) {
                            initialized = false;
                            isOpen = !states[ledgerId][groupName];
                            console.log('[Group: {{ $group['group_name'] }}] Updated from localStorage, isOpen:', isOpen);
                            $nextTick(() => { initialized = true; });
                        }
                    }
                });
                
                // 同一ページ内での変更検知（storageイベントは別タブでしか発火しない）
                const checkStorage = setInterval(() => {
                    if ($store.ledgerState && $store.ledgerState.currentLedgerId) {
                        // ストアから最新の状態を取得
                        const ledgerStates = $store.ledgerState.states[$store.ledgerState.currentLedgerId];
                        if (ledgerStates && ledgerStates[groupName] !== undefined) {
                            const storedCollapsed = ledgerStates[groupName];
                            const storedIsOpen = !storedCollapsed;
                
                            // 現在のUIの状態と異なる場合のみ更新
                            if (storedIsOpen !== isOpen && initialized) {
                                console.log('[Group: ' + groupName + '] Detected change in store, current:', isOpen, 'stored:', storedIsOpen);
                                initialized = false;
                                isOpen = storedIsOpen;
                                $nextTick(() => { initialized = true; });
                            }
                        }
                    }
                }, 200);
                
                // クリーンアップ
                $el.addEventListener('destroy', () => {
                    clearInterval(checkStorage);
                });"
                :class="{
                    'collapse-open': isOpen,
                    'collapse-close': !isOpen
                }"
                wire:key="group-{{ md5($group['group_name']) }}">

                <input type="checkbox" class="hidden" />

                <div class="collapse-title text-sm font-bold flex items-center justify-between cursor-pointer"
                    @click.stop="isOpen = !isOpen" role="button" :aria-expanded="isOpen">
                    <div class="flex items-center gap-2">
                        @if ($group['is_required_group'])
                            <span class="badge badge-ghost badge-sm text-error">必須</span>
                        @endif
                        {{ $group['group_name'] }}
                    </div>
                </div>

                <div class="collapse-content overflow-x-auto p-0">
                    <table class="table table-sm w-full border-t border-base-200">
                        <thead class="bg-base-200/30">
                            <tr>
                                <th class="{{ $showChanges ? 'w-1/4' : 'w-1/3' }} min-w-[150px]">
                                    {{ __('ledger.form.column_name') }}</th>
                                <th class="{{ $showChanges ? 'w-3/8' : 'w-2/3' }} min-w-[250px]">
                                    {{ 'Ver.' . $currentVersion }}</th>
                                @if ($showChanges)
                                    <th class="w-3/8 min-w-[250px]">
                                        {{ $pastVersion ? 'Ver.' . $pastVersion : __('ledger.diff.previous_value') }}
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-100">
                            @foreach ($group['columns'] as $column)
                                @if ($column['is_omitted'])
                                    <tr class="bg-base-200/5">
                                        <td colspan="{{ $showChanges ? 3 : 2 }}" class="py-2 px-4">
                                            <div
                                                class="flex items-center justify-center gap-3 py-1.5 bg-base-200/20 rounded-lg text-base-content/40 text-[10px] uppercase tracking-widest font-bold border border-dashed border-base-300/50">
                                                <x-mary-icon name="o-ellipsis-horizontal" class="w-4 h-4 opacity-50" />
                                                <span>{{ __('ledger.diff.omitted_items', ['count' => $column['omitted_count']]) }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr class="hover:bg-base-200/20 transition-colors {{ $showChanges && $column['status'] !== 'unchanged' ? 'bg-warning/5' : '' }}"
                                        wire:key="col-{{ $column['id'] }}-{{ $currentVersion }}-{{ $pastVersion }}">
                                        <td class="align-top py-3">
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    @if ($column['is_required'])
                                                        <x-mary-icon name="o-check-badge" class="w-3 h-3 text-error" />
                                                    @endif
                                                    <span
                                                        class="text-sm font-semibold text-base-content/80">{{ $column['name'] }}</span>
                                                </div>
                                                @if ($column['hint'])
                                                    <span
                                                        class="text-[10px] text-base-content/50 leading-tight">{{ $column['hint'] }}</span>
                                                @endif

                                                @if ($showChanges && $column['status'] !== 'unchanged')
                                                    <div>
                                                        @if ($column['status'] === 'added')
                                                            <span
                                                                class="badge badge-success badge-xs">{{ __('ledger.diff.added') }}</span>
                                                        @elseif($column['status'] === 'deleted')
                                                            <span
                                                                class="badge badge-error badge-xs">{{ __('ledger.diff.deleted') }}</span>
                                                        @else
                                                            <span
                                                                class="badge badge-warning badge-xs">{{ __('ledger.diff.modified') }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- 新データ (現在) --}}
                                        <td class="align-top py-3 text-sm prose-sm max-w-none">
                                            <div
                                                class="break-words {{ $showChanges && $column['status'] !== 'unchanged' ? 'font-medium' : '' }}">
                                                @if (in_array($column['type'] ?? '', ['file', 'files']))
                                                    {!! $column['current_value_html'] !!}
                                                @else
                                                    <x-expandable-content :content="$column['current_value_html']" max-height="6rem" />
                                                @endif
                                            </div>
                                        </td>

                                        {{-- 旧データ (比較対象) --}}
                                        @if ($showChanges)

                                            @if ($showIdenticalPlaceholder)
                                                @if ($loop->first)
                                                    <td class="align-middle py-3 px-0 bg-base-200/20 text-center relative p-0"
                                                        rowspan="{{ count($group['columns']) }}">
                                                        <div
                                                            class="absolute inset-0 flex flex-col items-center justify-center text-base-content/40 font-bold select-none pointer-events-none p-4">
                                                            <x-mary-icon name="o-document-check"
                                                                class="w-16 h-16 opacity-20 mb-2" />
                                                            <div class="text-lg opacity-70">
                                                                {{ __('ledger.diff.identical_content') }}</div>
                                                            <div class="text-xs font-normal opacity-50 mt-1">
                                                                Ver.{{ $pastVersion }}</div>
                                                        </div>
                                                    </td>
                                                @endif
                                            @elseif ($column['status'] === 'unchanged')
                                                {{-- カラム単位で変更がない場合のプレースホルダー --}}
                                                <td class="align-middle py-3 px-4 bg-base-200/10 text-center">
                                                    <div class="flex items-center justify-center gap-2 text-base-content/40 text-xs">
                                                        <x-mary-icon name="o-check-circle" class="w-4 h-4" />
                                                        <span>{{ __('ledger.diff.same_as_current') }}</span>
                                                    </div>
                                                </td>
                                            @else
                                                <td class="align-top py-3 text-sm prose-sm max-w-none">
                                                    <div class="break-words">
                                                        @if (in_array($column['type'] ?? '', ['file', 'files']))
                                                            {!! $column['old_value_html'] !!}
                                                        @else
                                                            <x-expandable-content :content="$column['old_value_html']" max-height="6rem" />
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                        @endif
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

</div>

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
                            <x-mary-icon name="o-arrow-long-left" class="" />
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
                    <x-mary-icon name="o-check-circle" class="" />
                    <div class="flex flex-col">
                        <span class="text-sm font-bold">{{ __('ledger.diff.no_changes') }}</span>
                        <span class="text-xs opacity-70">{{ __('ledger.diff.identical_content') }}</span>
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

            <div class="collapse collapse-arrow bg-base-100 border border-base-300 shadow-sm" x-data="{
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
                        // ストアの判定ロジック（個別設定＋グローバル設定を考慮）を使用
                        const storedCollapsed = $store.ledgerState.isCollapsed(groupName, isRequired);
                        const storedIsOpen = !storedCollapsed;
                
                        // 現在のUIの状態と異なる場合のみ更新
                        if (storedIsOpen !== isOpen && initialized) {
                            console.log('[Group: ' + groupName + '] Detected change in store/isCollapsed, current:', isOpen, 'stored:', storedIsOpen);
                            initialized = false;
                            isOpen = storedIsOpen;
                            $nextTick(() => { initialized = true; });
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

                <div class="collapse-title font-bold flex items-center justify-between cursor-pointer bg-base-200 border-l-4 border-primary px-4 py-3 min-h-[3rem]"
                    @click.stop="isOpen = !isOpen" role="button" :aria-expanded="isOpen">
                    <div class="flex items-center gap-3">
                        <div @if($group['is_required_group']) class="indicator tooltip tooltip-right" data-tip="{{ __('ledger.diff.contains_required_items') }}" @endif>
                            @if ($group['is_required_group'])
                                <span class="indicator-item badge badge-error badge-xs p-0 w-2 h-2 border-none"></span>
                            @endif
                            <x-mary-icon name="o-folder-open" class="text-primary/70" />
                        </div>
                        <span class="tracking-tight">{{ $group['group_name'] }}</span>
                    </div>
                    <div class="flex items-center gap-2 pr-12">
                        @php
                            $changedCount = collect($group['columns'])->where('status', '!=', 'unchanged')->where('is_omitted', false)->count();
                        @endphp
                        @if ($changedCount > 0)
                            <span class="badge badge-warning badge-sm font-bold gap-1">
                                <x-mary-icon name="o-pencil" class="" />
                                {{ __('ledger.diff.items_changed', ['count' => $changedCount]) }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="collapse-content overflow-x-auto p-0 border-t border-base-300">
                    <table class="table table-sm w-full table-fixed">
                        <thead class="bg-base-300/40 text-base-content/80">
                            <tr>
                                <th class="{{ $showChanges ? 'w-[25%]' : 'w-[30%]' }} min-w-[140px] px-4 py-3 border-r border-base-300">
                                    {{ __('ledger.form.column_name') }}</th>
                                <th class="{{ $showChanges ? 'w-[37.5%]' : 'w-[70%]' }} min-w-[200px] px-4 py-3 {{ $showChanges ? 'border-r border-base-300' : '' }}">
                                    {{ 'Ver.' . $currentVersion }}</th>
                                @if ($showChanges)
                                    <th class="w-[37.5%] min-w-[200px] px-4 py-3">
                                        {{ 'Ver.' . $pastVersion ? 'Ver.' . $pastVersion : __('ledger.diff.previous_value') }}
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
                                                <x-mary-icon name="o-ellipsis-horizontal" class="icon-sm opacity-50" />
                                                <span>{{ __('ledger.diff.omitted_items', ['count' => $column['omitted_count']]) }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr class="hover:bg-secondary/20 transition-colors {{ $showChanges && $column['status'] !== 'unchanged' ? 'bg-warning/5' : '' }}"
                                        wire:key="col-{{ $column['id'] }}-{{ $currentVersion }}-{{ $pastVersion }}">
                                        <td class="align-top py-4 px-4 bg-base-200/50 border-r border-base-300">
                                            <div class="flex flex-col gap-1.5">
                                                <div class="flex items-start gap-2">
                                                    @if ($column['is_required'])
                                                        <x-mary-icon name="o-check-badge" class=" text-error shrink-0" />
                                                    @endif
                                                    <span
                                                        class="text-sm font-bold text-base-content/90 leading-snug break-words">{{ $column['name'] }}</span>
                                                </div>
                                                @if ($column['hint'])
                                                    <span
                                                        class="text-xs text-base-content/50 leading-normal break-words">{{ $column['hint'] }}</span>
                                                @endif
 
                                                @if ($showChanges && $column['status'] !== 'unchanged')
                                                    <div class="mt-1">
                                                        @if ($column['status'] === 'added')
                                                            <span
                                                                class="badge badge-success badge-soft badge-xs font-bold">{{ __('ledger.diff.added') }}</span>
                                                        @elseif($column['status'] === 'deleted')
                                                            <span
                                                                class="badge badge-error badge-soft badge-xs font-bold">{{ __('ledger.diff.deleted') }}</span>
                                                        @else
                                                            <span
                                                                class="badge badge-warning badge-soft badge-xs font-bold">{{ __('ledger.diff.modified') }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- 新データ (現在) --}}
                                        <td class="align-top py-4 px-4 text-sm leading-relaxed {{ $showChanges ? 'border-r border-base-300' : '' }}">
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
                                                <td class="align-middle py-4 px-4 bg-base-200/10 text-center">
                                                    <div class="flex items-center justify-center gap-2 text-base-content/40 text-xs font-medium transition-opacity hover:opacity-100 italic">
                                                        <x-mary-icon name="o-check" class="" />
                                                        <span>{{ __('ledger.diff.same_as_current') }}</span>
                                                    </div>
                                                </td>
                                            @else
                                                <td class="align-top py-4 px-4 text-sm leading-relaxed">
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

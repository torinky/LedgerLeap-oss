<div class="space-y-4 p-2">
    <x-element.loading-overlay tier="2" target="showIdentifier,showSemantic" />

    {{-- ─────────────────────────────────────────────────── --}}
    {{-- ツールバー: トグル + 件数表示                        --}}
    {{-- ─────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3 px-2 py-2 bg-base-200/50 rounded-lg border border-base-300/50">
        {{-- 識別番号トグル --}}
        <div class="flex items-center gap-2">
            @if ($hasAutoNumber)
                <input type="checkbox" wire:model.live="showIdentifier"
                    class="toggle toggle-warning toggle-sm"
                    id="toggle-identifier" />
            @else
                <div class="tooltip tooltip-bottom"
                    data-tip="{{ __('ledger.related.identifier_unavailable_tooltip') }}">
                    <input type="checkbox" class="toggle toggle-warning toggle-sm" disabled />
                </div>
            @endif
            <label for="toggle-identifier" class="flex items-center gap-1 text-sm cursor-pointer">
                <span class="badge badge-warning badge-sm gap-1">
                    <i class="fas fa-bookmark text-xs"></i>
                    {{ __('ledger.related.toolbar_identifier') }}
                </span>
                @if ($identifierCount > 0)
                    <span class="text-xs text-base-content/50">({{ $identifierCount }})</span>
                @endif
            </label>
        </div>

        <div class="divider divider-horizontal mx-0 h-6 self-center"></div>

        {{-- 意味検索トグル --}}
        <div class="flex items-center gap-2">
            @if ($ragAvailable)
                <input type="checkbox" wire:model.live="showSemantic"
                    class="toggle toggle-info toggle-sm"
                    id="toggle-semantic" />
            @else
                <div class="tooltip tooltip-bottom"
                    data-tip="{{ __('ledger.related.rag_unavailable_tooltip') }}">
                    <input type="checkbox" class="toggle toggle-info toggle-sm" disabled />
                </div>
            @endif
            <label for="toggle-semantic" class="flex items-center gap-1 text-sm cursor-pointer">
                <span class="badge badge-info badge-sm gap-1">
                    <i class="fas fa-magnifying-glass text-xs"></i>
                    {{ __('ledger.related.toolbar_semantic') }}
                </span>
                @if ($semanticCount > 0)
                    <span class="text-xs text-base-content/50">({{ $semanticCount }})</span>
                @endif
            </label>
        </div>

        {{-- 件数表示 --}}
        <div class="ml-auto text-sm text-base-content/60">
            @if ($totalCount > 0)
                {{ __('ledger.related.count_total', ['count' => $totalCount]) }}
            @endif
        </div>
    </div>

    {{-- ─────────────────────────────────────────────────── --}}
    {{-- ゼロ件 / 利用不可 プレースホルダー                   --}}
    {{-- ─────────────────────────────────────────────────── --}}
    @if (! $hasAutoNumber && ! $ragAvailable)
        <x-ledger.alert
            message="{{ __('ledger.related.empty_unavailable') }}"
            icon="exclamation-triangle"
            type="warning"
            :refreshParentWindow="false" />
    @elseif ($totalCount === 0)
        <x-ledger.alert
            message="{{ __('ledger.related.empty_no_results') }}"
            icon="magnifying-glass"
            type="info"
            :refreshParentWindow="false" />
    @elseif ($filteredCount === 0)
        <x-ledger.alert
            message="{{ __('ledger.related.empty_filter') }}"
            icon="funnel"
            type="warning"
            :refreshParentWindow="false" />
    @else
        {{-- ─────────────────────────────────────────────── --}}
        {{-- ページネーション（上部・固定）                   --}}
        {{-- ─────────────────────────────────────────────── --}}
        @if ($paginator->hasPages())
            <div class="flex justify-center">
                <div class="card bg-base-300 opacity-70 hover:opacity-100 transition-all shadow ring-1 ring-base-content/5">
                    <div class="card-body p-2">
                        {!! $paginator->links('components.ledger.pagination-links', ['position' => 'related-top']) !!}
                    </div>
                </div>
            </div>
        @endif

        {{-- ─────────────────────────────────────────────── --}}
        {{-- 台帳定義グループごとのテーブル                   --}}
        {{-- ─────────────────────────────────────────────── --}}
        @foreach ($groupedResults as $defineId => $items)
            @php
                $define    = $defines[$defineId] ?? null;
                $columns   = $filteredColumnDefinesPerDefine[$defineId] ?? collect();
                $perms     = $permissionsPerDefine[$defineId] ?? ['canUpdate' => false, 'canView' => false];
                $canUpdate = $perms['canUpdate'];
                $canView   = $perms['canView'];
            @endphp
            @if (! $define)
                @continue
            @endif

            <div class="card bg-base-100 shadow-xl border border-base-200 overflow-hidden"
                wire:key="related_group_{{ $defineId }}">

                {{-- 台帳グループヘッダー（簡略版） --}}
                <div class="flex items-center gap-3 bg-primary/40 px-4 py-2">
                    <i class="fas fa-book-open text-primary-content"></i>
                    <h3 class="text-lg font-semibold text-primary-content">{{ $define->title }}</h3>
                    @if ($define->folder)
                        <span class="text-xs text-primary-content/70">
                            <i class="fas fa-folder mr-1"></i>{{ $define->folder->name }}
                        </span>
                    @endif
                </div>

                {{-- テーブル --}}
                <div class="overflow-x-auto max-h-[60vh]">
                    <table class="table table-zebra table-compact table-auto table-pin-rows table-pin-cols w-full">
                        <thead>
                            <tr class="hover z-30" wire:key="related_header_{{ $defineId }}">
                                {{-- 識別理由バッジ列 --}}
                                <th class="w-24 text-center px-3 py-2 bg-accent/30">
                                    <span class="text-xs font-bold text-accent-content">
                                        {{ __('ledger.related.reason_column_label') }}
                                    </span>
                                </th>
                                {{-- アクションボタン列 --}}
                                <th class="w-10 text-center px-4 py-2 bg-accent/30">
                                    <i class="fas fa-cogs"></i>
                                </th>
                                {{-- カラムヘッダー --}}
                                @foreach ($columns as $col)
                                    <th class="px-4 py-2 text-center bg-accent/30"
                                        wire:key="related_col_{{ $defineId }}_{{ $col->id }}">
                                        <span class="text-accent-content font-bold">{{ $col->name }}</span>
                                    </th>
                                @endforeach
                                <th class="px-4 py-2 text-center bg-accent/30">
                                    {{ __('ledger.updated_at') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $ledgerRecord = $item['ledger'];
                                    $reason       = $item['reason'];
                                @endphp
                                <tr class="hover group hover:bg-accent/20"
                                    wire:key="related_row_{{ $ledgerRecord->id }}">
                                    {{-- 識別理由バッジ --}}
                                    <td class="border text-center">
                                        <x-ledger.related-reason-badge :reason="$reason" />
                                    </td>
                                    {{-- 編集・詳細ボタン --}}
                                    <th class="border flex-col bg-accent/20">
                                        <div class="tooltip tooltip-right" data-tip="{{ __('ledger.edit') }}">
                                            @if ($canUpdate && ! $ledgerRecord->isLocked())
                                                <a href="{{ route('ledger.edit', ['tenant' => $currentTenantId, 'ledgerId' => $ledgerRecord->id]) }}"
                                                    class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                                                    target="ledgerEdit_{{ $defineId }}">
                                                    <i class="fas fa-pencil"></i>
                                                </a>
                                            @else
                                                <button class="btn btn-neutral opacity-70 btn-sm my-1 btn-square" disabled>
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="tooltip tooltip-right" data-tip="{{ __('ledger.show_details') }}">
                                            <a href="{{ route('ledger.show', ['tenant' => $currentTenantId, 'ledgerId' => $ledgerRecord->id]) }}"
                                                class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
                                                target="ledgerShow_{{ $defineId }}">
                                                <i class="fas fa-table-list"></i>
                                            </a>
                                        </div>
                                    </th>
                                    {{-- カラム値 --}}
                                    @foreach ($columns as $col)
                                        <td class="px-4 py-2 text-sm"
                                            wire:key="related_cell_{{ $ledgerRecord->id }}_{{ $col->id }}">
                                            @php
                                                $val = $ledgerRecord->content[$col->id] ?? null;
                                                $display = is_array($val) ? implode(', ', array_filter($val)) : ($val ?? '');
                                            @endphp
                                            <span class="line-clamp-2">{{ $display }}</span>
                                        </td>
                                    @endforeach
                                    {{-- 更新日時 --}}
                                    <td class="px-4 py-2 text-xs text-base-content/60 whitespace-nowrap">
                                        {{ $ledgerRecord->updated_at?->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        {{-- ページネーション（下部） --}}
        @if ($paginator->hasPages())
            <div class="mt-4">
                {!! $paginator->links('components.ledger.pagination-links', ['position' => 'related-bottom']) !!}
            </div>
        @endif
    @endif
</div>


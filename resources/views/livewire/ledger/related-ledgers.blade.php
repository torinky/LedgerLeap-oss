<div class="space-y-4 p-2">
    <x-element.loading-overlay tier="2" target="showIdentifier,showSemantic" />

    {{-- ─────────────────────────────────────────────────── --}}
    {{-- ツールバー: トグル + 件数バッジ + 表示レベル         --}}
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
            <label for="toggle-identifier" class="flex items-center gap-1 text-sm cursor-pointer select-none">
                <i class="fas fa-bookmark text-warning"></i>
                <span class="text-sm">{{ __('ledger.related.toolbar_identifier') }}</span>
                @if ($identifierCount > 0)
                    <span class="badge badge-warning badge-sm">{{ $identifierCount }}</span>
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
            <label for="toggle-semantic" class="flex items-center gap-1 text-sm cursor-pointer select-none">
                <i class="fas fa-brain text-info"></i>
                <span class="text-sm">{{ __('ledger.related.toolbar_semantic') }}</span>
                @if ($semanticCount > 0)
                    <span class="badge badge-info badge-sm">{{ $semanticCount }}</span>
                @endif
            </label>
        </div>

        <div class="divider divider-horizontal mx-0 h-6 self-center"></div>

        {{-- 表示レベルセレクタ（基本情報タブと同期） --}}
        @php
            $displayLevelOptions = [
                ['id' => 1, 'name' => __('ledger.form.display_level_options.1')],
                ['id' => 2, 'name' => __('ledger.form.display_level_options.2')],
                ['id' => 3, 'name' => __('ledger.form.display_level_options.3')],
            ];
        @endphp
        <div class="flex items-center gap-1">
            <x-mary-group wire:model.live="$parent.displayLevel" :options="$displayLevelOptions"
                class="[&_label]:btn-ghost [&_label]:btn-xs [&_input:checked+label]:!btn-primary"
                option-value="id" option-label="name"
                wire:key="related-display-level-group" />
        </div>

        {{-- 合計件数バッジ --}}
        <div class="ml-auto">
            @if ($totalCount > 0)
                <span class="badge badge-neutral badge-sm">{{ $totalCount }}</span>
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
                                {{-- アクションボタン列 --}}
                                <th class="w-10 text-center px-4 py-2 bg-accent/30">
                                    <i class="fas fa-cogs"></i>
                                </th>
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
                                    $matchedKeys  = $item['matched_keys'] ?? [];
                                    $score        = $item['score'] ?? null;
                                @endphp
                                <x-ledger.table-row
                                    :ledgerRecord="$ledgerRecord"
                                    :canUpdate="$canUpdate"
                                    :canView="$canView"
                                    :allAttachments="$allAttachments"
                                    :filteredColumnDefines="$columns->toArray()"
                                    :currentTenantId="$currentTenantId"
                                    :highlightKeyword="null"
                                    wire:key="related_row_{{ $ledgerRecord->id }}">
                                    <x-slot:relatedBadge>
                                        <x-ledger.related-reason-badge
                                            :reason="$reason"
                                            :matchedKeys="$matchedKeys"
                                            :score="$score" />
                                    </x-slot:relatedBadge>
                                </x-ledger.table-row>
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


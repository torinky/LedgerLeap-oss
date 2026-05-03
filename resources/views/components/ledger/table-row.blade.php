{{-- 関連案件タブ用: 識別理由インジケーター（null=通常リスト） --}}
@props([
    'ledgerRecord' => null,
    'highlightKeyword' => null,
    'canUpdate' => false,
    'canView' => false,
    'allAttachments' => [],
    'filteredColumnDefines' => [],
    'currentTenantId' => null,
    'relatedBadge' => null,
    'selectedFileId' => null,
    'selectedLedgerId' => null,
    'selectedColumnId' => null,
])
@php
    use App\Helpers\SearchHelper;
    $searchKeywords = SearchHelper::extractKeywords($highlightKeyword);
    $hitHashes = [];
    if (!empty($searchKeywords) && !empty($ledgerRecord->content_attached)) {
        foreach ($ledgerRecord->content_attached as $colId => $colFiles) {
            if (!is_array($colFiles)) {
                continue;
            }
            foreach ($colFiles as $hash => $fileData) {
                // RecordsTable.php で既に計算されている hit フラグがあればそれを採用
                if (!empty($fileData['hit'])) {
                    $hitHashes[$hash] = true;
                    continue;
                }
                // なければ新しく判定（より広範なキーをチェック）
                if (SearchHelper::isFileDataHit($fileData, $searchKeywords)) {
                    $hitHashes[$hash] = true;
                }
            }
        }
    }
@endphp
@php
    $isSelectedLedger = $selectedLedgerId !== null && (int) $selectedLedgerId === (int) $ledgerRecord->id;
@endphp
<tr id="ledger-row-{{ $ledgerRecord->id }}" tabindex="-1"
    class="hover group hover:bg-accent/20 focus:outline-none {{ $isSelectedLedger ? 'ring-2 ring-primary/40 bg-primary/5' : '' }}"
    wire:key="ledger-row-{{ $ledgerRecord->id }}">
    <th scope="row" class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right" data-tip="{{ __('ledger.edit') }}">
            @if ($canUpdate && !$ledgerRecord->isLocked())
                <a href="{{ route('ledger.edit', ['tenant' => $currentTenantId, 'ledgerId' => $ledgerRecord->id]) }}"
                    class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                    target="ledgerEdit_{{ $ledgerRecord->define->id }}}}">
                    <i class="fas fa-pencil"></i>
                </a>
            @else
                <div class="tooltip tooltip-right"
                    data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                    <button class="btn btn-neutral opacity-70 btn-sm my-1 btn-square" disabled>
                        <i class="fas fa-pencil"></i>
                    </button>
                </div>
            @endif
        </div>


        <div class="tooltip tooltip-right" data-tip="{{ __('ledger.show_details') }}">
            @php
                $ledgerShowParams = ['tenant' => $currentTenantId, 'ledgerId' => $ledgerRecord->id];

                if (! empty($highlightKeyword)) {
                    $ledgerShowParams['highlight'] = $highlightKeyword;
                }
            @endphp
            <a href="{{ route('ledger.show', $ledgerShowParams) }}"
                class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
                target="ledgerShow_{{ $ledgerRecord->define->id }}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>

    @foreach ($filteredColumnDefines as $cKey => $columnDefine)
        @php
            $isSelectedColumn = $selectedColumnId !== null && (int) $selectedColumnId === (int) ($columnDefine->id ?? -1);
        @endphp
        <td id="ledger-cell-{{ $ledgerRecord->id }}-{{ $columnDefine->id ?? 'na' }}"
            class="hover:bg-accent/20 border px-4 py-2 transition-colors {{ $isSelectedColumn ? 'ring-2 ring-primary/40 bg-primary/5' : '' }}">
            @php
                $isAttachmentColumn =
                    in_array($columnDefine->type ?? null, ['file', 'files']) ||
                    in_array($columnDefine->input_type ?? null, ['file', 'files']);
                $isMockAttachmentColumn = \App\Services\Ledger\MockAttachmentService::isMockColumn(
                    $columnDefine->id ?? null,
                );
            @endphp
            @if (!$canView)
                <x-ledger.not-authorized-message />
            @elseif($isMockAttachmentColumn && \App\Services\Ledger\MockAttachmentService::isEnabled())
                @php
                    $mockFiles = \App\Services\Ledger\MockAttachmentService::getMockFiles();
                    $mockColumnId = config('mock.attachment.column_id', -1);
                    // モックファイルに対してもヒット判定を行う
                    foreach ($mockFiles as &$mf) {
                        $mf['is_hit'] = SearchHelper::isFileDataHit($mf, $searchKeywords);
                    }
                    unset($mf); // 参照を解除してサイドエフェクトを防ぐ
                @endphp
                <x-ledger.attachment-list :files="$mockFiles" mode="compact" :column-id="$mockColumnId" :tenant-id="$currentTenantId"
                    :search="$highlightKeyword" :selected-file-id="$selectedFileId" />
                <x-ledger.attachment-list :files="$mockFiles" mode="icon-only" :column-id="$mockColumnId" :tenant-id="$currentTenantId"
                    :search="$highlightKeyword" :selected-file-id="$selectedFileId" />
                <x-ledger.attachment-list :files="$mockFiles" mode="full" :column-id="$mockColumnId" :tenant-id="$currentTenantId"
                    :search="$highlightKeyword" :selected-file-id="$selectedFileId" />
            @elseif($isAttachmentColumn)
                <livewire:ledger.records-table-row
                    :ledgerId="$ledgerRecord->id"
                    :columnId="$columnDefine->id"
                    :highlightKeyword="$highlightKeyword"
                    :canView="$canView"
                    :currentTenantId="$currentTenantId"
                    :selectedFileId="$selectedFileId"
                    wire:key="ledger-attachment-cell-{{ $ledgerRecord->id }}-{{ $columnDefine->id }}"
                    defer />
            @else
                @if (empty($ledgerRecord->content[$columnDefine->id]))
                    {{--                    @php \Log::info('Debug table-row: content empty', ['id' => $columnDefine->id, 'content' => $ledgerRecord->content]); @endphp --}}
                    <div class="text-neutral/50 flex w-full items-center justify-center">
                        <i class="fa-solid fa-cube text-info/50 mr-2"></i>
                        <span>{{ __('ledger.empty') }}</span>
                    </div>
                @else
                    @php
                        //                        \Log::info('Debug table-row: rendering content', ['id' => $columnDefine->id, 'val' => $ledgerRecord->content[$columnDefine->id]]);
                        // ColumnHtmlServiceを使用してバッジ表示などを適切にレンダリング
                        $columnHtml = ColumnHtml::setAttachmentCollection(
                            $allAttachments->get($ledgerRecord->id, collect())->keyBy('hashedbasename'),
                        )
                            ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                            ->show(
                                $columnDefine,
                                $ledgerRecord->content[$columnDefine->id],
                                $canView,
                                [],
                                '',
                                false,
                                $ledgerRecord,
                                $highlightKeyword,
                                $currentTenantId,
                            );
                        $columnHtmlString = $columnHtml->toHtml();
                        $columnTextLength = mb_strlen(trim(strip_tags($columnHtmlString)));
                        $showToggleHint = $columnTextLength > 120
                            || str_contains($columnHtmlString, '<br')
                            || str_contains($columnHtmlString, '<p')
                            || str_contains($columnHtmlString, '<ul')
                            || str_contains($columnHtmlString, '<ol');
                    @endphp

                    <x-expandable-content
                        :content="$columnHtmlString"
                        max-height="6rem"
                        :show-toggle-hint="$showToggleHint"
                        skip-measurement
                    />
                @endif
            @endif
        </td>
    @endforeach
        {{--                        <td class="border px-4 py-2 wrap-break-word whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}} --}}


    <td class="border px-4 py-2 relative">
        {{ $ledgerRecord->updated_at->format('Y-m-d H:i:s') }}
        <span class="text-gray-500">{{ JpDatetime::date('(bk)', $ledgerRecord->updated_at->timestamp) }}</span>
        <br />( {{ $ledgerRecord->updated_at->diffForHumans() }} )

        <!-- スコア・ステータスのオーバーレイ表示 -->
        <div
            class="absolute top-1 right-2 z-10 flex items-center gap-2 transition-opacity duration-300 opacity-30 group-hover:opacity-100 backdrop-blur-sm p-1 rounded-lg">

            {{-- 関連案件タブ用: 識別理由インジケーター（通常リストでは非表示） --}}
            @if ($relatedBadge !== null)
                {{ $relatedBadge }}
            @endif
            @php
                // 表示するスコアとその種類を決定
                $displayScore = null;
                $scoreType = null;
                $scoreClass = '';

                if (isset($ledgerRecord->semantic_score) && $ledgerRecord->semantic_score > 0) {
                    // セマンティックスコアが存在する場合（セマンティック検索モード）
                    $displayScore = $ledgerRecord->semantic_score;
                    $scoreType = 'semantic';
                    // 類似度スコア (0.0-1.0) に基づく色分け
                    $scoreClass = match (true) {
                        $displayScore >= 0.8 => 'badge-success',
                        $displayScore >= 0.6 => 'badge-primary',
                        $displayScore >= 0.4 => 'badge-info',
                        $displayScore > 0 => 'badge-ghost',
                        default => '',
                    };
                } elseif ($ledgerRecord->composite_score > 0) {
                    // 通常の総合スコア
                    $displayScore = $ledgerRecord->composite_score;
                    $scoreType = 'composite';
                    $scoreClass = match (true) {
                        $displayScore >= 70 => 'badge-success',
                        $displayScore >= 40 => 'badge-primary',
                        $displayScore >= 20 => 'badge-info',
                        $displayScore > 0 => 'badge-ghost',
                        default => '',
                    };
                }

                // ステータスに応じたアイコンを決定 (Enumから取得)
                $statusIcon = $ledgerRecord->status->icon();
            @endphp

            @if ($displayScore !== null)
                <span class="badge badge-xl {{ $scoreClass }} flex items-center gap-1 tooltip"
                    data-tip="{{ $scoreType === 'semantic' ? __('ledger.semantic_score_tooltip') : __('ledger.composite_score_tooltip') }}">
                    @if ($scoreType === 'semantic')
                        <i class="fas fa-brain"></i> {{-- セマンティックスコアアイコン --}}
                        {{ number_format($displayScore * 100, 1) }}% {{-- 0.0-1.0 を 0-100% に変換 --}}
                    @else
                        <i class="fas fa-star"></i> {{-- 総合スコアアイコン --}}
                        {{ number_format($displayScore, 1) }}
                    @endif
                </span>
            @endif

            @if ($ledgerRecord->define->workflow_enabled && $ledgerRecord->status)
                <span class="badge badge-lg {{ $ledgerRecord->status->colorClass() }} flex items-center gap-1">
                    <i class="{{ $statusIcon }}"></i> {{-- ステータスアイコン --}}
                    {{ $ledgerRecord->status->label() }}
                </span>
            @endif
        </div>
    </td>
    {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td> --}}
</tr>

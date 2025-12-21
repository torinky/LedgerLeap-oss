@props([
    'ledgerRecord' => null,
    'highlightKeyword' => null,
    'canUpdate' => false,
    'canView' => false,
    'allAttachments' => [],
    'filteredColumnDefines' => [],
    'currentTenantId' => null,
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
<tr class="hover group hover:bg-accent/20">
    <th class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right" data-tip="{{ __('ledger.edit') }}">
            @if ($canUpdate && !$ledgerRecord->isLocked())
                <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId' => $ledgerRecord->id]) }}"
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
            <a href="{{ route('ledger.show', ['tenant' => $ledgerRecord->tenant_id ?? tenant()?->id, 'ledgerId' => $ledgerRecord->id, 'highlight' => $highlightKeyword]) }}"
                class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
                target="ledgerShow_{{ $ledgerRecord->define->id }}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>

    @foreach ($filteredColumnDefines as $cKey => $columnDefine)
        <td class="hover:bg-accent/20 border px-4 py-2">
            @php
                $isAttachmentColumn =
                    ($columnDefine->type ?? null) === 'file' || ($columnDefine->input_type ?? null) === 'file';
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
                <x-ledger.attachment-list :files="$mockFiles" mode="compact" :column-id="$mockColumnId" :tenant-id="$currentTenantId ?? tenant()?->id" :search="$highlightKeyword" />
                <x-ledger.attachment-list :files="$mockFiles" mode="icon-only" :column-id="$mockColumnId" :tenant-id="$currentTenantId ?? tenant()?->id" :search="$highlightKeyword" />
                <x-ledger.attachment-list :files="$mockFiles" mode="full" :column-id="$mockColumnId" :tenant-id="$currentTenantId ?? tenant()?->id" :search="$highlightKeyword" />
            @elseif($isAttachmentColumn)
                @php
                    $files = [];
                    $columnId = $columnDefine->id;
                    $attached = $allAttachments[$columnId] ?? [];
                    foreach ($attached as $af) {
                        // Download Logic (Same as ColumnHtmlService)
                        $mainDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId ?? tenant()?->id,
                            'attachedFile' => $af->id,
                        ]);
                        $thumbnailUrl = null;
                        if (
                            str_starts_with($af->original_mime_type, 'image/') &&
                            \Illuminate\Support\Facades\Storage::disk('public')->exists(
                                \App\Helpers\AttachedFilePathHelper::getThumbnailStoragePath(
                                    basename($af->filename),
                                    $currentTenantId ?? tenant()?->id,
                                ),
                            )
                        ) {
                            $thumbnailUrl = route('file.download', [
                                'tenant' => $currentTenantId ?? tenant()?->id,
                                'attachedFile' => $af->id,
                                'thumbnail' => 'true',
                            ]);
                        }
                        $originalDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId ?? tenant()?->id,
                            'attachedFile' => $af->id,
                            'original' => true,
                        ]);
                        $optimizedPdfDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId ?? tenant()?->id,
                            'attachedFile' => $af->id,
                        ]);

                        $primaryDownload = null;
                        $secondaryDownload = null;

                        if (str_starts_with($af->original_mime_type, 'image/')) {
                            $primaryDownload = [
                                'url' => $originalDownloadUrl,
                                'label' => __('ledger.uploadedFile.download_image'),
                                'icon' => 'fa-download',
                            ];
                            $secondaryDownload = [
                                'url' => $optimizedPdfDownloadUrl,
                                'label' => 'PDF',
                                'icon' => 'fa-file-pdf',
                                'tooltip' => __('ledger.uploadedFile.download_pdf_with_text'),
                            ];
                        } elseif ($af->original_mime_type === 'application/pdf' && $af->optimized) {
                            $primaryDownload = [
                                'url' => $optimizedPdfDownloadUrl,
                                'label' => __('ledger.uploadedFile.download_optimized_pdf'),
                                'icon' => 'fa-file-pdf',
                            ];
                            $secondaryDownload = [
                                'url' => $originalDownloadUrl,
                                'label' => 'Original',
                                'icon' => 'fa-file',
                                'tooltip' => __('ledger.uploadedFile.download_original_pdf'),
                            ];
                        } else {
                            $primaryDownload = [
                                'url' => $mainDownloadUrl,
                                'label' => __('ledger.download'),
                                'icon' => 'fa-download',
                            ];
                        }

                        $files[] = [
                            'id' => $af->id,
                            'filename' =>
                                $af->original_filename ??
                                ($ledgerRecord->content[$columnId][$af->hashedbasename] ??
                                    (basename($af->filename) ?? '')),
                            'mime' => $af->original_mime_type ?? ($af->mime ?? 'application/octet-stream'),
                            'status' => $af->getDisplayStatus()->value ?? 'completed',
                            'size' => $af->size,
                            'thumbnailUrl' => $thumbnailUrl,
                            'downloadUrl' => $mainDownloadUrl, // Backward compatibility
                            'primary_download' => $primaryDownload,
                            'secondary_download' => $secondaryDownload,
                            'created_at' => $af->created_at,
                            'is_hit' => isset($hitHashes[$af->hashedbasename]),
                        ];
                    }
                    if (
                        empty($files) &&
                        isset($ledgerRecord->content_attached[$columnId]) &&
                        is_array($ledgerRecord->content_attached[$columnId])
                    ) {
                        foreach ($ledgerRecord->content_attached[$columnId] as $hashed => $metaOrName) {
                            $originalName = is_array($metaOrName)
                                ? $metaOrName['name'] ?? ($metaOrName['original'] ?? $hashed)
                                : $metaOrName ?? $hashed;
                            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                            $mime = match ($ext) {
                                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg' => 'image/*',
                                'pdf' => 'application/pdf',
                                'doc',
                                'docx'
                                    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'xls', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                default => 'application/octet-stream',
                            };
                            $files[] = [
                                'id' => 0,
                                'filename' => $originalName,
                                'mime' => $mime,
                                'status' => 'completed',
                                'thumbnailUrl' => null,
                                'downloadUrl' => '#',
                                'is_hit' => isset($hitHashes[$hashed]),
                            ];
                        }
                    }
                @endphp
                @if (!empty($files))
                    <x-ledger.attachment-list :files="$files" mode="compact" :column-id="$columnDefine->id" :tenant-id="$currentTenantId ?? tenant()?->id" :search="$highlightKeyword" />
                @else
                    <x-ledger.empty-message />
                @endif
            @else
                @if (empty($ledgerRecord->content[$columnDefine->id]))
                    <x-ledger.empty-message />
                @else
                    @php
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
                                $currentTenantId ?? tenant()?->id,
                            );
                        $columnHtmlString = $columnHtml->toHtml();
                    @endphp

                    <x-expandable-content :content="$columnHtmlString" max-height="6rem" />
                @endif
            @endif
        </td>
    @endforeach
    {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}} --}}


    <td class="border px-4 py-2 relative">
        {{ $ledgerRecord->updated_at->format('Y-m-d H:i:s') }}
        <span class="text-gray-500">{{ JpDatetime::date('(bk)', $ledgerRecord->updated_at->timestamp) }}</span>
        <br />( {{ $ledgerRecord->updated_at->diffForHumans() }} )

        <!-- スコア・ステータスのオーバーレイ表示 -->
        <div
            class="absolute top-1 right-2 z-10 flex items-center gap-2 transition-opacity duration-300 opacity-30 group-hover:opacity-100 backdrop-blur-sm p-1 rounded-lg">
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

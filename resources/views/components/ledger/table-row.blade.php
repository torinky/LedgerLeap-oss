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
                @php
                    $files = [];
                    $prepareStartedAt = microtime(true);
                    $lookupDurationMs = 0.0;
                    $routeDurationMs = 0.0;
                    $payloadDurationMs = 0.0;
                    $fileBuildDurationMs = 0.0;
                    $fallbackBuildDurationMs = 0.0;
                    $filenameMapBuildDurationMs = 0.0;
                    // ここでは、既に $allAttachments は親（RecordsTable）から渡されており、
                    // [ledger_id => attachments[]] の形でグループ化されている。
                    $columnId = $columnDefine->id ?? null;
                    $lookupStartedAt = microtime(true);
                    $attached = $allAttachments->get($ledgerRecord->id, collect())->where('column_id', $columnId);
                    $lookupDurationMs += (microtime(true) - $lookupStartedAt) * 1000;

                    $originalFilenameMap = [];
                    if (! empty($ledgerRecord->content)) {
                        $filenameMapStartedAt = microtime(true);
                        foreach ($ledgerRecord->content as $columnContent) {
                            if (! is_array($columnContent)) {
                                continue;
                            }

                            foreach ($columnContent as $hashedbasename => $originalName) {
                                if (is_string($originalName) && $originalName !== '') {
                                    $originalFilenameMap[$hashedbasename] = $originalName;
                                }
                            }
                        }
                        $filenameMapBuildDurationMs += (microtime(true) - $filenameMapStartedAt) * 1000;
                    }

                    $filesStartedAt = microtime(true);
                    $downloadBundleDurationMs = 0.0;
                    $scalarFieldDurationMs = 0.0;
                    $hitFlagDurationMs = 0.0;
                    $filenameResolveDurationMs = 0.0;
                    $filenameOriginalDurationMs = 0.0;
                    $filenameAttachedLookupDurationMs = 0.0;
                    $filenameBasenameDurationMs = 0.0;
                    $arrayAssemblyDurationMs = 0.0;
                    foreach ($attached as $af) {
                        // Download Logic (Same as ColumnHtmlService)
                        $routeStartedAt = microtime(true);
                        $mainDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId,
                            'attachedFile' => $af->id,
                        ]);
                        $thumbnailUrl = str_starts_with($af->original_mime_type, 'image/')
                            ? route('file.download', [
                                'tenant' => $currentTenantId,
                                'attachedFile' => $af->id,
                                'thumbnail' => true,
                            ])
                            : null;
                        $downloadBundleStartedAt = microtime(true);
                        $originalDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId,
                            'attachedFile' => $af->id,
                            'original' => true,
                        ]);
                        $optimizedPdfDownloadUrl = route('file.download', [
                            'tenant' => $currentTenantId,
                            'attachedFile' => $af->id,
                        ]);
                        $routeDurationMs += (microtime(true) - $routeStartedAt) * 1000;

                        $payloadStartedAt = microtime(true);
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
                        $downloadBundleDurationMs += (microtime(true) - $downloadBundleStartedAt) * 1000;

                        $scalarFieldStartedAt = microtime(true);
                        $statusValue = $af->getDisplayStatus()->value ?? 'completed';
                        $mimeValue = $af->original_mime_type ?? ($af->mime ?? 'application/octet-stream');
                        $sizeValue = $af->size;
                        $createdAtValue = $af->created_at;
                        $scalarFieldDurationMs += (microtime(true) - $scalarFieldStartedAt) * 1000;

                        $hitFlagStartedAt = microtime(true);
                        $hitFlagValue = isset($hitHashes[$af->hashedbasename]);
                        $hitFlagDurationMs += (microtime(true) - $hitFlagStartedAt) * 1000;

                        $filenameStartedAt = microtime(true);
                        $filenameValue = null;
                        $filenameOriginalStartedAt = microtime(true);
                        $originalFilename = $originalFilenameMap[$af->hashedbasename] ?? null;
                        $filenameOriginalDurationMs += (microtime(true) - $filenameOriginalStartedAt) * 1000;
                        if ($originalFilename) {
                            $filenameValue = $originalFilename;
                        } else {
                            $filenameAttachedStartedAt = microtime(true);
                            $columnContent = $ledgerRecord->content[$columnId] ?? null;
                            $attachedName = is_array($columnContent)
                                ? ($columnContent[$af->hashedbasename] ?? null)
                                : $columnContent;
                            $filenameAttachedLookupDurationMs += (microtime(true) - $filenameAttachedStartedAt) * 1000;
                            if ($attachedName) {
                                $filenameValue = $attachedName;
                            } else {
                                $filenameBasenameStartedAt = microtime(true);
                                $filenameValue = basename($af->filename) ?? '';
                                $filenameBasenameDurationMs += (microtime(true) - $filenameBasenameStartedAt) * 1000;
                            }
                        }
                        $filenameResolveDurationMs += (microtime(true) - $filenameStartedAt) * 1000;

                        $arrayStartedAt = microtime(true);
                        $files[] = [
                            'id' => $af->id,
                            'filename' => $filenameValue,
                            'mime' => $mimeValue,
                            'status' => $statusValue,
                            'size' => $sizeValue,
                            'thumbnailUrl' => $thumbnailUrl,
                            'downloadUrl' => $mainDownloadUrl, // Backward compatibility
                            'primary_download' => $primaryDownload,
                            'secondary_download' => $secondaryDownload,
                            'created_at' => $createdAtValue,
                            'is_hit' => $hitFlagValue,
                        ];
                        $arrayAssemblyDurationMs += (microtime(true) - $arrayStartedAt) * 1000;
                        $payloadDurationMs += (microtime(true) - $payloadStartedAt) * 1000;
                    }
                    $fileBuildDurationMs += (microtime(true) - $filesStartedAt) * 1000;

                    $fallbackStartedAt = microtime(true);
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
                    $fallbackBuildDurationMs += (microtime(true) - $fallbackStartedAt) * 1000;

                    \Log::info('[AttachmentHtml] prepareFilesData', [
                        'source' => 'table-row',
                        'ledger_id' => $ledgerRecord->id,
                        'column_id' => $columnId,
                        'file_count' => count($files),
                        'attachment_count' => $attached->count(),
                        'lookup_ms' => round($lookupDurationMs, 2),
                        'route_build_ms' => round($routeDurationMs, 2),
                        'download_bundle_ms' => round($downloadBundleDurationMs, 2),
                        'scalar_field_ms' => round($scalarFieldDurationMs, 2),
                        'hit_flag_ms' => round($hitFlagDurationMs, 2),
                        'filename_resolve_ms' => round($filenameResolveDurationMs, 2),
                        'filename_map_build_ms' => round($filenameMapBuildDurationMs, 2),
                        'filename_original_ms' => round($filenameOriginalDurationMs, 2),
                        'filename_attached_lookup_ms' => round($filenameAttachedLookupDurationMs, 2),
                        'filename_basename_ms' => round($filenameBasenameDurationMs, 2),
                        'array_assembly_ms' => round($arrayAssemblyDurationMs, 2),
                        'payload_build_ms' => round($payloadDurationMs, 2),
                        'file_build_ms' => round($fileBuildDurationMs, 2),
                        'fallback_build_ms' => round($fallbackBuildDurationMs, 2),
                        'duration_ms' => round((microtime(true) - $prepareStartedAt) * 1000, 2),
                    ]);
                @endphp
                @if (!empty($files))
                    @php
                        $renderStartedAt = microtime(true);
                        $attachmentListHtml = view('components.ledger.attachment-list', [
                            'files' => $files,
                            'mode' => 'compact',
                            'columnId' => $columnDefine->id,
                            'tenantId' => $currentTenantId,
                            'search' => $highlightKeyword,
                            'selectedFileId' => $selectedFileId,
                        ])->render();

                        \Log::info('[AttachmentHtml] getFileHtml', [
                            'source' => 'table-row',
                            'ledger_id' => $ledgerRecord->id,
                            'column_id' => $columnId,
                            'mode' => 'compact',
                            'file_count' => count($files),
                            'duration_ms' => round((microtime(true) - $renderStartedAt) * 1000, 2),
                        ]);
                    @endphp
                    {!! $attachmentListHtml !!}
                @else
                    <div class="text-neutral/50 flex w-full items-center justify-center">
                        <i class="fa-solid fa-cube text-info/50 mr-2"></i>
                        <span>{{ __('ledger.empty') }}</span>
                    </div>
                @endif
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

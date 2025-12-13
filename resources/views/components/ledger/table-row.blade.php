@props([
    'ledgerRecord'=>null,
    'highlightKeyword'=>null,
    'canUpdate'=>false,
    'canView'=>false,
    'allAttachments' => [],
    'filteredColumnDefines' => [],
    'currentTenantId' => null,
    ])
<tr class="hover group hover:bg-accent/20">
    <th class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.edit')}}"
        >
            @if($canUpdate && !$ledgerRecord->isLocked())
                <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                   target="ledgerEdit_{{$ledgerRecord->define->id}}}}"
                >
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


        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.show_details')}}"
        >
            <a href="{{ route('ledger.show', ['tenant' => $ledgerRecord->tenant_id ?? tenant()?->id, 'ledgerId'=>$ledgerRecord->id, 'highlight' => $highlightKeyword]) }}"
               class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
               target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>

    @foreach($filteredColumnDefines as $cKey=>$columnDefine)
        <td class="hover:bg-accent/20 border px-4 py-2">
            @php
                $isAttachmentColumn = ($columnDefine->type ?? null) === 'file' || ($columnDefine->input_type ?? null) === 'file';
                $isMockAttachmentColumn = ($columnDefine->id ?? null) === '__mock_files';
            @endphp
            @if (!$canView)
                <x-ledger.not-authorized-message/>
            @elseif($isMockAttachmentColumn)
                @php
                    // 詳細計画に沿った各ファイルタイプのモックバリエーション
                    $mockFiles = [
                        // 画像ファイル（JPG）- 完了、サムネイルあり
                        [
                            'id' => 1,
                            'filename' => '領収書_2025-12-01.jpg',
                            'mime' => 'image/jpeg',
                            'status' => 'completed',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/4CAF50/FFFFFF?text=JPG',
                            'downloadUrl' => '#',
                        ],
                        // PDF（テキスト付き）- 完了
                        [
                            'id' => 2,
                            'filename' => '契約書_2025年度.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                        // 画像ファイル（PNG）- 処理中
                        [
                            'id' => 3,
                            'filename' => 'スクリーンショット.png',
                            'mime' => 'image/png',
                            'status' => 'processing',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/FFC107/FFFFFF?text=PNG',
                            'downloadUrl' => '#',
                        ],
                        // Office文書（Word）- 完了
                        [
                            'id' => 4,
                            'filename' => '報告書_第4四半期.docx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                        // PDF（スキャン画像のみ）- 完了
                        [
                            'id' => 5,
                            'filename' => 'スキャン文書_20251213.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                        // Office文書（Excel）- 完了
                        [
                            'id' => 6,
                            'filename' => '売上集計表_12月.xlsx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                        // その他ファイル（ZIP）- エラー
                        [
                            'id' => 7,
                            'filename' => '資料一式.zip',
                            'mime' => 'application/zip',
                            'status' => 'error',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                        // テキストファイル - 完了
                        [
                            'id' => 8,
                            'filename' => '議事録_20251213.txt',
                            'mime' => 'text/plain',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                        ],
                    ];
                @endphp
                <x-ledger.attachment-list :files="$mockFiles" mode="icon-only" :tenant-id="$currentTenantId ?? tenant()?->id" />
            @elseif($isAttachmentColumn)
                @php
                    $files = [];
                    $columnId = $columnDefine->id;
                    $attached = $allAttachments[$columnId] ?? [];
                    foreach ($attached as $af) {
                        $files[] = [
                            'id' => $af->id,
                            'filename' => $af->original_filename ?? ($ledgerRecord->content[$columnId][$af->hashedbasename] ?? basename($af->filename) ?? ''),
                            'mime' => $af->original_mime_type ?? $af->mime ?? 'application/octet-stream',
                            'status' => $af->getDisplayStatus()->value ?? 'completed',
                            'thumbnailUrl' => route('file.download', ['tenant' => $currentTenantId ?? tenant()?->id, 'attachedFile' => $af->id, 'thumbnail' => 'true']),
                            'downloadUrl' => route('file.download', ['tenant' => $currentTenantId ?? tenant()?->id, 'attachedFile' => $af->id]),
                        ];
                    }
                    if (empty($files) && isset($ledgerRecord->content_attached[$columnId]) && is_array($ledgerRecord->content_attached[$columnId])) {
                        foreach ($ledgerRecord->content_attached[$columnId] as $hashed => $metaOrName) {
                            $originalName = is_array($metaOrName) ? ($metaOrName['name'] ?? $metaOrName['original'] ?? $hashed) : ($metaOrName ?? $hashed);
                            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                            $mime = match ($ext) {
                                'jpg','jpeg','png','gif','bmp','webp','svg' => 'image/*',
                                'pdf' => 'application/pdf',
                                'doc','docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'xls','xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                default => 'application/octet-stream',
                            };
                            $files[] = [
                                'id' => 0,
                                'filename' => $originalName,
                                'mime' => $mime,
                                'status' => 'completed',
                                'thumbnailUrl' => null,
                                'downloadUrl' => '#',
                            ];
                        }
                    }
                @endphp
                @if (!empty($files))
                    <x-ledger.attachment-list :files="$files" mode="icon-only" :tenant-id="$currentTenantId ?? tenant()?->id" />
                @else
                    <x-ledger.empty-message/>
                @endif
            @else
                @if (empty($ledgerRecord->content[$columnDefine->id]))
                    <x-ledger.empty-message/>
                @else
                    {{-- テキスト/Markdownの安全表示（長文は省略） --}}
                    @php
                        $raw = $ledgerRecord->content[$columnDefine->id];
                        $display = is_string($raw) ? \Illuminate\Support\Str::limit($raw, 200) : (is_array($raw) ? json_encode($raw, JSON_UNESCAPED_UNICODE) : (string) $raw);
                    @endphp
                    <div class="prose max-w-none">{!! \Illuminate\Support\Str::markdown($display, ['html_input' => 'strip']) !!}</div>
                @endif
            @endif
        </td>
    @endforeach
    {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}--}}


    <td class="border px-4 py-2 relative">
        {{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}
        <span class="text-gray-500">{{JpDatetime::date('(bk)',$ledgerRecord->updated_at->timestamp)}}</span>
        <br/>( {{ $ledgerRecord->updated_at->diffForHumans() }} )

        <!-- スコア・ステータスのオーバーレイ表示 -->
        <div class="absolute top-1 right-2 z-10 flex items-center gap-2 transition-opacity duration-300 opacity-30 group-hover:opacity-100 backdrop-blur-sm p-1 rounded-lg">
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
                    $scoreClass = match(true) {
                        $displayScore >= 0.8 => 'badge-success',
                        $displayScore >= 0.6 => 'badge-primary',
                        $displayScore >= 0.4 => 'badge-info',
                        $displayScore > 0 => 'badge-ghost',
                        default => ''
                    };
                } elseif ($ledgerRecord->composite_score > 0) {
                    // 通常の総合スコア
                    $displayScore = $ledgerRecord->composite_score;
                    $scoreType = 'composite';
                    $scoreClass = match(true) {
                        $displayScore >= 70 => 'badge-success',
                        $displayScore >= 40 => 'badge-primary',
                        $displayScore >= 20 => 'badge-info',
                        $displayScore > 0 => 'badge-ghost',
                        default => ''
                    };
                }
                
                // ステータスに応じたアイコンを決定 (Enumから取得)
                $statusIcon = $ledgerRecord->status->icon();
            @endphp
            
            @if($displayScore !== null)
                <span class="badge badge-xl {{ $scoreClass }} flex items-center gap-1 tooltip"
                      data-tip="{{ $scoreType === 'semantic' ? __('ledger.semantic_score_tooltip') : __('ledger.composite_score_tooltip') }}">
                    @if($scoreType === 'semantic')
                        <i class="fas fa-brain"></i> {{-- セマンティックスコアアイコン --}}
                        {{ number_format($displayScore * 100, 1) }}% {{-- 0.0-1.0 を 0-100% に変換 --}}
                    @else
                        <i class="fas fa-star"></i> {{-- 総合スコアアイコン --}}
                        {{ number_format($displayScore, 1) }}
                    @endif
                </span>
            @endif

            @if($ledgerRecord->define->workflow_enabled && $ledgerRecord->status)
                <span class="badge badge-lg {{ $ledgerRecord->status->colorClass() }} flex items-center gap-1">
                    <i class="{{ $statusIcon }}"></i> {{-- ステータスアイコン --}}
                    {{ $ledgerRecord->status->label() }}
                </span>
            @endif
        </div>
    </td>
    {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td>--}}
</tr>

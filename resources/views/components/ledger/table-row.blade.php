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
                    // 詳細計画に沿った各ファイルタイプのモックバリエーション（OCR処理状態含む）
                    $mockFiles = [
                        // 画像ファイル（JPG）- OCR処理済み、PDF変換済み
                        [
                            'id' => 1,
                            'filename' => '領収書_2025-12-01.jpg',
                            'mime' => 'image/jpeg',
                            'status' => 'completed',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/4CAF50/FFFFFF?text=JPG',
                            'downloadUrl' => '#',
                            'mock_preview_text' => '株式会社サンプル商事\n領収書\n\n日付：2025年12月1日\n金額：¥15,000\n但書：書籍代として\n\n上記正に領収いたしました。',
                            'mock_confidence' => 0.95,
                            'mock_source' => 'OCR',
                            'ocr_processed_at' => now()->subDays(2),
                            'original_mime_type' => 'image/jpeg',
                        ],
                        // PDF（テキスト付き）- OCRmyPDF最適化済み
                        [
                            'id' => 2,
                            'filename' => '契約書_2025年度.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '業務委託契約書\n\n第一条（目的）\n本契約は、甲と乙の間における業務委託に関する事項を定めることを目的とする。\n\n第二条（委託業務）\n甲は乙に対し、以下の業務を委託する...',
                            'mock_confidence' => 0.88,
                            'mock_source' => 'Tika',
                            'ocr_processed_at' => now()->subDays(5),
                            'original_mime_type' => 'application/pdf',
                        ],
                        // 画像ファイル（PNG）- OCR処理中
                        [
                            'id' => 3,
                            'filename' => 'スクリーンショット.png',
                            'mime' => 'image/png',
                            'status' => 'processing',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/FFC107/FFFFFF?text=PNG',
                            'downloadUrl' => '#',
                            'mock_preview_text' => null,
                            'mock_confidence' => 0.0,
                            'mock_source' => 'OCR',
                            'ocr_processed_at' => null,
                            'original_mime_type' => 'image/png',
                        ],
                        // Office文書（Word）- 完了（OCR不要）
                        [
                            'id' => 4,
                            'filename' => '報告書_第4四半期.docx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '第4四半期報告書\n\n1. 売上実績\n当四半期の売上は前年同期比120%を達成しました...',
                            'mock_confidence' => 0.92,
                            'mock_source' => 'Tika',
                            'ocr_processed_at' => null,
                            'original_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        // PDF（スキャン画像のみ）- OCR処理済み
                        [
                            'id' => 5,
                            'filename' => 'スキャン文書_20251213.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '社内通達\n\n件名：年末年始の営業について\n\n平素より格別のご高配を賜り、厚く御礼申し上げます。\n誠に勝手ながら、下記の期間を年末年始休業とさせていただきます...',
                            'mock_confidence' => 0.78,
                            'mock_source' => 'OCR',
                            'ocr_processed_at' => now()->subDays(1),
                            'original_mime_type' => 'application/pdf',
                        ],
                        // Office文書（Excel）- 完了（OCR不要）
                        [
                            'id' => 6,
                            'filename' => '売上集計表_12月.xlsx',
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '売上集計表 - 12月\n\n商品A: ¥1,250,000\n商品B: ¥980,000\n商品C: ¥1,540,000\n合計: ¥3,770,000',
                            'mock_confidence' => 0.85,
                            'mock_source' => 'Tika',
                            'ocr_processed_at' => null,
                            'original_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                        // その他ファイル（ZIP）- エラー（OCR対象外）
                        [
                            'id' => 7,
                            'filename' => '資料一式.zip',
                            'mime' => 'application/zip',
                            'status' => 'error',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => null,
                            'mock_confidence' => null,
                            'mock_source' => null,
                            'ocr_processed_at' => null,
                            'original_mime_type' => 'application/zip',
                        ],
                        // テキストファイル - 完了（OCR不要）
                        [
                            'id' => 8,
                            'filename' => '議事録_20251213.txt',
                            'mime' => 'text/plain',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '議事録\n日時：2025年12月13日 14:00-16:00\n場所：会議室A\n\n議題：\n1. 来期の事業計画について\n2. 新製品開発の進捗報告\n3. その他',
                            'mock_confidence' => 0.98,
                            'mock_source' => 'Tika',
                            'ocr_processed_at' => null,
                            'original_mime_type' => 'text/plain',
                        ],
                        // 画像ファイル（JPEG）- VLM解析済み、高信頼度
                        [
                            'id' => 9,
                            'filename' => '名刺_田中様.jpg',
                            'mime' => 'image/jpeg',
                            'status' => 'completed',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/2196F3/FFFFFF?text=Card',
                            'downloadUrl' => '#',
                            'mock_preview_text' => '田中太郎\n営業部長\n株式会社テクノロジー\n\nTEL: 03-1234-5678\nEmail: tanaka@example.com\n〒100-0001 東京都千代田区...',
                            'mock_confidence' => 0.97,
                            'mock_source' => 'VLM',
                            'ocr_processed_at' => now()->subHours(3),
                            'original_mime_type' => 'image/jpeg',
                        ],
                        // PDF（複合）- VLM + OCR処理済み
                        [
                            'id' => 10,
                            'filename' => '見積書_202512.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '御見積書\n\n株式会社サンプル 御中\n\n下記の通りお見積もり申し上げます。\n\n品名：システム開発一式\n金額：¥5,000,000（税別）\n納期：2026年3月末\n\n有効期限：2025年12月31日まで',
                            'mock_confidence' => 0.91,
                            'mock_source' => 'VLM',
                            'ocr_processed_at' => now()->subDays(3),
                            'original_mime_type' => 'application/pdf',
                        ],
                        // 画像ファイル（PNG）- OCR低信頼度（手書き）
                        [
                            'id' => 11,
                            'filename' => '手書きメモ.png',
                            'mime' => 'image/png',
                            'status' => 'completed',
                            'thumbnailUrl' => 'https://via.placeholder.com/150x150/FF9800/FFFFFF?text=Note',
                            'downloadUrl' => '#',
                            'mock_preview_text' => '明日の打ち合わせ\n・資料準備\n・会議室予約\n・参加者確認',
                            'mock_confidence' => 0.65,
                            'mock_source' => 'OCR',
                            'ocr_processed_at' => now()->subHours(12),
                            'original_mime_type' => 'image/png',
                        ],
                        // PDF（大容量）- OCRmyPDF最適化で大幅サイズ削減
                        [
                            'id' => 12,
                            'filename' => 'カタログ_2025.pdf',
                            'mime' => 'application/pdf',
                            'status' => 'completed',
                            'thumbnailUrl' => null,
                            'downloadUrl' => '#',
                            'mock_preview_text' => '製品カタログ 2025年版\n\n新製品ラインナップ\n・モデルA：高性能タイプ\n・モデルB：標準タイプ\n・モデルC：エントリータイプ\n\n詳細な仕様については各ページをご参照ください...',
                            'mock_confidence' => 0.82,
                            'mock_source' => 'OCR',
                            'ocr_processed_at' => now()->subDays(7),
                            'original_mime_type' => 'application/pdf',
                        ],
                    ];
                @endphp
                <x-ledger.attachment-list :files="$mockFiles" mode="compact" :tenant-id="$currentTenantId ?? tenant()?->id" />
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
                    <x-ledger.attachment-list :files="$files" mode="compact" :tenant-id="$currentTenantId ?? tenant()?->id" />
                @else
                    <x-ledger.empty-message/>
                @endif
            @else
                @if (empty($ledgerRecord->content[$columnDefine->id]))
                    <x-ledger.empty-message/>
                @else
                    @php
                        // ColumnHtmlServiceを使用してバッジ表示などを適切にレンダリング
                        $columnHtml = ColumnHtml::setAttachmentCollection($allAttachments->get($ledgerRecord->id, collect())->keyBy('hashedbasename'))
                                     ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                                     ->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView, [], '', false, $ledgerRecord, $highlightKeyword, $currentTenantId ?? tenant()?->id);
                        $columnHtmlString = $columnHtml->toHtml();
                    @endphp

                    <x-expandable-content
                        :content="$columnHtmlString"
                        max-height="6rem"
                    />
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

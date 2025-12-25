<?php

namespace App\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class AttachedFile extends Model
{
    use HasFactory, SoftDeletes, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $fillable = [
        'filename', 'hashedbasename', 'ledger_define_id',
        'ledger_id', 'column_id', 'mime', 'path', 'size', 'status',
        'contain_content', 'optimized', 'creator_id', 'modifier_id', 'original_file_path', 'original_mime_type',
        'vlm_markdown', 'vlm_structured_data', 'vlm_model', 'vlm_confidence', 'vlm_processing_time_ms', 'vlm_processed_at',
        'tika_processed_at', 'vlm_failed_at', 'ocr_processed_at', 'ocr_failed_at', 'processing_finalized_at', 'finalized_source',
    ];

    protected $casts = [
        'optimized' => 'boolean',
        'contain_content' => 'boolean',
        'status' => AttachedFileStatus::class,
        'vlm_structured_data' => 'array',
        'vlm_processed_at' => 'datetime',
        'tika_processed_at' => 'datetime',
        'vlm_failed_at' => 'datetime',
        'ocr_processed_at' => 'datetime',
        'ocr_failed_at' => 'datetime',
        'processing_finalized_at' => 'datetime',
    ];

    public function getOriginalFilenameAttribute(): ?string
    {
        if ($this->ledger && $this->ledger->content) {
            $ledgerContent = $this->ledger->content; // json_decode() を削除
            foreach ($ledgerContent as $columnData) {
                if (isset($columnData[$this->hashedbasename])) {
                    return $columnData[$this->hashedbasename];
                }
            }
        }

        return null;
    }

    /**
     * Tikaから抽出されたメタデータを取得
     */
    public function getTikaMetadataAttribute(): array
    {
        if (! $this->ledger || ! $this->ledger->content_attached) {
            return [];
        }

        $allAttached = $this->ledger->content_attached;

        // 1. まず column_id で直接引いてみる
        if (isset($allAttached[$this->column_id])) {
            $columnContent = $allAttached[$this->column_id];
            if (isset($columnContent[$this->hashedbasename])) {
                return $columnContent[$this->hashedbasename]['meta'] ?? [];
            }
        }

        // 2. 見つからない場合は全カラムを走査（AsColumnArrayJsonでキーが失われている可能性を考慮）
        // 備考: getOriginalFilenameAttribute() と同様のロジック
        foreach ($allAttached as $columnContent) {
            if (is_array($columnContent) && isset($columnContent[$this->hashedbasename])) {
                return $columnContent[$this->hashedbasename]['meta'] ?? [];
            }
        }

        return [];
    }

    /**
     * メタデータから作成日時を取得
     */
    public function getMetadataDateAttribute(): ?\Carbon\Carbon
    {
        $meta = $this->tika_metadata;

        // Tikaの一般的な日付キーをチェック
        $dateKey = collect(['dcterms:created', 'Creation-Date', 'created', 'date'])
            ->first(fn ($key) => isset($meta[$key]));

        if (! $dateKey) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($meta[$dateKey]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    public function ledgerChunks(): HasMany
    {
        return $this->hasMany(LedgerChunk::class);
    }

    /**
     * ファイルをアップロードしたユーザー
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * ファイルを最後に更新したユーザー
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    /**
     * ファイルに関連するアクティビティログ
     * (アップロード、ダウンロード、処理ステップ等)
     */
    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\Spatie\Activitylog\Models\Activity::class, 'subject')
            ->orderBy('created_at', 'desc');
    }

    /**
     * システム処理イベントのタイムラインを取得
     */
    public function getSystemTimelineAttribute(): \Illuminate\Support\Collection
    {
        $events = collect();

        // 1. アップロード
        if ($this->created_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-paper-clip',
                'color' => 'neutral',
                'title' => __('ledger.file_inspector.history.uploaded'),
                'description' => $this->creator?->name ?? 'System',
                'timestamp' => $this->created_at,
                'user' => $this->creator?->name ?? 'System',
            ]);
        }

        // 2. Tika処理完了
        if ($this->tika_processed_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-document-text',
                'color' => 'info',
                'title' => __('ledger.file_inspector.history.tika_extraction'),
                'description' => null,
                'timestamp' => $this->tika_processed_at,
                'user' => 'System',
            ]);
        }

        // 3. OCR処理完了
        if ($this->ocr_processed_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-eye',
                'color' => 'secondary',
                'title' => __('ledger.file_inspector.history.ocr_processing'),
                'description' => null,
                'timestamp' => $this->ocr_processed_at,
                'user' => 'System',
            ]);
        }

        // 4. VLM処理完了
        if ($this->vlm_processed_at) {
            $description = $this->vlm_confidence
                ? sprintf('%s: %.1f%%', __('ledger.file_inspector.info.confidence'), $this->vlm_confidence * 100)
                : null;
            if ($this->vlm_processing_time_ms) {
                $description .= sprintf(' | %.1f秒', $this->vlm_processing_time_ms / 1000);
            }

            $events->push([
                'type' => 'system',
                'icon' => 'o-cpu-chip',
                'color' => 'primary',
                'title' => __('ledger.file_inspector.history.vlm_analysis'),
                'description' => $description,
                'timestamp' => $this->vlm_processed_at,
                'user' => 'System',
            ]);
        }

        // 5. VLM処理失敗
        if ($this->vlm_failed_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-exclamation-circle',
                'color' => 'error',
                'title' => __('ledger.file_inspector.history.vlm_failed'),
                'description' => null,
                'timestamp' => $this->vlm_failed_at,
                'user' => 'System',
            ]);
        }

        // 6. OCR処理失敗
        if ($this->ocr_failed_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-exclamation-circle',
                'color' => 'error',
                'title' => __('ledger.file_inspector.history.ocr_failed'),
                'description' => null,
                'timestamp' => $this->ocr_failed_at,
                'user' => 'System',
            ]);
        }

        // 7. 処理確定
        if ($this->processing_finalized_at) {
            $events->push([
                'type' => 'system',
                'icon' => 'o-check-circle',
                'color' => 'success',
                'title' => __('ledger.file_inspector.history.processing_finalized'),
                'description' => $this->finalized_source ? "Source: {$this->finalized_source}" : null,
                'timestamp' => $this->processing_finalized_at,
                'user' => 'System',
            ]);
        }

        // 古い順にソート（時系列順：アップロード→処理→完了）
        return $events->sortBy('timestamp')->values();
    }

    /**
     * ユーザー操作のタイムラインを取得
     */
    public function getUserTimelineAttribute(): \Illuminate\Support\Collection
    {
        return $this->activities()
            ->with(['causer' => function ($query) {
                $query->select('id', 'name');
            }])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function ($activity) {
                // イベント名から表示情報を決定
                $icon = match ($activity->event) {
                    'downloaded', 'downloaded_original' => 'arrow-down-tray',
                    'viewed_thumbnail' => 'eye',
                    'downloaded_vlm' => 'cpu-chip',
                    'deleted' => 'trash',
                    default => 'clock',
                };

                $color = match ($activity->event) {
                    'downloaded', 'downloaded_original', 'downloaded_vlm' => 'success',
                    'viewed_thumbnail' => 'info',
                    'deleted' => 'error',
                    default => 'neutral',
                };

                return [
                    'type' => 'user',
                    'icon' => $icon,
                    'color' => $color,
                    'title' => \App\Helpers\ActivityLogFormatter::getOperationDescription($activity),
                    'description' => null,
                    'timestamp' => $activity->created_at,
                    'user' => $activity->causer?->name ?? __('ledger.activity.subject.unknown'),
                    'properties' => $activity->properties, // IP/UA等
                ];
            });
    }

    public function optimize()
    {
        //        $this->status = AttachedFileStatus::OPTIMIZING->value;
        // ファイルの最適化処理
        $this->status = AttachedFileStatus::OPTIMIZED->value;
    }

    public function extractMetadata()
    {
        //        $this->status = AttachedFileStatus::EXTRACTING->value;
        // メタデータ抽出処理
        $this->status = AttachedFileStatus::EXTRACTED_AND_SAVED->value;
    }

    public function retryProcessing(): void
    {
        $thumbnailFailed = ($this->status === AttachedFileStatus::THUMBNAIL_FAILED);

        // ステータスをPENDING_INITIAL_PROCESSINGにリセット
        $this->status = AttachedFileStatus::PENDING_INITIAL_PROCESSING;
        $this->save();

        // メインの処理ジョブを再ディスパッチ
        \App\Jobs\Ledger\ProcessAttachedFile::dispatch($this);

        // サムネイル生成に失敗していた場合、サムネイル生成ジョブも再ディスパッチ
        if ($thumbnailFailed) {
            Bus::dispatch(new GenerateThumbnail($this->id));
        }
    }

    public function hasVlmResult(): bool
    {
        return ! empty($this->vlm_markdown) && $this->status === AttachedFileStatus::COMPLETED;
    }

    public function isVlmProcessing(): bool
    {
        return $this->status === AttachedFileStatus::VLM_PROCESSING;
    }

    public function isVlmFailed(): bool
    {
        return $this->status === AttachedFileStatus::VLM_FAILED;
    }

    public function getVlmConfidenceFormattedAttribute(): ?string
    {
        if ($this->vlm_confidence === null) {
            return null;
        }

        return number_format($this->vlm_confidence * 100, 1).'%';
    }

    public function getPhysicalPath(): ?string
    {
        if (empty($this->path)) {
            return null;
        }

        if (Storage::disk('public')->exists($this->path)) {
            return Storage::disk('public')->path($this->path);
        }

        return null;
    }

    public function getProcessingStatusAttribute(): string
    {
        if ($this->processing_finalized_at) {
            return 'finalized';
        }

        $tikaComplete = (bool) $this->tika_processed_at;
        $vlmComplete = (bool) $this->vlm_processed_at;
        $vlmFailed = (bool) $this->vlm_failed_at;
        $ocrComplete = (bool) $this->ocr_processed_at;
        $ocrFailed = (bool) $this->ocr_failed_at;

        if ($tikaComplete && ($vlmComplete || $vlmFailed) && ($ocrComplete || $ocrFailed)) {
            return 'ready_for_finalization';
        }

        if ($tikaComplete) {
            return 'parallel_processing';
        }

        return 'initial_processing';
    }

    public function isReadyForFinalization(): bool
    {
        if ($this->processing_finalized_at) {
            return false;
        }

        if (! $this->tika_processed_at) {
            return false;
        }

        $vlmDone = $this->vlm_processed_at || $this->vlm_failed_at;
        $ocrDone = $this->ocr_processed_at || $this->ocr_failed_at;

        return $vlmDone && $ocrDone;
    }

    public function isVlmOrOcrTarget(): bool
    {
        if (! $this->mime) {
            return false;
        }

        return str_starts_with($this->mime, 'image/') ||
               $this->mime === 'application/pdf';
    }

    /**
     * UI表示用のステータスを取得
     *
     * Phase5並列処理において、最終化前は個別の失敗ステータス（OCR_FAILED等）を
     * 処理中ステータス（PARALLEL_PROCESSING）に変換する。
     * これにより、VLM処理が進行中の場合でもエラーアイコンではなく処理中アイコンが表示される。
     */
    public function getDisplayStatus(): AttachedFileStatus
    {
        // 最終化済みの場合は現在のステータスをそのまま返す
        if ($this->processing_finalized_at) {
            return $this->status;
        }

        // 最終化前で、VLM/OCR対象ファイルの場合
        if ($this->isVlmOrOcrTarget()) {
            // 個別処理の失敗ステータスは処理中に変換
            // （他の処理が成功する可能性があるため）
            if (in_array($this->status, [
                AttachedFileStatus::OCR_FAILED,
                AttachedFileStatus::VLM_FAILED,
                AttachedFileStatus::TIKA_FAILED,
            ])) {
                return AttachedFileStatus::PARALLEL_PROCESSING;
            }
        }

        // その他の場合は現在のステータスをそのまま返す
        return $this->status;
    }

    /**
     * テキスト抽出に失敗したか判定
     */
    public function hasExtractionError(): bool
    {
        // 最終化済みだがコンテンツが空
        if ($this->processing_finalized_at && ! $this->contain_content) {
            return true;
        }

        // VLM/OCR対象なのに両方失敗してコンテンツなし
        if ($this->isVlmOrOcrTarget() &&
            $this->vlm_failed_at &&
            $this->ocr_failed_at &&
            ! $this->contain_content) {
            return true;
        }

        return false;
    }

    /**
     * 一般ユーザーが再処理をリクエストできるか
     */
    public function canUserRequestRetry(): bool
    {
        return $this->hasExtractionError();
    }

    /**
     * 管理者が再処理を実行できるか
     */
    public function canAdminRetry(): bool
    {
        return $this->hasExtractionError() ||
            ($this->finalized_source === 'vlm' &&
                $this->vlm_confidence < 0.7) ||
            ($this->finalized_source === 'ocr' &&
                $this->vlm_failed_at);
    }

    /**
     * VLMによる高精度抽出が完了したか
     */
    public function isHighQualityExtraction(): bool
    {
        return $this->processing_finalized_at &&
            $this->finalized_source === 'vlm' &&
            $this->vlm_confidence >= 0.7;
    }

    /**
     * フォールバック処理で完了したか
     */
    public function isFallbackExtraction(): bool
    {
        return $this->processing_finalized_at &&
            in_array($this->finalized_source, ['ocr', 'tika']);
    }

    /**
     * プレビュー可能なテキストが存在するかを判定
     */
    public function hasPreviewableText(): bool
    {
        if (! $this->processing_finalized_at || ! $this->finalized_source) {
            return false;
        }

        // VLMの場合
        if ($this->finalized_source === 'vlm') {
            return ! empty($this->vlm_markdown);
        }

        // Eager Loadingチェック
        if (! $this->relationLoaded('ledger') || ! $this->ledger) {
            return false;
        }

        $contentAttached = $this->ledger->content_attached;
        $columnId = $this->column_id;
        $hashedbasename = $this->hashedbasename;

        // OCRの場合: .pdf付きと元の両方のキーをチェック
        if ($this->finalized_source === 'ocr') {
            $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME).'.pdf';

            return isset($contentAttached[$columnId][$pdfHashedbasename]['meta']['content'])
                || isset($contentAttached[$columnId][$hashedbasename]['meta']['content']);
        }

        // Tikaの場合: 元のキーのみをチェック
        if ($this->finalized_source === 'tika') {
            return isset($contentAttached[$columnId][$hashedbasename]['meta']['content']);
        }

        return false;
    }

    /**
     * プレビュー用のテキストを取得（アクセサ）
     */
    public function getPreviewableTextAttribute(): ?string
    {
        if (! $this->processing_finalized_at || ! $this->finalized_source) {
            return null;
        }

        return match ($this->finalized_source) {
            'vlm' => $this->vlm_markdown,
            'ocr', 'tika' => $this->getOcrTikaFormattedText($this->finalized_source),
            default => null,
        };
    }

    /**
     * OCRまたはTikaの抽出テキストを取得
     *
     * @param  string|null  $source  指定のソース ('ocr' または 'tika')。省略時は finalized_source を使用。
     */
    public function getOcrTikaFormattedText(?string $source = null): ?string
    {
        // content_attachedからテキスト取得（Eager Loading推奨）
        if (! $this->relationLoaded('ledger') || ! $this->ledger) {
            return null; // N+1防止のため、Eager Loading必須
        }

        $source = $source ?? $this->finalized_source;
        if (! in_array($source, ['ocr', 'tika'])) {
            return null;
        }

        $columnId = $this->column_id;
        $hashedbasename = $this->hashedbasename;

        // OCRの場合は .pdf キーもチェック
        if ($source === 'ocr') {
            $originalExt = pathinfo($hashedbasename, PATHINFO_EXTENSION);
            if ($originalExt !== 'pdf') {
                // 画像ファイル（.jpg → .pdf）の場合
                $pdfHashedbasename = pathinfo($hashedbasename, PATHINFO_FILENAME).'.pdf';
                $text = $this->ledger->content_attached[$columnId][$pdfHashedbasename]['meta']['content'] ?? null;
                if ($text) {
                    return "```\n{$text}\n```";
                }
            }
        }

        // 元のキーをチェック（Tika、またはPDFのOCR）
        $text = $this->ledger->content_attached[$columnId][$hashedbasename]['meta']['content'] ?? null;

        return $text ? "```\n{$text}\n```" : null;
    }

    public function getConfidenceBadgeInfo(): ?array
    {
        if (! $this->processing_finalized_at || ! $this->finalized_source) {
            return null;
        }

        return match ($this->finalized_source) {
            'vlm' => $this->getVlmBadgeInfo(),
            'ocr' => [
                'label' => __('ledger.vlm.source.ocr'),
                'color' => 'warning',
                'score' => null,
                'tooltip' => __('ledger.attached_file.badge.ocr_tooltip'),
            ],
            'tika' => [
                'label' => __('ledger.vlm.source.tika'),
                'color' => 'info',
                'score' => null,
                'tooltip' => __('ledger.attached_file.badge.tika_tooltip'),
            ],
            default => null,
        };
    }

    private function getVlmBadgeInfo(): array
    {
        $score = $this->vlm_confidence * 100;

        if ($score >= 70) {
            $color = 'success';
            $tooltip = __('ledger.attached_file.badge.vlm_high_quality');
        } elseif ($score >= 50) {
            $color = 'warning';
            $tooltip = __('ledger.attached_file.badge.vlm_medium_quality');
        } else {
            $color = 'error';
            $tooltip = __('ledger.attached_file.badge.vlm_low_quality');
        }

        return [
            'label' => __('ledger.vlm.source.vlm'),
            'color' => $color,
            'score' => number_format($score, 1).'%',
            'tooltip' => $tooltip,
        ];
    }

    /**
     * 処理履歴をタイムライン形式で取得
     *
     * @return array タイムラインステップの配列
     */
    public function getProcessingTimeline(): array
    {
        $timeline = [];

        // 1. アップロード
        $timeline[] = [
            'step' => 'upload',
            'label' => __('file.timeline.upload'),
            'timestamp' => $this->created_at,
            'status' => 'completed',
            'icon' => 'fa-upload',
            'color' => 'success',
            'user' => $this->creator,
            'duration_ms' => null,
            'details' => [
                'size' => $this->size,
                'mime' => $this->original_mime_type ?? $this->mime,
            ],
        ];

        // 2. Tika処理
        if ($this->tika_processed_at) {
            $timeline[] = [
                'step' => 'tika',
                'label' => __('file.timeline.tika'),
                'timestamp' => $this->tika_processed_at,
                'status' => 'completed',
                'icon' => 'fa-file-text',
                'color' => 'success',
                'user' => null, // システム処理
                'duration_ms' => $this->calculateProcessingDuration('tika'),
                'details' => null,
            ];
        }

        // 3. VLM処理
        if ($this->vlm_processed_at) {
            $timeline[] = [
                'step' => 'vlm',
                'label' => __('file.timeline.vlm'),
                'timestamp' => $this->vlm_processed_at,
                'status' => 'completed',
                'icon' => 'fa-robot',
                'color' => 'success',
                'user' => null,
                'duration_ms' => $this->vlm_processing_time_ms,
                'details' => [
                    'model' => $this->vlm_model,
                    'confidence' => $this->vlm_confidence,
                ],
            ];
        } elseif ($this->vlm_failed_at) {
            $timeline[] = [
                'step' => 'vlm',
                'label' => __('file.timeline.vlm'),
                'timestamp' => $this->vlm_failed_at,
                'status' => 'failed',
                'icon' => 'fa-exclamation-triangle',
                'color' => 'error',
                'user' => null,
                'duration_ms' => null,
                'details' => $this->getVlmErrorDetails(),
            ];
        }

        // 4. OCR処理
        if ($this->ocr_processed_at) {
            $timeline[] = [
                'step' => 'ocr',
                'label' => __('file.timeline.ocr'),
                'timestamp' => $this->ocr_processed_at,
                'status' => 'completed',
                'icon' => 'fa-text-width',
                'color' => 'success',
                'user' => null,
                'duration_ms' => $this->calculateProcessingDuration('ocr'),
                'details' => null,
            ];
        } elseif ($this->ocr_failed_at) {
            $timeline[] = [
                'step' => 'ocr',
                'label' => __('file.timeline.ocr'),
                'timestamp' => $this->ocr_failed_at,
                'status' => 'failed',
                'icon' => 'fa-exclamation-triangle',
                'color' => 'error',
                'user' => null,
                'duration_ms' => null,
                'details' => $this->getOcrErrorDetails(),
            ];
        }

        // 5. 最終化
        if ($this->processing_finalized_at) {
            $timeline[] = [
                'step' => 'finalization',
                'label' => __('file.timeline.finalization'),
                'timestamp' => $this->processing_finalized_at,
                'status' => 'completed',
                'icon' => 'fa-check-circle',
                'color' => 'success',
                'user' => null,
                'duration_ms' => $this->calculateProcessingDuration('finalization'),
                'details' => [
                    'selected_source' => $this->finalized_source,
                    'contain_content' => $this->contain_content,
                ],
            ];
        }

        // 6. Activity Logからのダウンロード履歴（最新5件）
        if ($this->relationLoaded('activities')) {
            $downloadActivities = $this->activities
                ->where('description', 'downloaded')
                ->take(5);

            foreach ($downloadActivities as $activity) {
                $timeline[] = [
                    'step' => 'download',
                    'label' => __('file.timeline.download'),
                    'timestamp' => $activity->created_at,
                    'status' => 'info',
                    'icon' => 'fa-download',
                    'color' => 'info',
                    'user' => $activity->causer,
                    'duration_ms' => null,
                    'details' => $activity->properties->toArray(),
                ];
            }
        }

        // タイムスタンプでソート（降順）
        usort($timeline, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $timeline;
    }

    /**
     * 処理時間を計算（ヘルパーメソッド）
     */
    /**
     * 各処理ステップの所要時間を計算（ミリ秒）
     */
    public function calculateProcessingDuration(string $step): ?int
    {
        // 簡易実装: 実際のジョブログから取得する場合はHorizonのAPIを使用
        return match ($step) {
            'tika' => $this->tika_processed_at ?
                $this->created_at->diffInMilliseconds($this->tika_processed_at) : null,
            'ocr' => $this->ocr_processed_at && $this->tika_processed_at ?
                $this->tika_processed_at->diffInMilliseconds($this->ocr_processed_at) : null,
            'vlm' => $this->vlm_processing_time_ms ? (int) $this->vlm_processing_time_ms : null,
            'finalization' => $this->processing_finalized_at && $this->tika_processed_at ?
                $this->tika_processed_at->diffInMilliseconds($this->processing_finalized_at) : null,
            default => null,
        };
    }

    /**
     * VLMエラー詳細を取得（ヘルパーメソッド）
     */
    private function getVlmErrorDetails(): ?array
    {
        // Activity Logから取得
        if ($this->relationLoaded('activities')) {
            $errorActivity = $this->activities
                ->where('description', 'vlm_failed')
                ->first();

            if ($errorActivity) {
                return $errorActivity->properties->toArray();
            }
        }

        return null;
    }

    /**
     * OCRエラー詳細を取得（ヘルパーメソッド）
     */
    private function getOcrErrorDetails(): ?array
    {
        // Activity Logから取得
        if ($this->relationLoaded('activities')) {
            $errorActivity = $this->activities
                ->where('description', 'ocr_failed')
                ->first();

            if ($errorActivity) {
                return $errorActivity->properties->toArray();
            }
        }

        return null;
    }
}

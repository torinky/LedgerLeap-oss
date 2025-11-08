<?php

namespace App\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
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
     * Get user-friendly status text
     */
    public function getUserFriendlyStatusAttribute(): string
    {
        // エラー状態のチェック（優先）
        if ($this->hasExtractionError()) {
            return 'テキストを抽出できませんでした';
        }

        // 最終化済み
        if ($this->processing_finalized_at) {
            return match ($this->finalized_source) {
                'vlm' => '高精度抽出完了',
                'ocr' => 'テキスト抽出完了',
                'tika' => '処理完了',
                default => '完了',
            };
        }

        // 処理中
        if ($this->tika_processed_at) {
            if ($this->isVlmOrOcrTarget()) {
                return '画像を読み取り中...';
            }

            return '処理中...';
        }

        return '待機中';
    }

    /**
     * Get confidence level badge
     */
    public function getConfidenceLevelAttribute(): ?string
    {
        if (! $this->processing_finalized_at) {
            return null;
        }

        return match ($this->finalized_source) {
            'vlm' => $this->vlm_confidence ?
                ($this->vlm_confidence >= 0.9 ? '高精度' :
                ($this->vlm_confidence >= 0.7 ? '標準精度' : '低精度')) :
                '高精度',
            'ocr' => '標準精度',
            'tika' => '基本抽出',
            default => null,
        };
    }

    /**
     * Check if extraction failed
     */
    public function hasExtractionError(): bool
    {
        // 最終化済みで、コンテンツが空の場合はエラー
        if ($this->processing_finalized_at && ! $this->contain_content) {
            return true;
        }

        // VLM/OCR対象なのに両方失敗した場合
        if ($this->isVlmOrOcrTarget() &&
            $this->vlm_failed_at &&
            $this->ocr_failed_at &&
            ! $this->contain_content) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can retry processing
     */
    public function canRetryProcessing(): bool
    {
        // エラー状態の場合は再処理可能
        if ($this->hasExtractionError()) {
            return true;
        }

        // 低精度の場合も再処理可能
        if ($this->finalized_source === 'vlm' &&
            $this->vlm_confidence &&
            $this->vlm_confidence < 0.7) {
            return true;
        }

        // OCRフォールバックの場合も再処理可能（VLMを試す価値がある）
        if ($this->finalized_source === 'ocr' && $this->vlm_failed_at) {
            return true;
        }

        return false;
    }

    /**
     * Get badge color class for status
     */
    public function getStatusBadgeColorAttribute(): string
    {
        if ($this->hasExtractionError()) {
            return 'badge-error';
        }

        if ($this->processing_finalized_at) {
            return match ($this->finalized_source) {
                'vlm' => 'badge-success',
                'ocr' => 'badge-info',
                'tika' => 'badge-success',
                default => 'badge-neutral',
            };
        }

        if ($this->tika_processed_at) {
            return 'badge-warning';
        }

        return 'badge-ghost';
    }
}

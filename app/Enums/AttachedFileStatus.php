<?php

namespace App\Enums;

enum AttachedFileStatus: string
{
    case PENDING_INITIAL_PROCESSING = 'pending_initial_processing';
    case INITIAL_PROCESSING = 'initial_processing';
    case PENDING_OCR = 'pending_ocr';
    case OCR_PROCESSING = 'ocr_processing';
    case COMPLETED = 'completed';
    case TIKA_FAILED = 'tika_failed';
    case OCR_FAILED = 'ocr_failed';
    case THUMBNAIL_FAILED = 'thumbnail_failed';
    case PROCESSING_FAILED = 'processing_failed';

    case VLM_PROCESSING = 'vlm_processing';
    case VLM_FAILED = 'vlm_failed';
    case PENDING_VLM = 'pending_vlm';

    // Phase5: 並列処理統合後の新しいステータス
    case PARALLEL_PROCESSING = 'parallel_processing';
    case READY_FOR_FINALIZATION = 'ready_for_finalization';
    case FINALIZED = 'finalized';

    // 既存のステータスも残しておくが、将来的には新しいステータスに統合することを検討
    case UPLOADED = 'uploaded';
    case OPTIMIZED = 'optimized';
    case OPTIMIZING = 'optimizing';
    case OPTIMIZE_FAILED = 'optimize_failed';
    case EXTRACTED_AND_SAVED = 'extracted_and_saved';
    case EXTRACTION_FAILED = 'extraction_failed';
    case EXTRACTING = 'extracting';

    public function icon(): string
    {
        return match ($this) {
            self::PENDING_INITIAL_PROCESSING, self::PENDING_OCR, self::PENDING_VLM => 'fa-solid fa-clock',
            self::INITIAL_PROCESSING, self::OCR_PROCESSING, self::VLM_PROCESSING, self::PARALLEL_PROCESSING => 'fa-solid fa-gear',
            self::READY_FOR_FINALIZATION => 'fa-solid fa-clock',
            self::FINALIZED, self::COMPLETED => 'fa-solid fa-circle-check',
            self::TIKA_FAILED, self::OCR_FAILED, self::THUMBNAIL_FAILED, self::PROCESSING_FAILED, self::VLM_FAILED => 'fa-solid fa-triangle-exclamation',
            // 既存のステータス
            self::UPLOADED => 'fa-solid fa-cloud-arrow-up',
            self::OPTIMIZED => 'fa-solid fa-circle-check',
            self::OPTIMIZING => 'fa-solid fa-gear',
            self::OPTIMIZE_FAILED => 'fa-solid fa-triangle-exclamation',
            self::EXTRACTED_AND_SAVED => 'fa-solid fa-circle-check',
            self::EXTRACTION_FAILED => 'fa-solid fa-triangle-exclamation',
            self::EXTRACTING => 'fa-solid fa-gear',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::PENDING_INITIAL_PROCESSING, self::PENDING_OCR, self::PENDING_VLM, self::READY_FOR_FINALIZATION => 'text-info',
            self::INITIAL_PROCESSING, self::OCR_PROCESSING, self::VLM_PROCESSING, self::PARALLEL_PROCESSING => 'text-warning animate-spin',
            self::FINALIZED, self::COMPLETED => 'text-success',
            self::TIKA_FAILED, self::OCR_FAILED, self::THUMBNAIL_FAILED, self::PROCESSING_FAILED, self::VLM_FAILED => 'text-error',
            // 既存のステータス
            self::UPLOADED => 'text-info',
            self::OPTIMIZED => 'text-success',
            self::OPTIMIZING => 'text-warning animate-spin',
            self::OPTIMIZE_FAILED => 'text-error',
            self::EXTRACTED_AND_SAVED => 'text-success',
            self::EXTRACTION_FAILED => 'text-error',
            self::EXTRACTING => 'text-warning animate-spin',
        };
    }

    public function tooltip(): string
    {
        return match ($this) {
            self::PENDING_INITIAL_PROCESSING => __('ledger.uploadedFile.status.pending_initial_processing'),
            self::INITIAL_PROCESSING => __('ledger.uploadedFile.status.initial_processing'),
            self::PENDING_OCR => __('ledger.uploadedFile.status.pending_ocr'),
            self::OCR_PROCESSING => __('ledger.uploadedFile.status.ocr_processing'),
            self::COMPLETED => __('ledger.uploadedFile.status.completed'),
            self::TIKA_FAILED => __('ledger.uploadedFile.status.tika_failed'),
            self::OCR_FAILED => __('ledger.uploadedFile.status.ocr_failed'),
            self::THUMBNAIL_FAILED => __('ledger.uploadedFile.status.thumbnail_failed'),
            self::PROCESSING_FAILED => __('ledger.uploadedFile.status.processing_failed'),
            self::VLM_PROCESSING => __('ledger.uploadedFile.status.vlm_processing'),
            self::VLM_FAILED => __('ledger.uploadedFile.status.vlm_failed'),
            self::PENDING_VLM => __('ledger.uploadedFile.status.pending_vlm'),
            self::PARALLEL_PROCESSING => __('ledger.uploadedFile.status.parallel_processing'),
            self::READY_FOR_FINALIZATION => __('ledger.uploadedFile.status.ready_for_finalization'),
            self::FINALIZED => __('ledger.uploadedFile.status.finalized'),
            // 既存のステータス
            self::UPLOADED => __('ledger.uploadedFile.status.uploaded'),
            self::OPTIMIZED => __('ledger.uploadedFile.status.optimized'),
            self::OPTIMIZING => __('ledger.uploadedFile.status.optimizing'),
            self::OPTIMIZE_FAILED => __('ledger.uploadedFile.status.optimize_failed'),
            self::EXTRACTED_AND_SAVED => __('ledger.uploadedFile.status.extracted_and_saved'),
            self::EXTRACTION_FAILED => __('ledger.uploadedFile.status.extraction_failed'),
            self::EXTRACTING => __('ledger.uploadedFile.status.extracting'),
        };
    }

    /**
     * ファイルの状態に応じた詳細なツールチップを生成
     */
    public function getDetailedTooltip(\App\Models\AttachedFile $file): string
    {
        return match ($this) {
            self::FINALIZED => $this->getFinalizedTooltip($file),
            self::INITIAL_PROCESSING => __('ledger.uploadedFile.status.detailed.initial_processing'),
            self::PARALLEL_PROCESSING => $this->getParallelProcessingTooltip($file),
            self::READY_FOR_FINALIZATION => __('ledger.uploadedFile.status.detailed.ready_for_finalization'),
            self::TIKA_FAILED => __('ledger.uploadedFile.status.detailed.tika_failed'),
            self::OCR_FAILED => __('ledger.uploadedFile.status.detailed.ocr_failed'),
            self::VLM_FAILED => __('ledger.uploadedFile.status.detailed.vlm_failed'),
            default => $this->tooltip(),
        };
    }

    /**
     * 最終化済みファイルのツールチップ
     */
    private function getFinalizedTooltip(\App\Models\AttachedFile $file): string
    {
        if ($file->hasExtractionError()) {
            return __('ledger.uploadedFile.status.detailed.extraction_error');
        }

        return match ($file->finalized_source) {
            'vlm' => $file->vlm_confidence >= 0.9
                ? __('ledger.uploadedFile.status.detailed.vlm_high_quality')
                : __('ledger.uploadedFile.status.detailed.vlm_completed'),
            'ocr' => __('ledger.uploadedFile.status.detailed.ocr_completed'),
            'tika' => __('ledger.uploadedFile.status.detailed.tika_completed'),
            default => __('ledger.uploadedFile.status.detailed.completed'),
        };
    }

    /**
     * 並列処理中のツールチップ
     */
    private function getParallelProcessingTooltip(\App\Models\AttachedFile $file): string
    {
        $parts = [];

        if (! $file->vlm_processed_at && ! $file->vlm_failed_at) {
            $parts[] = __('ledger.uploadedFile.status.detailed.analyzing_image');
        }
        if (! $file->ocr_processed_at && ! $file->ocr_failed_at) {
            $parts[] = __('ledger.uploadedFile.status.detailed.ocr_processing');
        }

        return empty($parts)
            ? __('ledger.uploadedFile.status.detailed.processing')
            : implode(__('ledger.uploadedFile.status.detailed.separator'), $parts);
    }
}

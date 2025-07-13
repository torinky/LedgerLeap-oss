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
            self::PENDING_INITIAL_PROCESSING, self::PENDING_OCR => 'o-clock',
            self::INITIAL_PROCESSING, self::OCR_PROCESSING => 'o-cog-6-tooth',
            self::COMPLETED => 'o-check-circle',
            self::TIKA_FAILED, self::OCR_FAILED => 'o-exclamation-triangle',
            // 既存のステータス
            self::UPLOADED => 'o-arrow-up-tray',
            self::OPTIMIZED => 'o-check-circle',
            self::OPTIMIZING => 'o-cog-6-tooth',
            self::OPTIMIZE_FAILED => 'o-exclamation-triangle',
            self::EXTRACTED_AND_SAVED => 'o-check-circle',
            self::EXTRACTION_FAILED => 'o-exclamation-triangle',
            self::EXTRACTING => 'o-cog-6-tooth',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::PENDING_INITIAL_PROCESSING, self::PENDING_OCR => 'text-info',
            self::INITIAL_PROCESSING, self::OCR_PROCESSING => 'text-warning animate-spin',
            self::COMPLETED => 'text-success',
            self::TIKA_FAILED, self::OCR_FAILED => 'text-error',
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
            self::PENDING_INITIAL_PROCESSING => __('file.status.pending_initial_processing'),
            self::INITIAL_PROCESSING => __('file.status.initial_processing'),
            self::PENDING_OCR => __('file.status.pending_ocr'),
            self::OCR_PROCESSING => __('file.status.ocr_processing'),
            self::COMPLETED => __('file.status.completed'),
            self::TIKA_FAILED => __('file.status.tika_failed'),
            self::OCR_FAILED => __('file.status.ocr_failed'),
            // 既存のステータス
            self::UPLOADED => __('file.status.uploaded'),
            self::OPTIMIZED => __('file.status.optimized'),
            self::OPTIMIZING => __('file.status.optimizing'),
            self::OPTIMIZE_FAILED => __('file.status.optimize_failed'),
            self::EXTRACTED_AND_SAVED => __('file.status.extracted_and_saved'),
            self::EXTRACTION_FAILED => __('file.status.extraction_failed'),
            self::EXTRACTING => __('file.status.extracting'),
        };
    }
}

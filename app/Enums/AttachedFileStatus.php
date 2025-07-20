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
            self::PENDING_INITIAL_PROCESSING, self::PENDING_OCR => 'fa-solid fa-clock',
            self::INITIAL_PROCESSING, self::OCR_PROCESSING => 'fa-solid fa-gear',
            self::COMPLETED => 'fa-solid fa-circle-check',
            self::TIKA_FAILED, self::OCR_FAILED => 'fa-solid fa-triangle-exclamation',
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
            self::PENDING_INITIAL_PROCESSING => __('ledger.uploadedFile.status.pending_initial_processing'),
            self::INITIAL_PROCESSING => __('ledger.uploadedFile.status.initial_processing'),
            self::PENDING_OCR => __('ledger.uploadedFile.status.pending_ocr'),
            self::OCR_PROCESSING => __('ledger.uploadedFile.status.ocr_processing'),
            self::COMPLETED => __('ledger.uploadedFile.status.completed'),
            self::TIKA_FAILED => __('ledger.uploadedFile.status.tika_failed'),
            self::OCR_FAILED => __('ledger.uploadedFile.status.ocr_failed'),
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
}

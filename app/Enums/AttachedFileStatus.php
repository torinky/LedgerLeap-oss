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
}

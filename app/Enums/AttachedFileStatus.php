<?php

namespace App\Enums;

enum AttachedFileStatus: string
{
    case UPLOADED = 'uploaded';
    case OPTIMIZED = 'optimized';
    case OPTIMIZING = 'optimizing';
    case OPTIMIZE_FAILED = 'optimize_failed';
    case EXTRACTED_AND_SAVED = 'extracted_and_saved';
    case EXTRACTION_FAILED = 'extraction_failed';
    case EXTRACTING = 'extracting';

}

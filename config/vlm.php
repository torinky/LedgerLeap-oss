<?php

return [
    'enabled' => (bool) env('VLM_ENABLED', false),

    'url' => env('VLM_URL', 'http://vlm:8000'),

    'max_file_size' => env('VLM_MAX_FILE_SIZE', 10 * 1024 * 1024), // 10MB

    'default_model' => env('VLM_DEFAULT_MODEL', 'PaddleOCR-VL-1.6'),

    'timeout' => env('VLM_TIMEOUT', 300),

    'retry' => [
        'times' => env('VLM_RETRY_TIMES', 2),
        'backoff' => env('VLM_RETRY_BACKOFF', 300),
    ],
];

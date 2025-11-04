<?php

return [
    // VLM機能の有効化
    'enabled' => (bool) env('VLM_ENABLED', false),
    
    // VLMコンテナURL
    'url' => env('VLM_URL', 'http://vlm:8000'),
    
    // 処理対象の最大ファイルサイズ（バイト）
    'max_file_size' => env('VLM_MAX_FILE_SIZE', 10 * 1024 * 1024), // 10MB
    
    // デフォルトモデル
    'default_model' => env('VLM_DEFAULT_MODEL', 'PaddleOCR-VL-0.9B'),
    
    // タイムアウト設定（秒）
    'timeout' => env('VLM_TIMEOUT', 300),
    
    // リトライ設定
    'retry' => [
        'times' => env('VLM_RETRY_TIMES', 2),
        'backoff' => env('VLM_RETRY_BACKOFF', 300), // 5分
    ],
];

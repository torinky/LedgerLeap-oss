<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval-Augmented Generation) Settings
    |--------------------------------------------------------------------------
    |
    | This file contains all the settings related to the RAG functionality,
    | including semantic search, embedding models, and performance tuning.
    |
    */

    // Feature Flag for Semantic Search
    'enabled' => env('RAG_ENABLED', true),

    // Service settings for the embedding Python container
    'embedding_service' => [
        'url' => env('EMBEDDING_SERVICE_URL', 'http://embedding:8000'),
        'timeout' => env('EMBEDDING_SERVICE_TIMEOUT', 60), // seconds
    ],

    // Model selection and configuration
    'model' => [
        // The currently active embedding model.
        // This value should match one of the keys in the 'available_models' array.
        'active' => env('RAG_MODEL', 'cl-nagoya/ruri-v3-310m'),

        // A list of available embedding models and their configurations.
        'available_models' => [
            // Recommended for ARM64 development environments
            'ruri-v3-310m' => [
                'name' => 'cl-nagoya/ruri-v3-310m',
                'dimension' => 768,
                'description' => 'Fast and lightweight Japanese model with excellent performance (recommended for ARM64 dev).',
                'prefix' => [
                    'query' => '検索クエリ: ',
                    'passage' => '検索文書: ',
                ],
            ],

            'ruri-v3-30m' => [
                'name' => 'cl-nagoya/ruri-v3-30m',
                'dimension' => 256,
                'description' => 'Fast and lightweight Japanese model with excellent performance (recommended for ARM64 dev).',
                'prefix' => [
                    'query' => '検索クエリ: ',
                    'passage' => '検索文書: ',
                ],
            ],

            // Multilingual models - Lightweight
            'multilingual-e5-small' => [
                'name' => 'intfloat/multilingual-e5-small',
                'dimension' => 384,
                'description' => 'Lightweight multilingual model with good performance.',
                'prefix' => [
                    'query' => '',
                    'passage' => '',
                ],
            ],
            'all-minilm-l6-v2' => [
                'name' => 'sentence-transformers/all-MiniLM-L6-v2',
                'dimension' => 384,
                'description' => 'Ultra-fast lightweight model (English-focused).',
                'prefix' => [
                    'query' => '',
                    'passage' => '',
                ],
            ],

            // Multilingual models - Balanced
            'multilingual-e5-base' => [
                'name' => 'intfloat/multilingual-e5-base',
                'dimension' => 768,
                'description' => 'Balanced multilingual model with high quality.',
                'prefix' => [
                    'query' => '',
                    'passage' => '',
                ],
            ],

            // Special purpose models
            'granite-embedding-107m' => [
                'name' => 'ibm/granite-embedding-107m-multilingual',
                'dimension' => 1024,
                'description' => 'Unique multilingual model that also supports code search.',
                'prefix' => [
                    'query' => '',
                    'passage' => '',
                ],
            ],

            // High-quality models (x86_64 recommended)
            'bge-m3' => [
                'name' => 'BAAI/bge-m3',
                'dimension' => 1024,
                'description' => 'High-quality multilingual model (slow on ARM64, use x86_64).',
                'prefix' => [
                    'query' => '',
                    'passage' => '',
                ],
            ],
        ],
    ],

    // Chunking process configuration
    'chunking' => [
        'size' => env('RAG_CHUNK_SIZE', 2000), // Target characters per chunk
        'overlap' => env('RAG_CHUNK_OVERLAP', 400), // Characters to overlap between chunks
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & ONNX Runtime Settings
    |--------------------------------------------------------------------------
    |
    | These settings are passed to the Python embedding service to tune
    | performance. They allow for dynamic adjustment of ONNX Runtime
    | session options without changing the Python code.
    |
    */
    'performance' => [
        // Batch size for encoding texts (affects throughput and memory usage)
        // Lower values = more stable but slower
        // Higher values = faster but may cause crashes on large models
        // Recommended: 1 for BGE-M3 on ARM64, 4-8 for smaller models or x86_64
        'batch_size' => env('RAG_BATCH_SIZE', 1),

        // PyTorch thread settings for CPU parallelism
        // Set to 0 to use all available CPU cores
        // Lower values may improve stability on constrained systems
        'num_threads' => env('RAG_NUM_THREADS', 0),

        // Number of threads for inter-operation parallelism
        // Controls parallelization of independent operations
        'num_interop_threads' => env('RAG_NUM_INTEROP_THREADS', 0),

        // Convert embeddings to numpy arrays (vs PyTorch tensors)
        // True = compatibility, False = slightly faster
        'convert_to_numpy' => env('RAG_CONVERT_TO_NUMPY', true),

        // Device to use for inference (cpu, cuda, mps)
        // cpu = CPU only, cuda = NVIDIA GPU, mps = Apple Silicon GPU
        'device' => env('RAG_DEVICE', 'cpu'),

        // Whether to use ONNX Runtime for inference.
        'use_onnx' => env('RAG_USE_ONNX', true),

        // ONNX graph optimization level.
        // Options: 'disabled', 'basic', 'extended', 'all'
        'graph_optimization_level' => env('RAG_ONNX_OPT_LEVEL', 'all'),

        // Number of threads to use for parallelizing the execution of operators.
        // Set to 0 to let ONNX Runtime decide (usually defaults to all available cores).
        'intra_op_num_threads' => env('RAG_INTRA_OP_THREADS', 0),

        // Number of threads to use for parallelizing the execution of the graph.
        'inter_op_num_threads' => env('RAG_INTER_OP_THREADS', 0),

        // Execution mode.
        // Options: 'sequential', 'parallel'
        'execution_mode' => env('RAG_EXECUTION_MODE', 'sequential'),

        // Enable dynamic quantization for CPU.
        // This can significantly speed up inference at the cost of a minor precision loss.
        'quantize' => env('RAG_QUANTIZE', false),
    ],

    // Logging channel for RAG related processes
    'log_channel' => 'rag',

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the search behavior, including similarity thresholds.
    |
    */
    'search' => [
        // Cosine distance threshold for hybrid search.
        // Only chunks with a distance LESS than this value will be considered.
        // A lower value means higher similarity (0.0 = identical, 1.0 = opposite).
        // This is only applied when a keyword is provided in the search.
        'similarity_threshold' => env('RAG_SIMILARITY_THRESHOLD', 0.2),
    ],
];

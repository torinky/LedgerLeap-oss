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
        'active' => env('RAG_MODEL', 'all-minilm-l6-v2'),

        // A list of available embedding models and their configurations.
        'available_models' => [
            'all-minilm-l6-v2' => [
                'name' => 'sentence-transformers/all-MiniLM-L6-v2',
                'dimension' => 384,
            ],
            'bge-m3' => [
                'name' => 'BAAI/bge-m3',
                'dimension' => 1024,
            ],
            'multilingual-e5-base' => [
                'name' => 'intfloat/multilingual-e5-base',
                'dimension' => 768,
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
];

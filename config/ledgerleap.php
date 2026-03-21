<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Links Configuration
    |--------------------------------------------------------------------------
    */
    'auto_links' => [
        /*
        | 仮想AutoLinkのベースURL設定
        |
        | テナント識別方式によって適切なホストを設定:
        | - パスベース: 'http://localhost' (推奨)
        | - サブドメイン: null (相対URLを使用)
        */
        'base_url' => env('AUTO_LINK_BASE_URL', 'http://localhost'),

        'link_types' => [
            'default' => [
                'icon' => 'o-link',
                'label_key' => 'auto_links.link_types.default', // 翻訳キー
            ],
            'external' => [
                'icon' => 'o-arrow-top-right-on-square',
                'label_key' => 'auto_links.link_types.external',
            ],
            'document' => [
                'icon' => 'o-document-text',
                'label_key' => 'auto_links.link_types.document',
            ],
            'ticket' => [
                'icon' => 'o-ticket',
                'label_key' => 'auto_links.link_types.ticket',
            ],
            // 他のタイプを追加可能
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Processing Configuration
    |--------------------------------------------------------------------------
    |
    | 添付ファイル処理に関する設定
    |
    */
    'processing_timeout_hours' => env('FILE_PROCESSING_TIMEOUT_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Scoring System Configuration
    |--------------------------------------------------------------------------
    |
    | ハイブリッド型情報価値評価システムの設定
    | Phase 1: 簡素化版（活動・新鮮度・重要度のみ）
    |
    */
    'scoring' => [
        /*
        | 活動スコア設定
        | 期間別カウント方式: 直近の活動を評価
        */
        'activity' => [
            'windows' => [
                ['days' => 7, 'multiplier' => 10],   // 直近7日間のイベント × 10点
                ['days' => 30, 'multiplier' => 3],   // 直近30日間のイベント × 3点
            ],
        ],

        /*
        | 複合スコアの重み付け
        | 合計が1.0になるように設定
        */
        'weights' => [
            'activity' => 0.40,      // 活動スコア: 今使われている情報を優先
            'freshness' => 0.30,     // 新鮮度スコア: 新しい情報を優先
            'importance' => 0.30,    // 重要度スコア: 承認待ち等を優先
            'relevance' => 0.00,     // 関連性スコア: Phase 3で有効化
            'popularity' => 0.00,    // 人気度スコア: Phase 5で有効化
        ],

        /*
        | バッチ処理設定
        */
        'batch' => [
            'chunk_size' => 100,     // 一度に処理するレコード数
            'schedule' => 'daily',   // 実行頻度（daily: 日次）
        ],

        /*
        | スコア計算の実行頻度
        |
        | 環境別推奨値:
        | - 開発/デモ: 'everyFiveMinutes' - リアルタイムに近い動作確認
        | - 本番（小〜中規模）: 'hourly' - 活発な環境
        | - 本番（通常）: 'daily' - 標準設定
        | - 本番（大規模）: 'weekly' - データ量が多い場合
        |
        | 注意: 'everyMinute' はデバッグ時のみ使用すること
        */
        'schedule_frequency' => env('SCORING_SCHEDULE_FREQUENCY', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | パフォーマンス測定機能の設定
    | FileInspectorコンポーネントのパフォーマンスメトリクスを収集します
    |
    */
    'performance' => [
        /*
        | パフォーマンス測定の有効化
        |
        | 環境別推奨値:
        | - 開発環境: true - パフォーマンス測定・改善のため
        | - ステージング: true - 本番環境前の検証のため
        | - 本番環境: false - オーバーヘッド削減のため（必要に応じてtrue）
        */
        'enabled' => env('PERFORMANCE_MONITORING_ENABLED', env('APP_ENV') === 'local'),

        /*
        | パフォーマンスログの出力先
        |
        | 'log': Laravel標準ログ（storage/logs/laravel-*.log）
        | 'json': JSON統計ファイル（storage/logs/performance_stats.json）
        | 'both': 両方に出力
        | 'none': ログ出力なし（コンソールのみ）
        */
        'log_destination' => env('PERFORMANCE_LOG_DESTINATION', 'both'),

        /*
        |--------------------------------------------------------------------------
        | 常時モニタ / 調査用メトリクス
        |--------------------------------------------------------------------------
        |
        | always_on_metrics: 回帰検知で常に追う軽量メトリクス
        | investigation_metrics: 調査時にだけ詳しく追う内部メトリクス
        | thresholds_ms: 回帰検知の警告閾値（ms）
        */
        'monitoring' => [
            'always_on_metrics' => [
                'ledger_records_render',
                'ledger_records_query_prep_ms',
                'ledger_records_query_paginate_ms',
                'normalize_ms',
                'ledger_init_overlay_hidden',
                'ledger_init_overlay_painted',
            ],

            'investigation_metrics' => [
                'prepare_folder_asset_ms',
                'display_ledger_defines_ms',
                'display_ledger_defines_query_ms',
                'display_ledger_defines_load_ms',
                'breadcrumbs_prepared_ms',
                'ledger_records_query_ms',
                'attachments_fetch_ms',
                'content_normalize_ms',
                'content_attached_normalize_ms',
                'search_hit_mark_ms',
                'current_user_permission_ms',
                'filtered_column_defines_ms',
                'score_stats_ms',
                'grouping_ms',
                'view_prepare_ms',
                'ledger_records_query_count_ms',
                'ledger_records_define_load_ms',
                'search_target_ledger_define_ids_ms',
            ],

            'thresholds_ms' => [
                'ledger_records_render' => 1000,
                'ledger_records_query_prep_ms' => 250,
                'ledger_records_query_paginate_ms' => 250,
                'normalize_ms' => 300,
                'ledger_init_overlay_hidden' => 300,
                'ledger_init_overlay_painted' => 350,
            ],

            'threshold_alert_channel' => env('PERFORMANCE_THRESHOLD_ALERT_CHANNEL', 'performance'),
        ],

        /*
        | パフォーマンスメトリクスの種類
        |
        | 測定する項目を選択（配列形式）
        | 'drawer_open': ドロワー開閉時間
        | 'tab_switch': タブ切り替え時間
        | 'search_keyword_update': キーワード検索更新時間（サーバー側）
        | 'search_render': 検索結果のレンダリング時間（フロントエンド側）
        | 'image_preview_load': 画像プレビュー読み込み時間
        */
        'metrics' => [
            'drawer_open' => env('PERFORMANCE_METRIC_DRAWER_OPEN', true),
            'tab_switch' => env('PERFORMANCE_METRIC_TAB_SWITCH', true),
            'search_keyword_update' => env('PERFORMANCE_METRIC_SEARCH', true),
            'search_render' => env('PERFORMANCE_METRIC_SEARCH', true),
            'image_preview_load' => env('PERFORMANCE_METRIC_IMAGE_PREVIEW', true),
            'ledger_diff_render' => env('PERFORMANCE_METRIC_LEDGER_DIFF', true),
            'ledger_load_more' => env('PERFORMANCE_METRIC_LEDGER_LOAD_MORE', true),
            'ledger_mount' => env('PERFORMANCE_METRIC_LEDGER_MOUNT', true),
            'ledger_toggle_selection' => env('PERFORMANCE_METRIC_LEDGER_TOGGLE', true),
        ],
    ],
];

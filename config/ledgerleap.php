<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Links Configuration
    |--------------------------------------------------------------------------
    */
    'auto_links' => [
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
];

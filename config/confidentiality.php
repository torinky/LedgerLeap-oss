<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 秘密区分レベル定義
    |--------------------------------------------------------------------------
    |
    | 各レベルの表示ラベルは翻訳キー文字列のみを定義し、
    | 表示時に __() を適用することで config:cache 実行時の
    | ロケール固定問題を回避します。
    |
    */

    'levels' => [
        'public' => [
            'label_key' => 'ledger.confidentiality.level.public',
            'color' => 'success',
        ],
        'internal' => [
            'label_key' => 'ledger.confidentiality.level.internal',
            'color' => 'info',
        ],
        'confidential' => [
            'label_key' => 'ledger.confidentiality.level.confidential',
            'color' => 'warning',
        ],
        'secret' => [
            'label_key' => 'ledger.confidentiality.level.secret',
            'color' => 'error',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | デフォルト設定
    |--------------------------------------------------------------------------
    |
    | 何も設定されていない場合のフォールバック値。
    | Folder の上位階層に設定がない場合や、
    | LedgerDefine で継承先がない場合に使用されます。
    |
    */

    'default_level' => 'public',

    /*
    |--------------------------------------------------------------------------
    | 継承設定
    |--------------------------------------------------------------------------
    |
    | Folder は上位階層の秘密区分を継承できる。
    | inherited フラグで継承/上書きを区別する。
    |
    */

    'inheritance' => [
        // 継承時に Folder 側で使用する識別子
        'inherited_key' => 'inherited',
        // 継承元がない場合のフォールバック
        'fallback_level' => 'public',
    ],

    /*
    |--------------------------------------------------------------------------
    | キャッシュ設定
    |--------------------------------------------------------------------------
    |
    | キャッシュキーには tenant ID を含める。
    | null の場合は 'global' でフォールバック。
    |
    */

    'cache' => [
        'prefix' => 'confidentiality',
        'tags' => ['confidentiality', 'tenant_access'],
        'ttl' => 3600, // 1時間
    ],

];

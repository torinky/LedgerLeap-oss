<?php

return [
    'confidentiality' => [
        'level' => [
            'label' => '秘密区分',
            'public' => '全体開示',
            'internal' => '社内限定',
            'confidential' => '社外秘',
            'secret' => '極秘',
        ],
        'scope' => [
            'label' => '公開範囲',
            'placeholder' => '組織・ロールを選択...',
        ],
        'stamp' => [
            'tooltip_unset' => '秘密区分が設定されていません',
            'edit_link' => '設定を変更',
        ],
        'tooltip' => [
            'source_label' => '設定元',
            'ledger_define' => '台帳定義「:name」',
            'folder' => 'フォルダ「:name」',
            'inherited' => '（継承）',
            // 1行表示用（ツールチップ簡潔版）
            'ledger_define_short' => '台帳定義「:name」',
            'folder_short' => 'フォルダ「:name」',
            'inherited_from' => '継承元：:name',
            'direct_from' => '設定元：:name',
            'scope_label' => '対象：:scopes',
        ],
    ],
];

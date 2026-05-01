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
        ],
    ],
];

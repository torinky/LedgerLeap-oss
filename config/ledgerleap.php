<?php

return [
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
];

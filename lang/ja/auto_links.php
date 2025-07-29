<?php

return [
    'fields' => [
        'template' => 'テンプレート',
        'label' => 'ラベル',
        'description' => '説明',
        'pattern' => '正規表現パターン',
        'url_template' => 'URLテンプレート',
        'priority' => '優先度',
        'is_enabled' => '有効',
        'open_in_new_tab' => '新しいタブで開く',
        'created_at' => '作成日時',
        'creator' => '作成者',
        'updated_at' => '更新日時',
        'modifier' => '更新者',
        'preview_text' => 'プレビューテキスト',
        'preview_output' => 'プレビュー出力',
    ],
    'templates' => [
        'redmine_ticket' => 'Redmineチケット',
        'gitlab_mr' => 'GitLabマージリクエスト',
        'jira_ticket' => 'Jiraチケット',
        'spec_id' => '仕様書ID',
    ],
    'helps' => [
        'url_template' => '正規表現のキャプチャグループを $1, $2 などの形式で埋め込めます。',
    ],
    'validations' => [
        'invalid_regex' => '無効な正規表現です。',
        'no_matches' => 'マッチする箇所がありません。',
    ],
    'labels' => [
        'generated_html' => '生成されたHTML',
    ],
];
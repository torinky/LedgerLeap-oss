<?php

return [
    'rollback' => [
        'button_label' => 'このバージョンに戻す',
        'default_comment' => 'Ver.:version からロールバック',
        'execute_button' => 'ロールバックを実行',
        'modal_title' => '内容のロールバック（復元）',
        'reason_hint' => 'なぜこのバージョンに戻すのか、理由を詳しく入力してください。',
        'reason_label' => 'ロールバックの理由 (5文字以上)',
        'reason_placeholder' => '例：誤ってデータを消去してしまったため、昨日の状態に復元します。',
        'source_info' => 'Ver.:version からロールバックしました',
        'status_label' => 'ロールバック実行',
        'step1_description' => '選択した過去のバージョンの内容に台帳を書き換えます。この操作により新しいバージョンが作成されます。',
        'success_message' => '台帳の内容を選択したバージョンに復元しました。さらに変更が必要な場合は「編集」ボタンから修正してください。',
        'target_record' => '復元対象のバージョン',
        'understand_risks' => '操作の影響を理解しました。内容をこのバージョンで上書きします。',
        'warning_description' => '現在の最新内容は失われません（履歴として残ります）が、台帳のメインデータは指定された過去時点の内容で即座に置き換えられます。',
        'warning_title' => '実行前の最終確認',
        'your_comment' => '入力された理由',
    ],
    'history_list' => '変更履歴リスト',
    'history_title' => '更新履歴',
    'history_end' => 'これ以上の履歴はありません。',
];

<?php

return [
    'folder' => [
        'collapse' => '折りたたむ',
        'containing' => '所属するフォルダ',
        'create' => 'フォルダーを新規作成',
        'created' => 'フォルダーが作成されました',
        'edit' => 'フォルダーを編集',
        'expand' => '展開する',
        'fix' => 'ツリー構造を修復する',
        'form' => [
            'header' => [
                'create' => 'フォルダを新規作成',
                'edit' => 'フォルダ編集',
                'edit_name' => 'フォルダ編集: :name',
            ],
            'label' => [
                'parent_id' => '親フォルダ',
                'title' => 'フォルダ名',
            ],
            'message' => [
                'confirm_delete_body' => '本当にこのフォルダ「:name」を削除してもよろしいですか？元に戻すことはできません。',
                'created_successfully' => 'フォルダを作成しました。',
                'created_successfully_name' => 'フォルダ「:name」を作成しました。',
                'delete_has_children' => '子フォルダが存在するため削除できません。先に子フォルダを削除または移動してください。',
                'delete_has_defines' => 'このフォルダには台帳定義が存在するため削除できません。',
                'deleted_successfully' => 'フォルダを削除しました。',
                'first_folder_is_root' => '最初のフォルダはルートフォルダとして作成されます。',
                'updated_successfully' => 'フォルダを更新しました。',
                'updated_successfully_name' => 'フォルダ「:name」を更新しました。',
            ],
            'modal_title' => [
                'confirm_delete' => 'フォルダ削除の確認',
            ],
            'option' => [
                'no_parent' => '（親フォルダなし - ルート）',
            ],
            'placeholder' => [
                'select_parent_or_null' => 'ルートフォルダにする場合は選択解除',
                'select_roles' => 'ロールを検索または選択...',
            ],
            'warning' => [
                'cannot_delete_if_children_exist' => '子フォルダや、このフォルダに属する台帳定義が存在する場合は削除できません。',
            ],
        ],
        'goto_ledger' => '同じ階層の台帳リストに移動',
        'ledger_count' => '台帳定義数',
        'manageable' => '管理できます',
        'not_allow_create' => 'フォルダーの作成権限がありません',
        'not_allow_edit' => 'フォルダーの編集権限がありません',
        'notification' => 'フォルダー通知',
        'opened_count' => '検索対象のフォルダ数',
        'parent' => '親フォルダ',
        'permission' => 'フォルダー権限',
        'readable' => '閲覧できます',
        'remove' => 'フォルダーを削除する',
        'remove_message' => 'このフォルダーを削除しようとしています',
        'root' => 'Top',
        'scoped' => '関係フォルダ',
        'settings' => 'フォルダー設定',
        'title' => 'フォルダー名',
        'will_remove_message' => 'このフォルダーに含まれる台帳／フォルダは直上のフォルダー階層に移動します',
        'writable' => '書き込み可能',
    ],
];

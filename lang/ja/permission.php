<?php

return [
    'title' => '権限',
    'description' => '説明',
    'descriptions' => [
        'receive_workflow_summary_email' => '担当している未処理のワークフロータスク件数を定期的にメールで受け取ります。',
        'receive_workflow_action_email' => '自分が関与するワークフローの進行状況（差し戻し、承認完了など）に関する通知をメールで受け取ります。',
        // 必要に応じて他の権限の説明も追加
    ],
    'direct_permissions' => '直接割り当てられた権限', // UserResource用
    'direct_permissions_help' => 'ユーザーに直接割り当てられた権限です。ロールから継承された権限はここには表示されません。', // UserResource用

    'role_enforced_tooltip' => 'この設定はあなたの役割によって有効化されており、変更できません。', // ツールチップ用
    'role_enforced_label' => '役割による設定', // 無効化理由表示用
    'no_settings_available' => '設定可能な通知項目がありません。', // 設定項目がない場合
    'settings_description' => 'ここでは、特定の通知をメールで受け取るかどうかを設定できます。役割（ロール）によって設定が強制されている場合、変更することはできません。', // 設定画面の説明文

    // --- グループ名の翻訳キー ---
    'group' => [
        'user' => 'ユーザー管理',
        'organization' => '組織管理',
        'role' => 'ロール管理',
        'permission' => '権限管理',
        'folder' => 'フォルダ管理',
        'folder_permission' => 'フォルダ権限設定',
        'ledger_define' => '台帳定義管理',
        'ledger' => '台帳操作',
        'workflow_notification' => 'ワークフロー通知',
        'activity_log' => 'アクティビティログ',
        'other' => 'その他',
        'general' => '一般',
        'management' => '管理',
        'workflow' => 'ワークフロー',
        'notification' => '通知',
        'access_control' => 'アクセス制御',
    ],

    // --- 権限名の翻訳キー ---
    'name' => [
        // --- 既存のキー (省略) ---
        'view_users' => 'ユーザーの閲覧',
        'create_users' => 'ユーザーの作成',
        'update_users' => 'ユーザーの更新',
        'delete_users' => 'ユーザーの削除',
        'manage_users' => 'ユーザーの管理',

        'view_organizations' => '組織の閲覧',
        'create_organizations' => '組織の作成',
        'update_organizations' => '組織の更新',
        'delete_organizations' => '組織の削除',
        'manage_organizations' => '組織の管理',

        'view_roles' => '役割の閲覧',
        'create_roles' => '役割の作成',
        'update_roles' => '役割の更新',
        'delete_roles' => '役割の削除',
        'restore_roles' => '役割の復元',
        'force_delete_roles' => '役割の完全削除',

        'view_folder_permissions' => 'フォルダー権限設定の閲覧', // 名称変更
        'create_folder_permissions' => 'フォルダー権限設定の作成', // 名称変更
        'update_folder_permissions' => 'フォルダー権限設定の更新', // 名称変更
        'delete_folder_permissions' => 'フォルダー権限設定の削除', // 名称変更

        'view_ledgers' => '台帳の閲覧',
        'create_ledgers' => '台帳の作成',
        'update_ledgers' => '台帳の更新',
        'delete_ledgers' => '台帳の削除',

        'view_ledger_defines' => '台帳定義の閲覧',
        'create_ledger_defines' => '台帳定義の作成',
        'update_ledger_defines' => '台帳定義の更新',
        'delete_ledger_defines' => '台帳定義の削除',
        'restore_ledger_defines' => '台帳定義の復元',
        'force_delete_ledger_defines' => '台帳定義の完全削除',

        'view_folders' => 'フォルダーの閲覧',
        'create_folders' => 'フォルダーの作成',
        'update_folders' => 'フォルダーの更新',
        'delete_folders' => 'フォルダーの削除',
        'restore_folders' => 'フォルダーの復元',
        'force_delete_folders' => 'フォルダーの完全削除',

        'notify' => '通知を受け取る（システム内）', // description を反映
        'view_permissions' => '権限の閲覧',
        'create_permissions' => '権限の作成',
        'update_permissions' => '権限の更新',
        'delete_permissions' => '権限の削除',
        'manage_permissions' => '権限の管理', // グループ化のため追加 (既存かも)

        'view_activity_logs' => 'アクティビティログの閲覧',


        // --- アクセス権限 ---
        'read' => '閲覧',
        'write' => '書き込み',
        'inspect' => '点検',
        'approve' => '承認',
        'admin' => '管理',
        // --- 通知設定 (別管理推奨だが、もしラベルが必要なら) ---
        'notify_on' => '通知ON',
        'notify_off' => '通知OFF',

        // --- 他の権限名 ---
        // ... (view_users など) ...
        'receive_workflow_summary_email' => 'ワークフロー集約メール受信',
        'receive_workflow_action_email' => 'ワークフロー個別メール受信',

        'manage_auto_links'=>'自動リンクの管理'
    ],
    // --- FolderRelationManager 用 ---
    'folder_permissions' => 'フォルダーアクセス権限',
    'current_permissions' => '設定中の権限', // テーブルカラム名変更
    'attach_folder_permissions' => 'フォルダーを選択して権限付与',
    'attach_folder_modal_heading' => 'フォルダー権限の一括付与',
    'attach_folder_target_label' => '対象フォルダー',
    'attach_folder_permission_label' => '付与するアクセス権限',
    'edit_folder_permissions' => '権限編集',
    'edit_folder_modal_folder_label' => 'フォルダー名',
    'edit_folder_modal_permission_label' => 'アクセス権限',
    'update_folder_permissions_success' => '権限を更新しました',
    'detach_folder_permissions' => '権限解除',
    'detach_folder_modal_heading' => 'フォルダから権限を解除',
    'detach_folder_modal_description' => 'このフォルダーに対する全てのアクセス権限をこのロールから解除しますか？',
    'detach_folder_permissions_success' => 'フォルダー権限を解除しました',
    'attach_folder_permissions_success' => '権限を一括付与しました',
    'edit_folder_permission_modal_heading'=>":folder の権限を変更します",
    'access_permissions' => "アクセス権限",
    'edit_permission' => "権限の編集",
    'detach_folder_permissions_modal_heading'=>':folder の権限を解除します',
    'detach_folder_permissions_modal_description'=>'このフォルダーに対する全てのアクセス権限をこのロールから解除しますか？',
    // ActivityLogFormatter などで使われる可能性のある汎用権限メッセージ
    'view_any_activity' => '全ての活動ログを閲覧',
    'view_activity_logs' => '活動ログを閲覧',
    'permissions'=>'権限',
    'no_specific_permissions'=>'権限なし',
];
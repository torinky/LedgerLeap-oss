<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ledger 関連
        NotificationType::firstOrCreate(['name' => 'ledger_created'], [
            'description' => 'activitylog.ledger_created',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_updated'], [
            'description' => 'activitylog.ledger_updated',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_deleted'], [
            'description' => 'activitylog.ledger_deleted',
            'model' => 'App\Models\Ledger',
            'route' => 'ledgerDefine.index',
            'folder_relation' => 'define.folder',
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // Folder 関連
        NotificationType::firstOrCreate(['name' => 'folder_created'], [
            'description' => 'activitylog.folder_created',
            'model' => 'App\Models\Folder',
            'route' => 'ledgersByFolderId',
            'folder_relation' => null, // Folder 自身が subject
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_updated'], [
            'description' => 'activitylog.folder_updated',
            'model' => 'App\Models\Folder',
            'route' => 'ledgersByFolderId',
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_deleted'], [
            'description' => 'activitylog.folder_deleted',
            'model' => 'App\Models\Folder',
            'route' => 'folder.index',
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // LedgerDefine 関連
        NotificationType::firstOrCreate(['name' => 'ledger_define_created'], [
            'description' => 'activitylog.ledger_define_created',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.show',
            'folder_relation' => 'folder',
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_updated'], [
            'description' => 'activitylog.ledger_define_updated',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.show',
            'folder_relation' => 'folder',
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_deleted'], [
            'description' => 'activitylog.ledger_define_deleted',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.index',
            'folder_relation' => 'folder',
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // User 関連
        NotificationType::firstOrCreate(['name' => 'user_created'], [
            'description' => 'activitylog.user_created',
            'model' => 'App\Models\User',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'created',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'user_updated'], [
            'description' => 'activitylog.user_updated',
            'model' => 'App\Models\User',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'user_deleted'], [
            'description' => 'activitylog.user_deleted',
            'model' => 'App\Models\User',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // Organization 関連
        NotificationType::firstOrCreate(['name' => 'organization_created'], [
            'description' => 'activitylog.organization_created',
            'model' => 'App\Models\Organization',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'created',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'organization_updated'], [
            'description' => 'activitylog.organization_updated',
            'model' => 'App\Models\Organization',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'organization_deleted'], [
            'description' => 'activitylog.organization_deleted',
            'model' => 'App\Models\Organization',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // Role 関連
        NotificationType::firstOrCreate(['name' => 'role_created'], [
            'description' => 'activitylog.role_created',
            'model' => 'App\Models\Role',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'created',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'role_updated'], [
            'description' => 'activitylog.role_updated',
            'model' => 'App\Models\Role',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'role_deleted'], [
            'description' => 'activitylog.role_deleted',
            'model' => 'App\Models\Role',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // Permission 関連
        NotificationType::firstOrCreate(['name' => 'permission_created'], [
            'description' => 'activitylog.permission_created',
            'model' => 'App\Models\Permission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'created',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'permission_updated'], [
            'description' => 'activitylog.permission_updated',
            'model' => 'App\Models\Permission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'permission_deleted'], [
            'description' => 'activitylog.permission_deleted',
            'model' => 'App\Models\Permission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // Login / Logout
        NotificationType::firstOrCreate(['name' => 'login'], [
            'description' => 'activitylog.login',
            'model' => 'App\Models\User',
            'route' => null,
            'folder_relation' => null,
            'event' => 'login',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'logout'], [
            'description' => 'activitylog.logout',
            'model' => 'App\Models\User',
            'route' => null,
            'folder_relation' => null,
            'event' => 'logout',
            'default_notify' => false,
            'enabled' => true,
        ]);
        // ユーザーと組織関連
        NotificationType::firstOrCreate(['name' => 'user_organization_attached'], [
            'description' => 'activitylog.user_organization_attached',
            'model' => 'App\Models\Organization',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'attached',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'user_organization_detached'], [
            'description' => 'activitylog.user_organization_detached',
            'model' => 'App\Models\Organization',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'detached',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // ユーザーとロール関連
        NotificationType::firstOrCreate(['name' => 'role_user_attached'], [
            'description' => 'activitylog.role_user_attached',
            'model' => 'App\Models\Role',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'attached',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'role_user_detached'], [
            'description' => 'activitylog.role_user_detached',
            'model' => 'App\Models\Role',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'detached',
            'default_notify' => false,
            'enabled' => true,
        ]);
        // ロールと権限関連
        NotificationType::firstOrCreate(['name' => 'role_permission_attached'], [
            'description' => 'activitylog.role_permission_attached',
            'model' => 'App\Models\Permission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'attached',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'role_permission_detached'], [
            'description' => 'activitylog.role_permission_detached',
            'model' => 'App\Models\Permission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => null,
            'event' => 'detached',
            'default_notify' => false,
            'enabled' => true,
        ]);

        // RoleFolderPermission 関連
        NotificationType::firstOrCreate(['name' => 'role_folder_permission_created'], [
            'description' => 'activitylog.role_folder_permission_created',
            'model' => 'App\Models\RoleFolderPermission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => 'folder',
            'event' => 'created',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'role_folder_permission_updated'], [
            'description' => 'activitylog.role_folder_permission_updated',
            'model' => 'App\Models\RoleFolderPermission',
            'route' => null, // 必要に応じて変更
            'folder_relation' => 'folder',
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        // --- ステップ6.4 追加: ワークフロー関連通知タイプ ---
        NotificationType::firstOrCreate(['name' => 'workflow_summary'], [
            'description' => 'ledger.notification_types_description.workflow_summary', // 説明用翻訳キー
            'model' => null, // 特定モデルに限定しない
            'route' => 'notifications.index', // 通知一覧へ (タスクタブを開くパラメータは別途)
            'folder_relation' => null, // フォルダに依存しない
            'event' => 'workflow_summary', // 固有イベント名
            'default_notify' => true, // 担当者はデフォルトで受け取る
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'status_returned_to_draft'], [
            'description' => 'ledger.notification_types_description.status_returned_to_draft',
            'model' => 'App\Models\Ledger', // 対象は Ledger
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder', // Ledger のフォルダ
            'event' => 'returned_to_draft', // 固有イベント名
            'default_notify' => true, // 申請者はデフォルトで受け取る
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'approved'], [
            'description' => 'ledger.notification_types_description.approved',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'approved',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'inspection_completed'], [
            'description' => 'ledger.notification_types_description.inspection_completed',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'inspection_completed',
            'default_notify' => false, // オプションなのでデフォルト OFF 推奨
            'enabled' => true,
        ]);
        // --- (任意) 担当者向け個別依頼通知 ---
        NotificationType::firstOrCreate(['name' => 'inspection_requested'], [
            'description' => 'ledger.notification_types_description.inspection_requested',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show', // または承認待ちリスト
            'folder_relation' => 'define.folder',
            'event' => 'inspection_requested',
            'default_notify' => false, // 集約通知を主とするため OFF 推奨
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'approval_requested'], [
            'description' => 'ledger.notification_types_description.approval_requested',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show', // または承認待ちリスト
            'folder_relation' => 'define.folder',
            'event' => 'approval_requested',
            'default_notify' => false, // 集約通知を主とするため OFF 推奨
            'enabled' => true,
        ]);


    }
}

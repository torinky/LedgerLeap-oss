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
            'description' => '台帳が作成されたときに通知します。',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_updated'], [
            'description' => '台帳が更新されたときに通知します。',
            'model' => 'App\Models\Ledger',
            'route' => 'ledger.show',
            'folder_relation' => 'define.folder',
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_deleted'], [
            'description' => '台帳が削除されたときに通知します。',
            'model' => 'App\Models\Ledger',
            'route' => 'ledgerDefine.index',
            'folder_relation' => 'define.folder',
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // Folder 関連
        NotificationType::firstOrCreate(['name' => 'folder_created'], [
            'description' => 'フォルダーが作成されたときに通知します。',
            'model' => 'App\Models\Folder',
            'route' => 'folder.show',
            'folder_relation' => null, // Folder 自身が subject
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_updated'], [
            'description' => 'フォルダーが更新されたときに通知します。',
            'model' => 'App\Models\Folder',
            'route' => 'folder.show',
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_deleted'], [
            'description' => 'フォルダーが削除されたときに通知します。',
            'model' => 'App\Models\Folder',
            'route' => 'folder.index',
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // LedgerDefine 関連
        NotificationType::firstOrCreate(['name' => 'ledger_define_created'], [
            'description' => '台帳定義が作成されたときに通知します。',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.show',
            'folder_relation' => 'folder',
            'event' => 'created',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_updated'], [
            'description' => '台帳定義が更新されたときに通知します。',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.show',
            'folder_relation' => 'folder',
            'event' => 'updated',
            'default_notify' => true,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_deleted'], [
            'description' => '台帳定義が削除されたときに通知します。',
            'model' => 'App\Models\LedgerDefine',
            'route' => 'ledgerDefine.index',
            'folder_relation' => 'folder',
            'event' => 'deleted',
            'default_notify' => true,
            'enabled' => true,
        ]);

        // User 関連 (例)
        NotificationType::firstOrCreate(['name' => 'user_created'], [
            'description' => 'ユーザーが作成されたときに通知します。',
            'model' => 'App\Models\User',
            'route' => 'user.show',
            'folder_relation' => null, // ユーザーはフォルダーに直接関連付けられない
            'event' => 'created',
            'default_notify' => false, // デフォルトでは OFF (必要に応じて変更)
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'user_updated'], [
            'description' => 'ユーザー情報が更新されたときに通知します。',
            'model' => 'App\Models\User',
            'route' => 'user.show',
            'folder_relation' => null,
            'event' => 'updated',
            'default_notify' => false,
            'enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'user_deleted'], [
            'description' => 'ユーザーが削除されたときに通知します。',
            'model' => 'App\Models\User',
            'route' => 'user.index',
            'folder_relation' => null,
            'event' => 'deleted',
            'default_notify' => false,
            'enabled' => true,
        ]);
    }
}

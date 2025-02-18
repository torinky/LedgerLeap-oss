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
        // 通知なし
        NotificationType::firstOrCreate(['name' => 'none'], [
            'description' => '通知を受け取りません。',
            'default_is_enabled' => false,
        ]);
        // Ledger 関連
        NotificationType::firstOrCreate(['name' => 'ledger_created'], [
            'description' => 'Ledger が作成されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_updated'], [
            'description' => 'Ledger が更新されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_deleted'], [
            'description' => 'Ledger が削除されたときに通知します。',
            'default_is_enabled' => true,
        ]);

        // Folder 関連
        NotificationType::firstOrCreate(['name' => 'folder_created'], [
            'description' => 'フォルダーが作成されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_updated'], [
            'description' => 'フォルダーが更新されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'folder_deleted'], [
            'description' => 'フォルダーが削除されたときに通知します。',
            'default_is_enabled' => true,
        ]);

        // LedgerDefine 関連
        NotificationType::firstOrCreate(['name' => 'ledger_define_created'], [
            'description' => '台帳定義が作成されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_updated'], [
            'description' => '台帳定義が更新されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate(['name' => 'ledger_define_deleted'], [
            'description' => '台帳定義が削除されたときに通知します。',
            'default_is_enabled' => true,
        ]);
    }
}

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
        // ... 他の通知タイプ ...

        NotificationType::firstOrCreate([
            'name' => 'ledger_updated',
            'description' => 'Ledgerが更新されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        // 以下を追加
        NotificationType::firstOrCreate([
            'name' => 'ledger_created',
            'description' => 'Ledgerが作成されたときに通知します。',
            'default_is_enabled' => true,
        ]);
        NotificationType::firstOrCreate([
            'name' => 'ledger_deleted',
            'description' => 'Ledgerが削除されたときに通知します。',
            'default_is_enabled' => true,
        ]);
    }
}

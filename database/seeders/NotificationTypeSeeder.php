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
    }
}

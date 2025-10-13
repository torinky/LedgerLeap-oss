<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo Complete Seeder
 *
 * マスタープラン Phase 1完全版の統合Seeder
 * DemoMinimalSeederとDemoPhase1ExtensionSeederを統合し、1回の実行で完了
 *
 * 実行方法:
 * php artisan db:seed --class=DemoCompleteSeeder
 *
 * または DatabaseSeeder.php から呼び出し:
 * $this->call(DemoCompleteSeeder::class);
 */
class DemoCompleteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Demo Complete Seeder (Phase 1 Full)...');
        $this->command->info('');

        // Step 0: 権限システムの初期化（重要！）
        $this->command->info('📦 Phase 0: Initializing permissions system...');
        $this->call(RolesAndPermissionsSeeder::class);
        $this->command->info('');

        // Step 1: 基盤データ（DemoMinimalSeederの内容）
        $this->command->info('📦 Phase 1: Creating base data (minimal)...');
        $this->call(DemoMinimalSeeder::class);
        $this->command->info('');

        // Step 2: 拡張データ（DemoPhase1ExtensionSeederの内容）
        $this->command->info('📦 Phase 2: Creating extended data...');
        $this->call(DemoPhase1ExtensionSeeder::class);
        $this->command->info('');

        $this->command->info('✅ Demo Complete Seeder finished successfully!');
        $this->command->info('');
        $this->displayUsage();
    }

    private function displayUsage(): void
    {
        $this->command->info('📚 Usage Guide:');
        $this->command->info('');
        $this->command->info('🔑 Login Credentials:');
        $this->command->info('   Super Admin:  superadmin@example.com / demo1234 (全権限)');
        $this->command->info('   Demo User:    demo@example.com  / demo1234');
        $this->command->info('   Admin User:   admin@example.com / demo1234');
        $this->command->info('   営業太郎:     sales1@example.com / demo1234');
        $this->command->info('   開発太郎:     dev1@example.com / demo1234');
        $this->command->info('   点検一郎:     inspector1@example.com / demo1234');
        $this->command->info('   承認一郎:     approver1@example.com / demo1234');
        $this->command->info('');
        $this->command->info('📊 Created Data:');
        $this->command->info('   Tenant: demo-tenant');
        $this->command->info('   Organizations: 3 (本社、営業部、技術部)');
        $this->command->info('   Users: 13+ (includes super admin + demo users)');
        $this->command->info('   Roles: 7+');
        $this->command->info('   Folders: 10 (hierarchical structure)');
        $this->command->info('   Ledger Defines: 4 (営業日報、経費申請、設備点検表、週報)');
        $this->command->info('   Ledgers: 80+ (with various workflow states)');
        $this->command->info('   Tags: 25+');
        $this->command->info('');
        $this->command->info('🧪 MCP Tools Testing:');
        $this->command->info('   All 11 MCP tools can be tested with this data');
        $this->command->info('   All 9 InputTypes are covered');
        $this->command->info('   All 5 WorkflowStatus states are included');
        $this->command->info('');
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchApiPoCTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RAGを無効化（Observerが tenancy を終了させる問題を回避）
        config(['rag.enabled' => false]);

        // マイグレーションを実行
        $this->artisan('migrate:fresh', ['--force' => true]);
    }

    /**
     * PoC: ベストプラクティスに従った実装
     */
    public function test_admin_can_search_all_ledgers_poc()
    {
        echo "\n=== PoC Test Start ===\n";

        // 1. テナント作成と初期化（ベストプラクティス）
        $tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid()]);
        $tenant->domains()->create(['domain' => 'test.localhost']);

        // 2. テナントを初期化
        tenancy()->initialize($tenant);
        echo "Tenant initialized: {$tenant->id}\n";
        echo 'Tenancy status after init: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";

        // 3. テナントDBをマイグレート
        $this->artisan('tenants:migrate', ['--tenants' => [$tenant->id]]);

        // マイグレート後のテナンシー状態を確認
        echo 'Tenancy status after migrate: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";
        if (! tenancy()->initialized) {
            echo "Re-initializing tenant after migration\n";
            tenancy()->initialize($tenant);
            echo 'Tenancy status after re-init: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";
        }

        // 4. URLジェネレータを設定（ベストプラクティス）
        $fqdn = 'test.localhost';
        config(['app.url' => "http://{$fqdn}"]);
        \URL::forceRootUrl(config('app.url'));

        // 5. 権限設定
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permission = Permission::create(['name' => 'view_ledgers', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);

        // 6. ユーザー作成
        $adminUser = User::factory()->create(['name' => 'Admin User']);
        $adminUser->assignRole($adminRole);
        echo "Admin user created: {$adminUser->id}\n";

        // 7. フォルダとLedgerを作成（tenancy()コンテキスト内で）
        $folder1 = Folder::create(['title' => 'Folder 1', 'creator_id' => $adminUser->id, 'modifier_id' => $adminUser->id]);
        $folder2 = Folder::create(['title' => 'Folder 2', 'creator_id' => $adminUser->id, 'modifier_id' => $adminUser->id]);
        $folder3 = Folder::create(['title' => 'Folder 3', 'creator_id' => $adminUser->id, 'modifier_id' => $adminUser->id]);

        // フォルダ権限
        RoleFolderPermission::create([
            'role_id' => $adminRole->id,
            'folder_id' => $folder1->id,
            'permission' => FolderPermissionType::ADMIN,
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
        ]);
        RoleFolderPermission::create([
            'role_id' => $adminRole->id,
            'folder_id' => $folder2->id,
            'permission' => FolderPermissionType::ADMIN,
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
        ]);
        RoleFolderPermission::create([
            'role_id' => $adminRole->id,
            'folder_id' => $folder3->id,
            'permission' => FolderPermissionType::ADMIN,
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
        ]);

        // LedgerDefine作成
        $define1 = LedgerDefine::factory()->create(['folder_id' => $folder1->id]);
        $define2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id]);
        $define3 = LedgerDefine::factory()->create(['folder_id' => $folder3->id]);

        // Ledger作成
        echo "\n=== Creating Ledgers ===\n";
        echo 'Tenancy before ledger creation: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";
        echo 'Current tenant: '.(tenancy()->tenant?->id ?? 'NULL')."\n";

        $columnId1 = $define1->column_define[0]->id;
        echo "About to create Ledger 1...\n";
        $ledger1 = Ledger::create([
            'ledger_define_id' => $define1->id,
            'content' => [$columnId1 => 'Test Ledger 1'],
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
            'composite_score' => 100,
        ]);
        echo "Ledger 1 created: ID={$ledger1->id}, tenant_id={$ledger1->tenant_id}\n";
        echo 'Tenancy after Ledger 1: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";

        echo "About to create Ledger 2...\n";
        echo 'Tenancy before Ledger 2: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";
        $columnId2 = $define2->column_define[0]->id;
        $ledger2 = Ledger::create([
            'ledger_define_id' => $define2->id,
            'content' => [$columnId2 => 'Test Ledger 2'],
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
            'composite_score' => 100,
        ]);
        echo "Ledger 2 created: ID={$ledger2->id}, tenant_id={$ledger2->tenant_id}\n";
        echo 'Tenancy after Ledger 2: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";

        $columnId3 = $define3->column_define[0]->id;
        $ledger3 = Ledger::create([
            'ledger_define_id' => $define3->id,
            'content' => [$columnId3 => 'Test Ledger 3'],
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
            'composite_score' => 100,
        ]);
        echo "Ledger 3 created: ID={$ledger3->id}, tenant_id={$ledger3->tenant_id}\n";

        echo "Created 3 ledgers\n";

        // 8. データベースの実際の状態を確認
        echo "\n=== Database State ===\n";
        $allLedgers = Ledger::withoutGlobalScopes()->get();
        echo "Total ledgers (no scopes): {$allLedgers->count()}\n";
        foreach ($allLedgers as $ledger) {
            echo "  Ledger ID: {$ledger->id}, tenant_id: {$ledger->tenant_id}\n";
        }

        $scopedLedgers = Ledger::all();
        echo "Ledgers with tenant scope: {$scopedLedgers->count()}\n";

        echo "Current tenant ID: {$tenant->id}\n";
        echo 'Tenancy initialized: '.(tenancy()->initialized ? 'YES' : 'NO')."\n";

        // 9. APIコール（HTTP_HOSTヘッダーを設定）
        echo "\n=== API Call ===\n";
        $adminUser->createToken('test');
        $response = $this->actingAs($adminUser, 'sanctum')
            ->get('/api/v1/search', ['HTTP_HOST' => $fqdn]);

        // 10. API呼び出し後のデータベース状態
        echo "\n=== After API Call ===\n";
        $ledgersAfter = Ledger::all();
        echo "Ledgers after API: {$ledgersAfter->count()}\n";
        echo 'Current tenant ID: '.(tenancy()->tenant?->id ?? 'NULL')."\n";

        // 11. レスポンス確認
        echo "\n=== Response ===\n";
        echo "Status: {$response->status()}\n";
        $data = $response->json('data', []);
        echo 'Data count: '.count($data)."\n";

//        dump($response->json());

        // アサーション
        $response->assertStatus(200);
        $this->assertEquals(3, count($data), 'Should return 3 ledgers');
    }
}

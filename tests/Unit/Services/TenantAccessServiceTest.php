<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantAccessService::class);
    }

    #[Test]
    public function it_returns_accessible_tenants_correctly_for_a_user_with_permissions(): void
    {
        // 準備 (Arrange)
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $tenantC = Tenant::factory()->create(); // このテナントには権限を与えない

        $folderA = Folder::factory()->for($tenantA)->create();
        $folderB = Folder::factory()->for($tenantB)->create();

        $role = Role::create(['name' => 'editor']);
        $user = User::factory()->create();

        // 中間テーブルに直接レコードを作成して権限を付与
        DB::table('role_folder_permissions')->insert([
            ['role_id' => $role->id, 'folder_id' => $folderA->id, 'permission' => 1, 'modifier_id' => $user->id],
            ['role_id' => $role->id, 'folder_id' => $folderB->id, 'permission' => 1, 'modifier_id' => $user->id],
        ]);

        $user->assignRole($role);

        // 実行 (Act)
        $accessibleTenants = $this->service->getAccessibleTenants($user);

        // 評価 (Assert)
        $this->assertCount(2, $accessibleTenants);
        $this->assertTrue($accessibleTenants->contains('id', $tenantA->id));
        $this->assertTrue($accessibleTenants->contains('id', $tenantB->id));
        $this->assertFalse($accessibleTenants->contains('id', $tenantC->id));
    }

    #[Test]
    public function it_returns_an_empty_collection_for_a_user_with_no_permissions(): void
    {
        // 準備
        $user = User::factory()->create();
        Tenant::factory()->create(); // テナントは存在するが、ユーザーに権限はない

        // 実行
        $accessibleTenants = $this->service->getAccessibleTenants($user);

        // 評価
        $this->assertCount(0, $accessibleTenants);
    }

    #[Test]
    public function it_uses_cache_on_the_second_call(): void
    {
        // 準備
        $tenant = Tenant::factory()->create();
        $folder = Folder::factory()->for($tenant)->create();
        $role = Role::create(['name' => 'viewer']);
        $user = User::factory()->create();

        DB::table('role_folder_permissions')->insert([
            ['role_id' => $role->id, 'folder_id' => $folder->id, 'permission' => 1, 'modifier_id' => $user->id],
        ]);
        
        $user->assignRole($role);

        // 実行と評価
        DB::enableQueryLog();

        // 1回目の呼び出し（クエリが実行される）
        $this->service->getAccessibleTenants($user);
        $queryCountAfterFirstCall = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $queryCountAfterFirstCall);

        DB::flushQueryLog();

        // 2回目の呼び出し（キャッシュが使われ、クエリは実行されない）
        $this->service->getAccessibleTenants($user);
        $queryCountAfterSecondCall = count(DB::getQueryLog());
        $this->assertEquals(0, $queryCountAfterSecondCall);

        DB::disableQueryLog();
    }
}

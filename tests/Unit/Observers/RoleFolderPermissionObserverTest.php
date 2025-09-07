<?php

namespace Tests\Unit\Observers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleFolderPermissionObserverTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $serviceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // TenantAccessServiceをモックし、サービスコンテナに束縛する
        $this->serviceMock = $this->spy(TenantAccessService::class);
        app()->instance(TenantAccessService::class, $this->serviceMock);
    }

    #[Test]
    public function it_clears_cache_when_permission_is_created(): void
    {
        // 準備 (Arrange)
        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor']);
        $user->assignRole($role);
        $tenant = Tenant::factory()->create();
        $folder = Folder::factory()->for($tenant)->create();

        // 実行 (Act)
        // createdイベントをトリガーする
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ,
            'modifier_id' => $user->id,
        ]);

        // 評価 (Assert)
        $this->serviceMock->shouldHaveReceived('clearCache')->with(Mockery::any())->once();
    }

    #[Test]
    public function it_clears_cache_when_permission_is_deleted(): void
    {
        // 準備 (Arrange)
        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor']);
        $user->assignRole($role);
        $tenant = Tenant::factory()->create();
        $folder = Folder::factory()->for($tenant)->create();
        $permission = RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ,
            'modifier_id' => $user->id,
        ]);

        // 実行 (Act)
        // deletedイベントをトリガーする
        $permission->delete();

        // 評価 (Assert)
        $this->serviceMock->shouldHaveReceived('clearCache')->with(Mockery::any())->once();
    }
}

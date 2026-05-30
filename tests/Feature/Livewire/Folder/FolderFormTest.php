<?php

namespace Tests\Feature\Livewire\Folder;

use App\Enums\FolderPermissionType;
use App\Livewire\Folder\FolderForm;
use App\Models\Folder;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FolderFormTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected Folder $rootFolder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テナントを作成（CI で複数テストが同ドメインを作らないようユニーク化）
        $this->tenant = $this->getTenant();

        // ユーザーを作成
        $this->user = User::factory()->create();

        // 必要な権限を作成
        Permission::findOrCreate('create_folders', 'web');
        Permission::findOrCreate('update_folders', 'web');

        // ユーザーに権限を付与したロールを割り当て
        $role = Role::findOrCreate('test-role', 'web');
        $role->givePermissionTo(['create_folders', 'update_folders']);
        $this->user->assignRole($role);

        // ルートフォルダを作成
        $this->rootFolder = Folder::make([
            'title' => 'Root Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->rootFolder->saveAsRoot();

        // ユーザーを認証
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_creates_a_new_folder_with_the_correct_tenant_id(): void
    {
        Livewire::test(FolderForm::class)
            ->set('parentId', $this->rootFolder->id)
            ->set('title', 'New Sub Folder')
            ->set('tenantId', $this->tenant->id)
            ->call('save');

        // データベースにフォルダが作成されたことを確認
        $this->assertDatabaseHas('folders', [
            'title' => 'New Sub Folder',
            'parent_id' => $this->rootFolder->id,
            'tenant_id' => $this->tenant->id, // tenant_idが正しく設定されていることを確認
        ]);
    }

    #[Test]
    public function it_updates_an_existing_folder(): void
    {
        // テスト用の既存フォルダを作成
        $existingFolder = Folder::make([
            'title' => 'Original Title',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $existingFolder->appendToNode($this->rootFolder)->save();

        // ★ ここから追加
        // ユーザーがこのフォルダを更新できるように権限を設定
        RoleFolderPermission::create([
            'role_id' => $this->user->roles->first()->id,
            'folder_id' => $existingFolder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $this->user->id,
        ]);

        // 権限キャッシュをクリア
        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);
        // ★ ここまで追加

        Livewire::test(FolderForm::class, ['folderId' => $existingFolder->id])
            ->set('title', 'Updated Title')
            ->set('tenantId', $this->tenant->id)
            ->call('save');

        // データベースのフォルダが更新されたことを確認
        $existingFolder->refresh();
        $this->assertEquals('Updated Title', $existingFolder->title);
        $this->assertEquals($this->tenant->id, $existingFolder->tenant_id);
    }

    #[Test]
    public function it_creates_a_folder_with_confidentiality_settings(): void
    {
        Cache::flush();

        $org = Organization::factory()->create();
        $role = Role::factory()->create();

        $this->assertNotNull(Role::find($role->id), 'Role should be findable by ID');

        Livewire::test(FolderForm::class)
            ->set('parentId', $this->rootFolder->id)
            ->set('title', 'Confidential Folder')
            ->set('tenantId', $this->tenant->id)
            ->set('confidentialityLevel', 'confidential')
            ->set('confidentialityScopes', ["org:{$org->id}", "role:{$role->id}"])
            ->call('save');

        $this->assertDatabaseHas('folders', [
            'title' => 'Confidential Folder',
            'confidentiality_level' => 'confidential',
        ]);

        $folder = Folder::where('title', 'Confidential Folder')->first();
        $this->assertEquals('confidential', $folder->confidentiality_level);
        $this->assertNotNull($folder->confidentiality_scopes);
        $this->assertArrayHasKey('org_ids', $folder->confidentiality_scopes);
        $this->assertArrayHasKey('role_ids', $folder->confidentiality_scopes);
        $this->assertCount(1, $folder->confidentiality_scopes['org_ids']);
        $this->assertCount(1, $folder->confidentiality_scopes['role_ids']);
        $this->assertEquals($org->id, $folder->confidentiality_scopes['org_ids'][0]['id']);
        $this->assertEquals($role->id, $folder->confidentiality_scopes['role_ids'][0]['id']);
    }

    #[Test]
    public function it_updates_confidentiality_settings_on_existing_folder(): void
    {
        Cache::flush();

        $existingFolder = Folder::make([
            'title' => 'Original Title',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $existingFolder->appendToNode($this->rootFolder)->save();

        RoleFolderPermission::create([
            'role_id' => $this->user->roles->first()->id,
            'folder_id' => $existingFolder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $this->user->id,
        ]);

        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        $org = Organization::factory()->create();

        Livewire::test(FolderForm::class, ['folderId' => $existingFolder->id])
            ->set('title', 'Updated Title')
            ->set('tenantId', $this->tenant->id)
            ->set('confidentialityLevel', 'secret')
            ->set('confidentialityScopes', ["org:{$org->id}"])
            ->call('save');

        $existingFolder->refresh();
        $this->assertEquals('secret', $existingFolder->confidentiality_level);
        $this->assertCount(1, $existingFolder->confidentiality_scopes['org_ids']);
        $this->assertEquals($org->id, $existingFolder->confidentiality_scopes['org_ids'][0]['id']);
    }

    #[Test]
    public function it_initializes_confidentiality_fields_from_existing_folder(): void
    {
        Cache::flush();

        $org = Organization::factory()->create();
        $existingFolder = Folder::make([
            'title' => 'Existing Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'confidentiality_level' => 'internal',
            'confidentiality_scopes' => [
                'org_ids' => [['id' => $org->id, 'name' => $org->name]],
                'role_ids' => [],
            ],
        ]);
        $existingFolder->appendToNode($this->rootFolder)->save();

        RoleFolderPermission::create([
            'role_id' => $this->user->roles->first()->id,
            'folder_id' => $existingFolder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $this->user->id,
        ]);

        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        $component = Livewire::test(FolderForm::class, ['folderId' => $existingFolder->id]);

        $this->assertEquals('internal', $component->get('confidentialityLevel'));
        $this->assertEquals(["org:{$org->id}"], $component->get('confidentialityScopes'));
    }
}

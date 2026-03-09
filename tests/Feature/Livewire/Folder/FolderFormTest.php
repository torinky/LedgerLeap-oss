<?php

namespace Tests\Feature\Livewire\Folder;

use App\Enums\FolderPermissionType;
use App\Livewire\Folder\FolderForm;
use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
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
}

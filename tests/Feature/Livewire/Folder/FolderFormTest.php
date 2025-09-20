<?php

namespace Tests\Feature\Livewire\Folder;

use App\Livewire\Folder\FolderForm;
use App\Models\Folder;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FolderFormTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Folder $rootFolder;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを作成
        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

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
        $this->rootFolder = Folder::create([
            'title' => 'Root Folder',
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // ユーザーを認証
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_creates_a_new_folder_with_the_correct_tenant_id(): void
    {
        Livewire::test(FolderForm::class)
            ->set('parentId', $this->rootFolder->id)
            ->set('title', 'New Sub Folder')
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
        $existingFolder = Folder::create([
            'title' => 'Original Title',
            'parent_id' => $this->rootFolder->id,
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        Livewire::test(FolderForm::class, ['folder' => $existingFolder])
            ->set('title', 'Updated Title')
            ->call('save');

        // データベースのフォルダが更新されたことを確認
        $this->assertDatabaseHas('folders', [
            'id' => $existingFolder->id,
            'title' => 'Updated Title',
            'tenant_id' => $this->tenant->id, // tenant_idが変更されていないことを確認
        ]);
    }
}

<?php

namespace Tests\Feature\Livewire\Common;

use App\Enums\FolderPermissionType;
use App\Livewire\Common\PermissionDisplay;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected User $user;

    protected Folder $folder;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->role = Role::firstOrCreate(['name' => 'TestRole_'.uniqid(), 'guard_name' => 'web']);
        $this->folder = Folder::factory()->create();
    }

    #[Test]
    public function renders_successfully_for_folder()
    {
        $this->actingAs($this->user);

        Livewire::test(PermissionDisplay::class, [
            'resourceId' => $this->folder->id,
            'resourceType' => 'Folder',
        ])->assertStatus(200);
    }

    #[Test]
    public function access_users_paginator_uses_permission_user_page_name()
    {
        // Arrange
        $this->actingAs($this->user);
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        // Act
        $component = Livewire::test(PermissionDisplay::class, [
            'resourceId' => $this->folder->id,
            'resourceType' => 'Folder',
        ]);

        // accessUsers プロパティ（ページネーター）の pageName を確認
        $paginator = $component->instance()->getAccessUsersProperty();
        $this->assertSame('permission_user_page', $paginator->getPageName());
    }

    #[Test]
    public function resets_page_when_search_user_query_is_updated()
    {
        // Arrange: 11件ユーザーを作成しページ2に遷移
        $this->actingAs($this->user);
        User::factory()->count(11)->create();

        $component = Livewire::withQueryParams(['permission_user_page' => 2])
            ->test(PermissionDisplay::class, [
                'resourceId' => $this->folder->id,
                'resourceType' => 'Folder',
            ]);

        // Act: 検索クエリを変更
        $component->set('searchUserQuery', 'test');

        // Assert: ページが1にリセットされる
        $paginator = $component->instance()->getAccessUsersProperty();
        $this->assertSame(1, $paginator->currentPage());
    }

    #[Test]
    public function resets_page_when_filter_by_role_id_is_updated()
    {
        $this->actingAs($this->user);
        User::factory()->count(11)->create();

        $component = Livewire::withQueryParams(['permission_user_page' => 2])
            ->test(PermissionDisplay::class, [
                'resourceId' => $this->folder->id,
                'resourceType' => 'Folder',
            ]);

        // Act: ロールフィルタを変更
        $component->set('filterByRoleId', $this->role->id);

        // Assert: ページが1にリセットされる
        $paginator = $component->instance()->getAccessUsersProperty();
        $this->assertSame(1, $paginator->currentPage());
    }

    #[Test]
    public function reset_filters_resets_page_to_one()
    {
        $this->actingAs($this->user);

        $component = Livewire::withQueryParams(['permission_user_page' => 2])
            ->test(PermissionDisplay::class, [
                'resourceId' => $this->folder->id,
                'resourceType' => 'Folder',
            ]);

        $component->call('resetFilters');

        $paginator = $component->instance()->getAccessUsersProperty();
        $this->assertSame(1, $paginator->currentPage());
    }

    #[Test]
    public function renders_successfully_for_ledger_define()
    {
        $this->actingAs($this->user);
        $define = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);

        Livewire::test(PermissionDisplay::class, [
            'resourceId' => $define->id,
            'resourceType' => 'LedgerDefine',
        ])->assertStatus(200);
    }

    #[Test]
    public function renders_successfully_for_ledger()
    {
        $this->actingAs($this->user);
        $define = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);

        Livewire::test(PermissionDisplay::class, [
            'resourceId' => $ledger->id,
            'resourceType' => 'Ledger',
        ])->assertStatus(200);
    }

    #[Test]
    public function returns_no_permission_view_for_unauthenticated_user()
    {
        Livewire::test(PermissionDisplay::class, [
            'resourceId' => $this->folder->id,
            'resourceType' => 'Folder',
        ])->assertSee(__('ledger.access_and_permissions.title'));
    }
}

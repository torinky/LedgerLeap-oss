<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TenantSwitcher;
use App\Models\Folder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\FolderPermissionType;
use App\Models\RoleFolderPermission;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantSwitcherTest extends TestCase
{
    use RefreshDatabase;
    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function component_is_not_visible_to_guests(): void
    {
        $tenant = Tenant::create(['id' => 'dummy']);
        $this->get(route('my-portal', ['tenant' => $tenant->id]))
            ->assertDontSeeLivewire('tenant-switcher');
    }

    #[Test]
    public function component_is_visible_to_authenticated_users(): void
    {
        $tenant = \App\Models\Tenant::create(['id' => 'test-tenant']);
        $user = \App\Models\User::factory()->create();
        $role = \App\Models\Role::factory()->create(['name' => 'test-role']);
        $user->assignRole($role);

        $tenant->run(function () use ($role, $user) {
            $parentFolder = \App\Models\Folder::factory()->create(['title' => 'Parent Folder', 'parent_id' => null]);
            \App\Models\RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $parentFolder->id,
                'permission' => \App\Enums\FolderPermissionType::READ->value,
                'modifier_id' => $user->id,
            ]);
        });

        $this->actingAs($user);
        tenancy()->initialize($tenant);

        $this->get(route('my-portal', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee(__('ledger.navigation.my_tenants'));
    }

    #[Test]
    public function it_shows_all_tenants_and_distinguishes_membership(): void
    {
        $user = \App\Models\User::factory()->create();
        $role = \App\Models\Role::factory()->create(['name' => 'test-role']);
        $user->assignRole($role);

        $tenantA = \App\Models\Tenant::create(['id' => 'tenant-a']);
        $tenantB = \App\Models\Tenant::create(['id' => 'tenant-b']);
        $tenantC = \App\Models\Tenant::create(['id' => 'tenant-c']);

        // tenantAとtenantBにアクセス権を付与
        $tenantA->run(function () use ($role, $user) {
            $folderA = \App\Models\Folder::factory()->create(['title' => 'Folder A', 'parent_id' => null]);
            \App\Models\RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $folderA->id,
                'permission' => \App\Enums\FolderPermissionType::READ->value,
                'modifier_id' => $user->id,
            ]);
        });
        $tenantB->run(function () use ($role, $user) {
            $folderB = \App\Models\Folder::factory()->create(['title' => 'Folder B', 'parent_id' => null]);
            \App\Models\RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $folderB->id,
                'permission' => \App\Enums\FolderPermissionType::READ->value,
                'modifier_id' => $user->id,
            ]);
        });

        $this->actingAs($user);
        tenancy()->initialize($tenantA);

        Livewire::test(TenantSwitcher::class)
            ->assertSee($tenantA->name)
            ->assertSee($tenantB->name)
            ->assertSee($tenantC->name)
            ->assertSeeHtml('<li class="disabled">')
            ->assertSeeHtmlInOrder([__('ledger.navigation.my_tenants'), $tenantA->name, $tenantB->name, __('ledger.navigation.other_tenants'), $tenantC->name]);
    }

    #[Test]
    public function it_shows_folder_hierarchy_for_member_tenants(): void
    {
        $user = \App\Models\User::factory()->create();
        $role = \App\Models\Role::factory()->create(['name' => 'test-role']);
        $user->assignRole($role);

        $tenant = \App\Models\Tenant::create(['id' => 'member-tenant']);

        $tenant->run(function () use ($role, $user) {
            $parentFolder = \App\Models\Folder::factory()->create(['title' => 'Parent Folder', 'parent_id' => null]);
            $childFolder = \App\Models\Folder::factory()->create(['title' => 'Child Folder', 'parent_id' => $parentFolder->id]);

            RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $parentFolder->id,
                'permission' => FolderPermissionType::READ->value,
                'modifier_id' => $user->id,
            ]);
        });

        $this->actingAs($user);
        tenancy()->initialize($tenant);

        Livewire::test(TenantSwitcher::class)
            ->assertSee('Parent Folder')
            ->assertSee('Child Folder');
    }

    #[Test]
    public function links_are_generated_correctly(): void
    {
        $user = \App\Models\User::factory()->create();
        $role = \App\Models\Role::factory()->create(['name' => 'test-role']);
        $user->assignRole($role);

        $tenant = \App\Models\Tenant::create(['id' => 'link-tenant']);

        $folder = null; // Initialize $folder outside the closure
        $tenant->run(function () use ($role, $user, & $folder) {
            $folder = \App\Models\Folder::factory()->create(['title' => 'Test Folder', 'parent_id' => null]);
            \App\Models\RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $folder->id,
                'permission' => FolderPermissionType::READ->value,
                'modifier_id' => $user->id,
            ]);
        });

        $this->actingAs($user);
        tenancy()->initialize($tenant);

        Livewire::test(TenantSwitcher::class)
            ->assertSee(route('my-portal', ['tenant' => $tenant->id]))
            ->assertSee(route('ledgersByFolderId', ['tenant' => $tenant->id, 'folderId' => $folder->id]));
    }
}

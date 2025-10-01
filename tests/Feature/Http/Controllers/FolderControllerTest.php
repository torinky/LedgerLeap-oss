<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FolderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private User $adminUser;

    private User $writerUser;

    private User $viewerUser;

    private Folder $writableFolder;

    private Folder $readableFolder;

    protected function setUp(): void
    {
        parent::setUp();

        // Define permissions
        Permission::findOrCreate('view_folders', 'web');
        Permission::findOrCreate('create_folders', 'web');
        Permission::findOrCreate('update_folders', 'web');
        Permission::findOrCreate('delete_folders', 'web');

        // Define roles
        $adminRole = Role::findOrCreate('admin', 'web')->givePermissionTo(['view_folders', 'create_folders', 'update_folders', 'delete_folders']);
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_folders', 'create_folders', 'update_folders']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo('view_folders');

        // Create users
        $this->adminUser = User::factory()->create()->assignRole($adminRole);
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);

        // Create folders and grant permissions within tenant context
        $this->tenant->run(function () use ($adminRole, $writerRole, $viewerRole) {
            $this->writableFolder = Folder::factory()->create(['title' => 'Writable Folder']);
            $this->readableFolder = Folder::factory()->create(['title' => 'Readable Folder']);

            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->writableFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->readableFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $writerRole->id, 'folder_id' => $this->writableFolder->id, 'permission' => FolderPermissionType::WRITE, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $viewerRole->id, 'folder_id' => $this->readableFolder->id, 'permission' => FolderPermissionType::READ, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        });
    }

    // --- Test Create Action ---

    public function test_admin_can_access_create_page()
    {
        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('folder.create'))
            ->assertOk();
    }

    public function test_writer_can_access_create_page()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('folder.create'))
            ->assertOk();
    }

    public function test_viewer_is_forbidden_to_access_create_page()
    {
        $this->actingAs($this->viewerUser)
            ->get($this->tenantRoute('folder.create'))
            ->assertForbidden();
    }

    // --- Test Edit Action ---

    public function test_admin_can_access_edit_page_for_any_folder()
    {
        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('folder.edit', ['folder' => $this->writableFolder->id]))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('folder.edit', ['folder' => $this->readableFolder->id]))
            ->assertOk();
    }

    public function test_writer_can_access_edit_page_for_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('folder.edit', ['folder' => $this->writableFolder->id]))
            ->assertOk();
    }

    public function test_writer_is_forbidden_to_access_edit_page_for_non_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('folder.edit', ['folder' => $this->readableFolder->id]))
            ->assertForbidden();
    }

    public function test_viewer_is_forbidden_to_access_edit_page()
    {
        $this->actingAs($this->viewerUser)
            ->get($this->tenantRoute('folder.edit', ['folder' => $this->writableFolder->id]))
            ->assertForbidden();
    }

    /**
     * Generate a tenant-aware route.
     */
    private function tenantRoute(string $name, array $parameters = []): string
    {
        return route($name, array_merge(['tenant' => tenant('id')], $parameters));
    }
}

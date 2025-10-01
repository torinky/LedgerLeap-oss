<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerDefineControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private User $adminUser;

    private User $writerUser;

    private User $viewerUser;

    private LedgerDefine $ledgerDefineInWritableFolder;

    private LedgerDefine $ledgerDefineInReadableFolder;

    private Folder $writableFolder;

    private Folder $readableFolder;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        Permission::findOrCreate('view_ledger_defines', 'web');
        Permission::findOrCreate('create_ledger_defines', 'web');
        Permission::findOrCreate('update_ledger_defines', 'web');
        Permission::findOrCreate('delete_ledger_defines', 'web');

        // Roles
        $adminRole = Role::findOrCreate('admin', 'web')->givePermissionTo(['view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines', 'delete_ledger_defines']);
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo('view_ledger_defines');

        // Users
        $this->adminUser = User::factory()->create()->assignRole($adminRole);
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);

        // Folders and Permissions within tenant context
        $this->tenant->run(function () use ($adminRole, $writerRole, $viewerRole) {
            $this->writableFolder = Folder::factory()->create(['title' => 'Writable Folder']);
            $this->readableFolder = Folder::factory()->create(['title' => 'Readable Folder']);

            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->writableFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->readableFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $writerRole->id, 'folder_id' => $this->writableFolder->id, 'permission' => FolderPermissionType::WRITE, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $viewerRole->id, 'folder_id' => $this->readableFolder->id, 'permission' => FolderPermissionType::READ, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        });

        // Ledger Defines
        $this->ledgerDefineInWritableFolder = LedgerDefine::factory()->create(['folder_id' => $this->writableFolder->id]);
        $this->ledgerDefineInReadableFolder = LedgerDefine::factory()->create(['folder_id' => $this->readableFolder->id]);
    }

    // --- Test Index Action (ledgerDefine.index) ---

    public function test_admin_can_view_ledger_define_list()
    {
        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('ledgerDefine.index'))
            ->assertOk();
    }

    public function test_writer_can_view_ledger_define_list()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('ledgerDefine.index'))
            ->assertOk();
    }

    public function test_viewer_can_view_ledger_define_list()
    {
        $this->actingAs($this->viewerUser)
            ->get($this->tenantRoute('ledgerDefine.index'))
            ->assertOk();
    }

    // --- Test Edit Action (ledgerDefine.edit) ---

    public function test_admin_can_access_edit_page_for_any_ledger_define()
    {
        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineInWritableFolder->id]))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get($this->tenantRoute('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineInReadableFolder->id]))
            ->assertOk();
    }

    public function test_writer_can_access_edit_page_for_ledger_define_in_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineInWritableFolder->id]))
            ->assertOk();
    }

    public function test_writer_is_forbidden_to_access_edit_page_for_ledger_define_in_non_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get($this->tenantRoute('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineInReadableFolder->id]))
            ->assertForbidden();
    }

    public function test_viewer_is_forbidden_to_access_edit_page()
    {
        $this->actingAs($this->viewerUser)
            ->get($this->tenantRoute('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineInWritableFolder->id]))
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

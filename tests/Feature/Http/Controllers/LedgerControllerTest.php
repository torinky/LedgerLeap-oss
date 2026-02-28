<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerControllerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private User $adminUser;

    private User $writerUser;

    private User $viewerUser;

    private LedgerDefine $ledgerDefine;

    private Folder $writeFolder;

    private Folder $readFolder;

    private Ledger $writeLedger;

    private Ledger $readLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // Ledger::factory()->create() が LedgerObserver 経由で RAGジョブを dispatch する。
        // Queue::fake() でジョブを実際には実行させず Embeddingコンテナへの接続を防ぐ。
        Queue::fake();

        // Define permissions based on the seeder
        Permission::findOrCreate('view_ledgers', 'web');
        Permission::findOrCreate('create_ledgers', 'web');
        Permission::findOrCreate('update_ledgers', 'web');
        Permission::findOrCreate('delete_ledgers', 'web');

        // Define roles
        $adminRole = Role::findOrCreate('admin', 'web')->givePermissionTo(['view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers']);
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledgers', 'create_ledgers', 'update_ledgers']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo('view_ledgers');

        // Create users
        $this->adminUser = User::factory()->create()->assignRole($adminRole);
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);

        // Create folders and grant permissions within tenant context
        $this->getTenant()->run(function () use ($adminRole, $writerRole, $viewerRole) {
            $this->writeFolder = Folder::factory()->create(['title' => 'Writable Folder']);
            $this->readFolder = Folder::factory()->create(['title' => 'Readable Folder']);

            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->writeFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->readFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $writerRole->id, 'folder_id' => $this->writeFolder->id, 'permission' => FolderPermissionType::WRITE, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            RoleFolderPermission::create(['role_id' => $viewerRole->id, 'folder_id' => $this->readFolder->id, 'permission' => FolderPermissionType::READ, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        });

        // Create ledger defines for each folder
        $writeLedgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->writeFolder->id]);
        $readLedgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->readFolder->id]);
        $this->ledgerDefine = $writeLedgerDefine; // For create/edit tests

        // Create ledgers associated with their respective defines
        $this->writeLedger = Ledger::factory()->create(['ledger_define_id' => $writeLedgerDefine->id]);
        $this->readLedger = Ledger::factory()->create(['ledger_define_id' => $readLedgerDefine->id]);
    }

    // --- Test Show Action ---

    public function test_admin_can_view_any_ledger()
    {
        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->writeLedger->id]))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->readLedger->id]))
            ->assertOk();
    }

    public function test_writer_can_view_ledger_in_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->writeLedger->id]))
            ->assertOk();
    }

    public function test_writer_is_forbidden_to_view_ledger_in_non_writable_folder()
    {
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->readLedger->id]))
            ->assertForbidden();
    }

    public function test_viewer_can_view_ledger_in_readable_folder()
    {
        $this->actingAs($this->viewerUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->readLedger->id]))
            ->assertOk();
    }

    public function test_viewer_is_forbidden_to_view_ledger_in_non_readable_folder()
    {
        $this->actingAs($this->viewerUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => $this->writeLedger->id]))
            ->assertForbidden();
    }

    public function test_returns_404_for_non_existent_ledger()
    {
        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.show', ['ledgerId' => 9999]))
            ->assertNotFound();
    }

    // --- Test Index Action ---

    public function test_admin_can_view_ledger_list()
    {
        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.index'))
            ->assertOk();
    }

    public function test_writer_can_view_ledger_list()
    {
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.index'))
            ->assertOk();
    }

    public function test_viewer_can_view_ledger_list()
    {
        $this->actingAs($this->viewerUser)
            ->get(tenant_route('ledger.index'))
            ->assertOk();
    }

    // --- Test Create Action ---

    public function test_admin_can_access_create_page()
    {
        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.create', ['ledgerDefineId' => $this->ledgerDefine->id]))
            ->assertOk();
    }

    public function test_writer_can_access_create_page()
    {
        // Note: The create page itself doesn't require a folder context,
        // but the subsequent store action will.
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.create', ['ledgerDefineId' => $this->ledgerDefine->id]))
            ->assertOk();
    }

    public function test_viewer_is_forbidden_to_access_create_page()
    {
        $this->actingAs($this->viewerUser)
            ->get(tenant_route('ledger.create', ['ledgerDefineId' => $this->ledgerDefine->id]))
            ->assertForbidden();
    }

    // --- Test Edit Action ---

    public function test_admin_can_access_edit_page_for_any_ledger()
    {
        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.edit', ['ledgerId' => $this->writeLedger->id]))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(tenant_route('ledger.edit', ['ledgerId' => $this->readLedger->id]))
            ->assertOk();
    }

    public function test_writer_can_access_edit_page_for_writable_ledger()
    {
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.edit', ['ledgerId' => $this->writeLedger->id]))
            ->assertOk();
    }

    public function test_writer_is_forbidden_to_access_edit_page_for_non_writable_ledger()
    {
        $this->actingAs($this->writerUser)
            ->get(tenant_route('ledger.edit', ['ledgerId' => $this->readLedger->id]))
            ->assertForbidden();
    }

    public function test_viewer_is_forbidden_to_access_edit_page()
    {
        $this->actingAs($this->viewerUser)
            ->get(tenant_route('ledger.edit', ['ledgerId' => $this->writeLedger->id]))
            ->assertForbidden();
    }
}

function tenant_route(string $name, array $parameters = []): string
{
    return route($name, array_merge(['tenant' => tenant('id')], $parameters));
}

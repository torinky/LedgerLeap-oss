<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Helpers\AttachedFilePathHelper;
use App\Livewire\Ledger\ModifyColumn;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Enums\FolderPermissionType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class CreateColumnFileCollisionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();
        $this->user = User::factory()->create();

        $this->actingAs($this->user);

        $role = Role::firstOrCreate(['name' => 'test-file-collision-role', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_ledgers', 'guard_name' => 'web']));
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'update_ledgers', 'guard_name' => 'web']));
        $this->user->assignRole($role);

        $fileColumn = new ColumnDefine((object) [
            'id' => 0,
            'name' => 'Attachment',
            'type' => 'files',
            'order' => 1,
            'required' => false,
            'unique' => false,
            'options' => [],
            'group' => 'Files',
            'file' => null,
            'sort_index' => null,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [$fileColumn],
        ]);

        if ($this->ledgerDefine->folder) {
            RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $this->ledgerDefine->folder_id,
                'permission' => FolderPermissionType::WRITE,
                'modifier_id' => $this->user->id,
            ]);
        }
    }

    #[Test]
    public function migrate_fresh_no_collision_when_reuploading_files(): void
    {
        Bus::fake();
        Storage::fake('public');

        tenancy()->initialize($this->tenant);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);

        $file1 = UploadedFile::fake()->image('report_a.jpg');
        $file2 = UploadedFile::fake()->image('report_b.jpg');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->set('content.0', [$file1, $file2])
            ->call('saveDirectly');

        $files = AttachedFile::where('ledger_id', $ledger->id)->get();
        $this->assertCount(2, $files);

        $basenames = $files->pluck('hashedbasename')->unique();
        $this->assertCount(2, $basenames, 'Each file should have a unique hashedbasename');
    }

    #[Test]
    public function same_filename_different_files_produce_different_hashes(): void
    {
        Bus::fake();
        Storage::fake('public');

        tenancy()->initialize($this->tenant);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);

        $fileA = UploadedFile::fake()->create('same_name.pdf', 1024, 'application/pdf');
        $fileB = UploadedFile::fake()->create('same_name.pdf', 2048, 'application/pdf');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->set('content.0', [$fileA, $fileB])
            ->call('saveDirectly');

        $files = AttachedFile::where('ledger_id', $ledger->id)->get();
        $this->assertCount(2, $files);

        $basenames = $files->pluck('hashedbasename')->unique();
        $this->assertCount(2, $basenames, 'Same-named files with different content must have different hashes');
    }

    #[Test]
    public function files_uploaded_through_modify_column_are_stored_correctly(): void
    {
        Bus::fake();
        Storage::fake('public');

        tenancy()->initialize($this->tenant);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);

        $file = UploadedFile::fake()->image('invoice.png');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->set('content.0', [$file])
            ->call('saveDirectly');

        $attachedFile = AttachedFile::where('ledger_id', $ledger->id)->first();
        $this->assertNotNull($attachedFile);
        $this->assertEquals('invoice.png', $attachedFile->filename);

        Storage::disk('public')->assertExists($attachedFile->path);

        $this->assertNotEmpty($attachedFile->hashedbasename);
        $this->assertStringEndsWith('.png', $attachedFile->hashedbasename);
        $this->assertGreaterThanOrEqual(44, strlen($attachedFile->hashedbasename), 'hashedbasename should be at least 40 hex + ext');
    }

    #[Test]
    public function multiple_ledger_defines_do_not_share_hashedbasenames(): void
    {
        Bus::fake();
        Storage::fake('public');

        tenancy()->initialize($this->tenant);

        $fileColumn = new ColumnDefine((object) [
            'id' => 0,
            'name' => 'Files',
            'type' => 'files',
            'order' => 1,
            'required' => false,
            'unique' => false,
            'options' => [],
        ]);

        $defineA = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [$fileColumn],
        ]);
        $defineB = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [$fileColumn],
        ]);

        $ledgerA = Ledger::factory()->create([
            'ledger_define_id' => $defineA->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);
        $ledgerB = Ledger::factory()->create([
            'ledger_define_id' => $defineB->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);

        $file = UploadedFile::fake()->image('cross_define.jpg');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledgerA->id])
            ->set('content.0', [$file])
            ->call('saveDirectly');

        $file2 = UploadedFile::fake()->image('cross_define.jpg');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledgerB->id])
            ->set('content.0', [$file2])
            ->call('saveDirectly');

        $allBasenames = AttachedFile::pluck('hashedbasename');
        $uniqueBasenames = $allBasenames->unique();
        $this->assertEquals(
            $allBasenames->count(),
            $uniqueBasenames->count(),
            'Files uploaded to different ledger defines must have unique hashedbasenames'
        );
    }

    #[Test]
    public function file_content_attached_populates_after_save(): void
    {
        Bus::fake();
        Storage::fake('public');

        tenancy()->initialize($this->tenant);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [0 => []],
        ]);

        $file = UploadedFile::fake()->image('content_test.jpg');

        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->set('content.0', [$file])
            ->call('saveDirectly');

        $ledger->refresh();
        $contentColumn = $ledger->content[0] ?? [];

        $this->assertNotEmpty($contentColumn, 'content[0] must be populated after file upload');
        $this->assertIsArray($contentColumn);

        foreach ($contentColumn as $hashedBasename => $originalName) {
            $this->assertNotEmpty($hashedBasename, 'Key must be a hashedbasename');
            $this->assertEquals('content_test.jpg', $originalName, 'Value must be original filename');
        }
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class CheckStorageConsistencyTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    private function tenantId(): string
    {
        return (string) tenant('id');
    }

    #[Test]
    public function reports_success_when_all_records_have_files(): void
    {
        Storage::fake('public');

        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $filePath = "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/test_file.pdf";
        Storage::disk('public')->put($filePath, 'test content');

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.pdf',
            'hashedbasename' => 'test_file.pdf',
            'mime' => 'application/pdf',
            'path' => $filePath,
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:check-storage-consistency')
            ->assertExitCode(0);
    }

    #[Test]
    public function detects_orphan_records_with_missing_files(): void
    {
        Storage::fake('public');

        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'missing.pdf',
            'hashedbasename' => 'missing_file.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/missing_file.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:check-storage-consistency')
            ->expectsOutputToContain('孤立レコード')
            ->assertExitCode(1);
    }

    #[Test]
    public function detects_orphan_files_without_db_records(): void
    {
        Storage::fake('public');

        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        Storage::disk('public')->put(
            "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/orphan_file.pdf",
            'orphan content'
        );

        Storage::disk('public')->put(
            "tenants/{$tid}/Ledger/thumbs/orphan_thumb.jpg",
            'orphan thumb'
        );

        $this->artisan('attached-files:check-storage-consistency')
            ->expectsOutputToContain('孤立ファイル')
            ->assertExitCode(1);
    }

    #[Test]
    public function clean_option_removes_orphan_files(): void
    {
        Storage::fake('public');

        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $orphanPath = "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/orphan_to_clean.pdf";
        Storage::disk('public')->put($orphanPath, 'orphan content');

        $this->assertTrue(Storage::disk('public')->exists($orphanPath));

        $this->artisan('attached-files:check-storage-consistency', ['--clean' => true])
            ->assertExitCode(1);

        $this->assertFalse(Storage::disk('public')->exists($orphanPath), 'Orphan file should be deleted');
    }

    #[Test]
    public function dry_run_does_not_delete_orphan_files(): void
    {
        Storage::fake('public');

        $tid = $this->tenantId();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $orphanPath = "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/keep_orphan.pdf";
        Storage::disk('public')->put($orphanPath, 'keep me');

        $this->assertTrue(Storage::disk('public')->exists($orphanPath));

        $this->artisan('attached-files:check-storage-consistency', [
            '--clean' => true,
            '--dry-run' => true,
        ])->assertExitCode(1);

        $this->assertTrue(Storage::disk('public')->exists($orphanPath), 'Orphan file should remain in dry-run');
    }
}

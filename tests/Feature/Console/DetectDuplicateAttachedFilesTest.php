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

class DetectDuplicateAttachedFilesTest extends TestCase
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
    public function no_duplicates_reports_success(): void
    {
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
            'filename' => 'unique1.pdf',
            'hashedbasename' => 'unique_hash_a.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/unique_hash_a.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'unique2.pdf',
            'hashedbasename' => 'unique_hash_b.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/unique_hash_b.pdf",
            'size' => 2000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:detect-duplicates')
            ->assertExitCode(0);
    }

    #[Test]
    public function detects_duplicate_hashedbasenames(): void
    {
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
            'filename' => 'file1.pdf',
            'hashedbasename' => 'duplicate_hash.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/duplicate_hash.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'file2.pdf',
            'hashedbasename' => 'duplicate_hash.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/duplicate_hash.pdf",
            'size' => 2000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:detect-duplicates')
            ->expectsOutputToContain('2 件重複')
            ->assertExitCode(0);
    }

    #[Test]
    public function fix_option_regenerates_hashedbasename_for_duplicates(): void
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
            "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/duplicate_fix.pdf",
            'test content'
        );

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'file1.pdf',
            'hashedbasename' => 'duplicate_fix.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/duplicate_fix.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'file2.pdf',
            'hashedbasename' => 'duplicate_fix.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/duplicate_fix.pdf",
            'size' => 2000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $originalCount = AttachedFile::where('hashedbasename', 'duplicate_fix.pdf')->count();
        $this->assertEquals(2, $originalCount);

        $this->artisan('attached-files:detect-duplicates', ['--fix' => true])
            ->assertExitCode(0);

        $afterFix = AttachedFile::where('hashedbasename', 'duplicate_fix.pdf')->count();
        $this->assertEquals(1, $afterFix);

        $allUnique = AttachedFile::select('hashedbasename')
            ->groupBy('hashedbasename')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->assertEquals(0, $allUnique);
    }

    #[Test]
    public function dry_run_does_not_modify_records(): void
    {
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
            'filename' => 'file1.pdf',
            'hashedbasename' => 'dryrun_dup.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/dryrun_dup.pdf",
            'size' => 1000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'file2.pdf',
            'hashedbasename' => 'dryrun_dup.pdf',
            'mime' => 'application/pdf',
            'path' => "tenants/{$tid}/Ledger/Attachments/{$ledgerDefine->id}/dryrun_dup.pdf",
            'size' => 2000,
            'status' => 'completed',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $this->artisan('attached-files:detect-duplicates', ['--dry-run' => true, '--fix' => true])
            ->assertExitCode(0);

        $duplicates = AttachedFile::where('hashedbasename', 'dryrun_dup.pdf')->count();
        $this->assertEquals(2, $duplicates);
    }
}

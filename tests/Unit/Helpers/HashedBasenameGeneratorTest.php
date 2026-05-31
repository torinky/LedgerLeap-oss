<?php

namespace Tests\Unit\Helpers;

use App\Helpers\HashedBasenameGenerator;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class HashedBasenameGeneratorTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = true;

    protected HashedBasenameGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->generator = new HashedBasenameGenerator;
    }

    #[Test]
    public function generate_produces_hash_with_correct_extension(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 1024);

        $result = $this->generator->generate($file);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertEquals(64 + 4, strlen($result));
    }

    #[Test]
    public function generate_produces_different_hashes_for_different_files(): void
    {
        $file1 = UploadedFile::fake()->create('doc_a.pdf', 1024);
        $file2 = UploadedFile::fake()->create('doc_b.pdf', 2048);

        $hash1 = $this->generator->generate($file1);
        $hash2 = $this->generator->generate($file2);

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function generate_produces_same_hash_for_same_file(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 1024);

        $hash1 = $this->generator->generate($file);
        $hash2 = $this->generator->generate($file);

        $this->assertEquals($hash1, $hash2, 'Same file should produce identical hash (deterministic by name+size+mtime)');
    }

    #[Test]
    public function generate_with_retry_handles_same_file_reupload(): void
    {
        $file = UploadedFile::fake()->create('dedup.pdf', 1024, 'application/pdf');

        $initialHash = $this->generator->generate($file);

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
            'filename' => 'dedup.pdf',
            'hashedbasename' => $initialHash,
            'mime' => 'application/pdf',
            'path' => "tenants/1/Ledger/Attachments/{$ledgerDefine->id}/{$initialHash}",
            'size' => 1024,
            'status' => 'uploaded',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $retryHash = $this->generator->generateWithRetry($file);

        $this->assertNotEquals($initialHash, $retryHash, 'Same file re-uploaded while DB has collision should produce unique hash');
        $this->assertStringEndsWith('.pdf', $retryHash);
    }

    #[Test]
    public function generate_raw_produces_valid_hash(): void
    {
        $result = $this->generator->generateRaw('testfile', 'pdf', 5000);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertEquals(64 + 4, strlen($result));
    }

    #[Test]
    public function generate_raw_produces_different_hashes_for_different_inputs(): void
    {
        $hash1 = $this->generator->generateRaw('file_a', 'pdf', 1000);
        $hash2 = $this->generator->generateRaw('file_b', 'pdf', 2000);

        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function generate_for_seeder_produces_valid_hash(): void
    {
        $result = $this->generator->generateForSeeder('bulk_word_1', 'docx', 2048);

        $this->assertStringEndsWith('.docx', $result);
        $this->assertEquals(64 + 5, strlen($result));
    }

    #[Test]
    public function generate_with_retry_retries_on_collision(): void
    {
        $file = UploadedFile::fake()->create('collision.pdf', 1024, 'application/pdf');

        $initialHash = $this->generator->generate($file);

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
            'filename' => 'collision.pdf',
            'hashedbasename' => $initialHash,
            'mime' => 'application/pdf',
            'path' => "tenants/1/Ledger/Attachments/{$ledgerDefine->id}/{$initialHash}",
            'size' => 1024,
            'status' => 'uploaded',
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $result = $this->generator->generateWithRetry($file);

        $this->assertNotEquals($initialHash, $result, 'Retry should produce different hash when collision exists');
        $this->assertStringEndsWith('.pdf', $result);
    }

    #[Test]
    public function generate_with_retry_returns_original_when_no_collision(): void
    {
        $file = UploadedFile::fake()->create('unique_test.pdf', 1024, 'application/pdf');

        $result = $this->generator->generateWithRetry($file);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertEquals(64 + 4, strlen($result));
    }
}

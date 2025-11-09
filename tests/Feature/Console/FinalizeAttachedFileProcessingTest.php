<?php

namespace Tests\Feature\Console;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class FinalizeAttachedFileProcessingTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Bus::fake();
    }

    #[Test]
    public function command_runs_successfully_with_no_files()
    {
        $this->artisan('ledger:finalize-processing')
            ->expectsOutput('No files ready for finalization.')
            ->assertExitCode(0);
    }

    #[Test]
    public function command_finalizes_files_ready_for_finalization()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $file = AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.jpg',
            'hashedbasename' => 'test.jpg',
            'mime' => 'image/jpeg',
            'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
            'size' => 1000,
            'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
            'contain_content' => false,
            'optimized' => false,
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_processed_at' => now()->subMinute(),
            'vlm_markdown' => '# Test VLM Result',
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // Act
        $this->artisan('ledger:finalize-processing')
            ->assertExitCode(0);

        // Assert
        $file->refresh();
        $this->assertNotNull($file->processing_finalized_at);
        $this->assertEquals('vlm', $file->finalized_source);
        $this->assertTrue($file->contain_content);

        // Check RAG job dispatched
        Bus::assertDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function command_selects_vlm_over_ocr()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'content_attached' => [
                0 => [], // Column 0 must exist for column 1 to have correct index
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'OCR extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.jpg',
            'hashedbasename' => 'test.jpg',
            'mime' => 'image/jpeg',
            'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
            'size' => 1000,
            'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
            'contain_content' => false,
            'optimized' => false,
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_processed_at' => now()->subMinute(),
            'vlm_markdown' => '# VLM Result - Superior',
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // Act
        $this->artisan('ledger:finalize-processing')
            ->assertExitCode(0);

        // Assert
        $file->refresh();
        $ledger->refresh();

        $this->assertEquals('vlm', $file->finalized_source);
        $this->assertStringContainsString('VLM Result', $ledger->content_attached[1]['test.jpg']['meta']['content']);
    }

    #[Test]
    public function command_falls_back_to_ocr_when_vlm_failed()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'content_attached' => [
                0 => [],
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'Tika extracted text', // Tika result
                        ],
                    ],
                    'test.pdf' => [
                        'meta' => [
                            'content' => 'OCR extracted text', // OCR result (converted to PDF)
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.jpg',
            'hashedbasename' => 'test.jpg', // Original filename before OCR conversion
            'mime' => 'image/jpeg',
            'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
            'size' => 1000,
            'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
            'contain_content' => false,
            'optimized' => false,
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_failed_at' => now()->subMinute(),
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // Act
        $this->artisan('ledger:finalize-processing')
            ->assertExitCode(0);

        // Assert
        $file->refresh();
        $this->assertEquals('ocr', $file->finalized_source);
    }

    #[Test]
    public function command_falls_back_to_tika_when_both_vlm_and_ocr_failed()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'content_attached' => [
                0 => [],
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'Tika extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.jpg',
            'hashedbasename' => 'test.jpg',
            'mime' => 'image/jpeg',
            'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
            'size' => 1000,
            'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
            'contain_content' => false,
            'optimized' => false,
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_failed_at' => now()->subMinute(),
            'ocr_failed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // Act
        $this->artisan('ledger:finalize-processing')
            ->assertExitCode(0);

        // Assert
        $file->refresh();
        $this->assertEquals('tika', $file->finalized_source);
    }

    #[Test]
    public function command_respects_timeout_parameter()
    {
        // Arrange - File with tika_processed_at within timeout (should NOT be finalized)
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        $file = AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'column_id' => 1,
            'filename' => 'test.jpg',
            'hashedbasename' => 'test.jpg', // Original filename before OCR conversion
            'mime' => 'image/jpeg',
            'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test.jpg",
            'size' => 1000,
            'status' => \App\Enums\AttachedFileStatus::PARALLEL_PROCESSING,
            'contain_content' => false,
            'optimized' => false,
            'tika_processed_at' => now()->subSeconds(30), // 30s ago (within 60s timeout)
            'vlm_processed_at' => null,
            'ocr_processed_at' => null,
            'vlm_failed_at' => null,
            'ocr_failed_at' => null,
            'processing_finalized_at' => null,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
        ]);

        // Act with 60s timeout (file is only 30s old, should not be finalized)
        $this->artisan('ledger:finalize-processing', ['--timeout' => 60])
            ->assertExitCode(0);

        // Assert
        $file->refresh();
        $this->assertNull($file->processing_finalized_at);
    }

    #[Test]
    public function command_respects_limit_parameter()
    {
        // Arrange - Create 3 files ready for finalization
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $user = \App\Models\User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'content_attached' => [
                0 => [],
                1 => [
                    'test0.jpg' => ['meta' => ['content' => 'Tika extracted text']],
                    'test1.jpg' => ['meta' => ['content' => 'Tika extracted text']],
                    'test2.jpg' => ['meta' => ['content' => 'Tika extracted text']],
                ],
            ],
        ]);

        for ($i = 0; $i < 3; $i++) {
            AttachedFile::create([
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledgerDefine->id,
                'column_id' => 1,
                'filename' => "test{$i}.jpg",
                'hashedbasename' => "test{$i}.jpg",
                'mime' => 'image/jpeg',
                'path' => "public/Ledger/Attachments/{$ledgerDefine->id}/test{$i}.jpg",
                'size' => 1000,
                'status' => \App\Enums\AttachedFileStatus::READY_FOR_FINALIZATION,
                'contain_content' => false,
            'optimized' => false,
                'tika_processed_at' => now()->subMinutes(2),
                'vlm_processed_at' => now()->subMinute(),
                'vlm_markdown' => '# Test',
                'ocr_processed_at' => now()->subMinute(),
                'processing_finalized_at' => null,
                'creator_id' => $user->id,
                'modifier_id' => $user->id,
            ]);
        }

        // Act with limit=2
        $this->artisan('ledger:finalize-processing', ['--limit' => 2])
            ->assertExitCode(0);

        // Assert - Only 2 files should be finalized (for this specific ledger)
        $finalized = AttachedFile::where('ledger_id', $ledger->id)
            ->whereNotNull('processing_finalized_at')
            ->count();
        $this->assertEquals(2, $finalized);
    }
}

<?php

namespace Tests\Feature\Console;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
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
        $ledger = Ledger::factory()->create();

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'hashedbasename' => 'test.jpg',
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_processed_at' => now()->subMinute(),
            'vlm_markdown' => '# Test VLM Result',
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
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
        $ledger = Ledger::factory()->create([
            'content_attached' => [
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'OCR extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'hashedbasename' => 'test.jpg',
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_processed_at' => now()->subMinute(),
            'vlm_markdown' => '# VLM Result - Superior',
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
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
        $ledger = Ledger::factory()->create([
            'content_attached' => [
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'OCR extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'hashedbasename' => 'test.jpg',
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_failed_at' => now()->subMinute(),
            'ocr_processed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
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
        $ledger = Ledger::factory()->create([
            'content_attached' => [
                1 => [
                    'test.jpg' => [
                        'meta' => [
                            'content' => 'Tika extracted text',
                        ],
                    ],
                ],
            ],
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'hashedbasename' => 'test.jpg',
            'tika_processed_at' => now()->subMinutes(2),
            'vlm_failed_at' => now()->subMinute(),
            'ocr_failed_at' => now()->subMinute(),
            'processing_finalized_at' => null,
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
        // Arrange - File with tika_processed_at beyond timeout
        $file = AttachedFile::factory()->create([
            'tika_processed_at' => now()->subSeconds(100), // Within 300s default timeout
            'vlm_processed_at' => null,
            'ocr_processed_at' => null,
            'processing_finalized_at' => null,
        ]);

        // Act with 60s timeout (file should not be finalized)
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
        $ledger = Ledger::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            AttachedFile::factory()->create([
                'ledger_id' => $ledger->id,
                'column_id' => 1,
                'hashedbasename' => "test{$i}.jpg",
                'tika_processed_at' => now()->subMinutes(2),
                'vlm_processed_at' => now()->subMinute(),
                'vlm_markdown' => '# Test',
                'ocr_processed_at' => now()->subMinute(),
                'processing_finalized_at' => null,
            ]);
        }

        // Act with limit=2
        $this->artisan('ledger:finalize-processing', ['--limit' => 2])
            ->assertExitCode(0);

        // Assert - Only 2 files should be finalized
        $finalized = AttachedFile::whereNotNull('processing_finalized_at')->count();
        $this->assertEquals(2, $finalized);
    }
}

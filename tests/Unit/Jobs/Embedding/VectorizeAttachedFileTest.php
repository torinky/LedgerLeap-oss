<?php

namespace Tests\Unit\Jobs\Embedding;

use App\Enums\AttachedFileStatus;
use App\Jobs\Embedding\VectorizeAttachedFile;
use App\Jobs\ProcessLedgerForRagJob;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class VectorizeAttachedFileTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Bus::fake();
    }

    #[Test]
    public function it_upgrades_from_tika_to_ocr()
    {
        // Arrange
        $file = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::FINALIZED_BY_TIKA,
            'tika_processed_at' => now(),
            'ocr_processed_at' => now(),
            'finalized_source' => 'tika',
            'processing_finalized_at' => now(),
        ]);

        // Act
        $job = new VectorizeAttachedFile($file->id, 'ocr');
        $job->handle();

        // Assert
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_OCR, $file->status);
        $this->assertEquals('ocr', $file->finalized_source);
        Bus::assertDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function it_upgrades_from_ocr_to_vlm()
    {
        // Arrange
        $file = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::FINALIZED_BY_OCR,
            'ocr_processed_at' => now(),
            'vlm_processed_at' => now(),
            'finalized_source' => 'ocr',
            'processing_finalized_at' => now(),
        ]);

        // Act
        $job = new VectorizeAttachedFile($file->id, 'vlm');
        $job->handle();

        // Assert
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_VLM, $file->status);
        $this->assertEquals('vlm', $file->finalized_source);
        Bus::assertDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function it_skips_downgrade_from_vlm_to_ocr()
    {
        // Arrange
        $file = AttachedFile::factory()->create([
            'status' => AttachedFileStatus::FINALIZED_BY_VLM,
            'vlm_processed_at' => now(),
            'finalized_source' => 'vlm',
            'processing_finalized_at' => now(),
        ]);

        // Act
        $job = new VectorizeAttachedFile($file->id, 'ocr');
        $job->handle();

        // Assert
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_VLM, $file->status);
        $this->assertEquals('vlm', $file->finalized_source);
        Bus::assertNotDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function it_finalizes_office_files_with_tika_only()
    {
        // Arrange - Word文書
        $file = AttachedFile::factory()->create([
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => AttachedFileStatus::INITIAL_PROCESSING,
            'tika_processed_at' => now(),
        ]);

        // Act - Tikaでファイナライズ
        $job = new VectorizeAttachedFile($file->id, 'tika');
        $job->handle();

        // Assert
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_TIKA, $file->status);
        Bus::assertDispatched(ProcessLedgerForRagJob::class);

        // OCRでアップグレードしようとしてもスキップされる
        Bus::fake();
        $job2 = new VectorizeAttachedFile($file->id, 'ocr');
        $job2->handle();

        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_TIKA, $file->status);
        Bus::assertNotDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function it_upgrades_image_files_progressively()
    {
        // Arrange - 画像ファイル
        $file = AttachedFile::factory()->create([
            'mime' => 'image/jpeg',
            'status' => AttachedFileStatus::INITIAL_PROCESSING,
            'tika_processed_at' => now(),
        ]);

        // Act & Assert - Tika → OCR → VLM の段階的アップグレード
        
        // Step 1: Tika
        $job1 = new VectorizeAttachedFile($file->id, 'tika');
        $job1->handle();
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_TIKA, $file->status);

        // Step 2: OCR (アップグレード)
        Bus::fake();
        $file->update(['ocr_processed_at' => now()]);
        $job2 = new VectorizeAttachedFile($file->id, 'ocr');
        $job2->handle();
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_OCR, $file->status);
        Bus::assertDispatched(ProcessLedgerForRagJob::class);

        // Step 3: VLM (最終アップグレード)
        Bus::fake();
        $file->update(['vlm_processed_at' => now()]);
        $job3 = new VectorizeAttachedFile($file->id, 'vlm');
        $job3->handle();
        $file->refresh();
        $this->assertEquals(AttachedFileStatus::FINALIZED_BY_VLM, $file->status);
        Bus::assertDispatched(ProcessLedgerForRagJob::class);
    }

    #[Test]
    public function it_handles_missing_file_gracefully()
    {
        // Arrange
        $nonExistentId = 99999;

        // Act
        $job = new VectorizeAttachedFile($nonExistentId, 'tika');
        $job->handle();

        // Assert - エラーにならず、何もディスパッチされない
        Bus::assertNotDispatched(ProcessLedgerForRagJob::class);
    }
}

<?php

namespace Tests\Unit\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFileTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_retries_processing_correctly()
    {
        Bus::fake(); // ジョブのディスパッチをフェイク

        $attachedFile = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::PROCESSING_FAILED, // 失敗状態のファイルを作成
        ]);
        $attachedFile->saveQuietly();

        $attachedFile->retryProcessing();

        // ステータスがリセットされたことを確認
        $this->assertEquals(AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);

        // ProcessAttachedFile ジョブがディスパッチされたことを確認
        Bus::assertDispatched(ProcessAttachedFile::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        // THUMBNAIL_FAILED ではないので GenerateThumbnail はディスパッチされない
        Bus::assertNotDispatched(GenerateThumbnail::class);
    }

    #[Test]
    public function it_retries_processing_and_dispatches_thumbnail_job_if_thumbnail_failed()
    {
        Bus::fake(); // ジョブのディスパッチをフェイク

        $attachedFile = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::THUMBNAIL_FAILED, // サムネイル失敗状態のファイルを作成
        ]);
        $attachedFile->saveQuietly();

        $attachedFile->retryProcessing();

        // ステータスがリセットされたことを確認
        $this->assertEquals(AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);

        // ProcessAttachedFile ジョブがディスパッチされたことを確認
        Bus::assertDispatched(ProcessAttachedFile::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        // GenerateThumbnail ジョブもディスパッチされたことを確認
        Bus::assertDispatched(GenerateThumbnail::class, function ($job) use ($attachedFile) {
            return $job->attachedFileId === $attachedFile->id;
        });
    }

    #[Test]
    public function has_vlm_result_returns_true_when_markdown_exists_and_status_is_completed()
    {
        $file = AttachedFile::factory()->make([
            'vlm_markdown' => '# Test',
            'status' => AttachedFileStatus::COMPLETED,
        ]);
        $this->assertTrue($file->hasVlmResult());
    }

    #[Test]
    public function has_vlm_result_returns_false_when_markdown_is_empty()
    {
        $file = AttachedFile::factory()->make([
            'vlm_markdown' => null,
            'status' => AttachedFileStatus::COMPLETED,
        ]);
        $this->assertFalse($file->hasVlmResult());
    }

    #[Test]
    public function has_vlm_result_returns_false_when_status_is_not_completed()
    {
        $file = AttachedFile::factory()->make([
            'vlm_markdown' => '# Test',
            'status' => AttachedFileStatus::VLM_PROCESSING,
        ]);
        $this->assertFalse($file->hasVlmResult());
    }

    #[Test]
    public function is_vlm_processing_returns_true_when_status_is_vlm_processing()
    {
        $file = AttachedFile::factory()->make(['status' => AttachedFileStatus::VLM_PROCESSING]);
        $this->assertTrue($file->isVlmProcessing());
    }

    #[Test]
    public function is_vlm_processing_returns_false_when_status_is_not_vlm_processing()
    {
        $file = AttachedFile::factory()->make(['status' => AttachedFileStatus::COMPLETED]);
        $this->assertFalse($file->isVlmProcessing());
    }

    #[Test]
    public function is_vlm_failed_returns_true_when_status_is_vlm_failed()
    {
        $file = AttachedFile::factory()->make(['status' => AttachedFileStatus::VLM_FAILED]);
        $this->assertTrue($file->isVlmFailed());
    }

    #[Test]
    public function is_vlm_failed_returns_false_when_status_is_not_vlm_failed()
    {
        $file = AttachedFile::factory()->make(['status' => AttachedFileStatus::COMPLETED]);
        $this->assertFalse($file->isVlmFailed());
    }

    // Phase5: New method tests
    #[Test]
    public function is_vlm_or_ocr_target_returns_true_for_images()
    {
        $file = AttachedFile::factory()->make(['mime' => 'image/png']);
        $this->assertTrue($file->isVlmOrOcrTarget());

        $file = AttachedFile::factory()->make(['mime' => 'image/jpeg']);
        $this->assertTrue($file->isVlmOrOcrTarget());
    }

    #[Test]
    public function is_vlm_or_ocr_target_returns_true_for_pdf()
    {
        $file = AttachedFile::factory()->make(['mime' => 'application/pdf']);
        $this->assertTrue($file->isVlmOrOcrTarget());
    }

    #[Test]
    public function is_vlm_or_ocr_target_returns_false_for_other_types()
    {
        $file = AttachedFile::factory()->make(['mime' => 'application/zip']);
        $this->assertFalse($file->isVlmOrOcrTarget());

        $file = AttachedFile::factory()->make(['mime' => 'text/plain']);
        $this->assertFalse($file->isVlmOrOcrTarget());
    }

    #[Test]
    public function is_ready_for_finalization_returns_true_when_all_processing_done()
    {
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'processing_finalized_at' => null,
        ]);
        $this->assertTrue($file->isReadyForFinalization());
    }

    #[Test]
    public function is_ready_for_finalization_returns_true_when_vlm_failed_and_ocr_done()
    {
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => now(),
            'vlm_failed_at' => now(),
            'ocr_processed_at' => now(),
            'processing_finalized_at' => null,
        ]);
        $this->assertTrue($file->isReadyForFinalization());
    }

    #[Test]
    public function is_ready_for_finalization_returns_false_when_already_finalized()
    {
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'processing_finalized_at' => now(),
        ]);
        $this->assertFalse($file->isReadyForFinalization());
    }

    #[Test]
    public function is_ready_for_finalization_returns_false_when_tika_not_done()
    {
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => null,
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
        ]);
        $this->assertFalse($file->isReadyForFinalization());
    }

    #[Test]
    public function get_display_status_returns_original_status_when_finalized()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::OCR_FAILED,
            'processing_finalized_at' => now(),
            'mime' => 'image/png',
        ]);

        $this->assertEquals(AttachedFileStatus::OCR_FAILED, $file->getDisplayStatus());
    }

    #[Test]
    public function get_display_status_returns_parallel_processing_when_ocr_failed_but_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::OCR_FAILED,
            'processing_finalized_at' => null,
            'mime' => 'image/png',
        ]);

        $this->assertEquals(AttachedFileStatus::PARALLEL_PROCESSING, $file->getDisplayStatus());
    }

    #[Test]
    public function get_display_status_returns_parallel_processing_when_vlm_failed_but_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::VLM_FAILED,
            'processing_finalized_at' => null,
            'mime' => 'application/pdf',
        ]);

        $this->assertEquals(AttachedFileStatus::PARALLEL_PROCESSING, $file->getDisplayStatus());
    }

    #[Test]
    public function get_display_status_returns_parallel_processing_when_tika_failed_but_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::TIKA_FAILED,
            'processing_finalized_at' => null,
            'mime' => 'image/jpeg',
        ]);

        $this->assertEquals(AttachedFileStatus::PARALLEL_PROCESSING, $file->getDisplayStatus());
    }

    #[Test]
    public function get_display_status_returns_original_status_for_non_vlm_ocr_target()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::OCR_FAILED,
            'processing_finalized_at' => null,
            'mime' => 'text/plain',
        ]);

        $this->assertEquals(AttachedFileStatus::OCR_FAILED, $file->getDisplayStatus());
    }

    #[Test]
    public function get_display_status_returns_original_status_for_processing_states()
    {
        $file = AttachedFile::factory()->make([
            'status' => AttachedFileStatus::PARALLEL_PROCESSING,
            'processing_finalized_at' => null,
            'mime' => 'image/png',
        ]);

        $this->assertEquals(AttachedFileStatus::PARALLEL_PROCESSING, $file->getDisplayStatus());
    }

    // Phase6: Text Preview Tests
    #[Test]
    public function has_previewable_text_returns_true_for_vlm()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test Content',
        ]);
        $this->assertTrue($file->hasPreviewableText());
    }

    #[Test]
    public function has_previewable_text_returns_false_when_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => null,
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test Content',
        ]);
        $this->assertFalse($file->hasPreviewableText());
    }

    #[Test]
    public function get_previewable_text_attribute_returns_vlm_markdown()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test Content',
        ]);
        $this->assertEquals('# Test Content', $file->previewable_text);
    }

    #[Test]
    public function get_previewable_text_attribute_returns_null_when_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => null,
            'finalized_source' => 'vlm',
            'vlm_markdown' => '# Test Content',
        ]);
        $this->assertNull($file->previewable_text);
    }

    #[Test]
    public function get_confidence_badge_info_returns_correct_vlm_badge()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'vlm_confidence' => 0.85,
        ]);

        $badgeInfo = $file->getConfidenceBadgeInfo();

        $this->assertIsArray($badgeInfo);
        $this->assertEquals('success', $badgeInfo['color']);
        $this->assertEquals('85.0%', $badgeInfo['score']);
    }

    #[Test]
    public function get_confidence_badge_info_returns_correct_ocr_badge()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => now(),
            'finalized_source' => 'ocr',
        ]);

        $badgeInfo = $file->getConfidenceBadgeInfo();

        $this->assertIsArray($badgeInfo);
        $this->assertEquals('warning', $badgeInfo['color']);
        $this->assertNull($badgeInfo['score']);
    }

    #[Test]
    public function get_confidence_badge_info_returns_correct_tika_badge()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => now(),
            'finalized_source' => 'tika',
        ]);

        $badgeInfo = $file->getConfidenceBadgeInfo();

        $this->assertIsArray($badgeInfo);
        $this->assertEquals('info', $badgeInfo['color']);
        $this->assertNull($badgeInfo['score']);
    }

    #[Test]
    public function get_confidence_badge_info_returns_null_when_not_finalized()
    {
        $file = AttachedFile::factory()->make([
            'processing_finalized_at' => null,
            'finalized_source' => 'vlm',
            'vlm_confidence' => 0.85,
        ]);

        $this->assertNull($file->getConfidenceBadgeInfo());
    }

    #[Test]
    public function get_processing_status_returns_correct_status()
    {
        // Finalized
        $file = AttachedFile::factory()->make(['processing_finalized_at' => now()]);
        $this->assertEquals('finalized', $file->processing_status);

        // Ready for finalization
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => now(),
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'processing_finalized_at' => null,
        ]);
        $this->assertEquals('ready_for_finalization', $file->processing_status);

        // Parallel processing
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => now(),
            'vlm_processed_at' => null,
            'ocr_processed_at' => null,
        ]);
        $this->assertEquals('parallel_processing', $file->processing_status);

        // Initial processing
        $file = AttachedFile::factory()->make([
            'tika_processed_at' => null,
        ]);
        $this->assertEquals('initial_processing', $file->processing_status);
    }

    // Phase 4.4: Timeline Tests
    #[Test]
    public function get_system_timeline_attribute_returns_correct_events()
    {
        $file = AttachedFile::factory()->create([
            'created_at' => now()->subMinutes(10),
            'tika_processed_at' => now()->subMinutes(8),
            'ocr_processed_at' => now()->subMinutes(5),
            'vlm_processed_at' => now()->subMinutes(2),
            'vlm_confidence' => 0.95,
            'processing_finalized_at' => now()->subMinute(),
            'finalized_source' => 'vlm',
        ]);

        $timeline = $file->system_timeline;

        $this->assertCount(5, $timeline);

        // Events should be sorted by asc timestamp (oldest first - chronological order)
        $this->assertEquals(__('ledger.file_inspector.history.uploaded'), $timeline[0]['title']);
        $this->assertEquals('system', $timeline[0]['type']);
        $this->assertEquals('o-paper-clip', $timeline[0]['icon']);
        $this->assertNotNull($timeline[0]['timestamp']);

        $this->assertEquals(__('ledger.file_inspector.history.tika_extraction'), $timeline[1]['title']);
        $this->assertEquals('system', $timeline[1]['type']);

        // Last event should be processing_finalized
        $this->assertEquals(__('ledger.file_inspector.history.processing_finalized'), $timeline[4]['title']);
        $this->assertEquals('o-check-circle', $timeline[4]['icon']);
    }

    #[Test]
    public function get_user_timeline_attribute_returns_correct_activities()
    {
        $user = \App\Models\User::factory()->create();
        $file = AttachedFile::factory()->create();

        // Create some activities
        // Create some activities with explicit timestamps
        $a1 = activity()
            ->performedOn($file)
            ->causedBy($user)
            ->event('downloaded')
            ->log('Downloaded file');
        $a1->created_at = now()->subMinutes(5);
        $a1->save();

        $a2 = activity()
            ->performedOn($file)
            ->causedBy($user)
            ->event('viewed_thumbnail')
            ->log('Viewed thumbnail');
        $a2->created_at = now()->subMinutes(1);
        $a2->save();

        $timeline = $file->user_timeline;

        $this->assertCount(2, $timeline);
        $this->assertEquals('user', $timeline[0]['type']);
        $this->assertEquals('eye', $timeline[0]['icon']); // viewed_thumbnail is latest
        $this->assertEquals('arrow-down-tray', $timeline[1]['icon']); // downloaded is older
    }
}

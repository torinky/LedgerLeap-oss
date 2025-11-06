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
}

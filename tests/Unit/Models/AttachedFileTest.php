<?php

namespace Tests\Unit\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AttachedFileTest extends TestCase
{
    use RefreshDatabase;

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
}

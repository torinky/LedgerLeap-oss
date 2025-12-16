<?php

namespace Tests\Unit\Models;

use App\Models\AttachedFile;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFileTimelineTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_generates_basic_timeline_with_upload_step()
    {
        $creator = User::factory()->create();
        $file = AttachedFile::factory()->create([
            'creator_id' => $creator->id,
        ]);

        $file->load('creator');
        $timeline = $file->getProcessingTimeline();

        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);

        // アップロードステップが存在することを確認
        $uploadStep = collect($timeline)->firstWhere('step', 'upload');
        $this->assertNotNull($uploadStep);
        $this->assertEquals('completed', $uploadStep['status']);
        $this->assertEquals($creator->id, $uploadStep['user']->id);
    }

    #[Test]
    public function it_includes_tika_processing_step_when_processed()
    {
        $file = AttachedFile::factory()->create([
            'tika_processed_at' => now(),
        ]);

        $timeline = $file->getProcessingTimeline();

        $tikaStep = collect($timeline)->firstWhere('step', 'tika');
        $this->assertNotNull($tikaStep);
        $this->assertEquals('completed', $tikaStep['status']);
    }

    #[Test]
    public function it_includes_vlm_success_step_when_processed()
    {
        $file = AttachedFile::factory()->create([
            'vlm_processed_at' => now(),
            'vlm_model' => 'gpt-4o-mini',
            'vlm_confidence' => 0.92,
            'vlm_processing_time_ms' => 4821,
        ]);

        $timeline = $file->getProcessingTimeline();

        $vlmStep = collect($timeline)->firstWhere('step', 'vlm');
        $this->assertNotNull($vlmStep);
        $this->assertEquals('completed', $vlmStep['status']);
        $this->assertEquals('gpt-4o-mini', $vlmStep['details']['model']);
        $this->assertEquals(0.92, $vlmStep['details']['confidence']);
    }

    #[Test]
    public function it_includes_vlm_failure_step_when_failed()
    {
        $file = AttachedFile::factory()->create([
            'vlm_failed_at' => now(),
        ]);

        $timeline = $file->getProcessingTimeline();

        $vlmStep = collect($timeline)->firstWhere('step', 'vlm');
        $this->assertNotNull($vlmStep);
        $this->assertEquals('failed', $vlmStep['status']);
        $this->assertEquals('error', $vlmStep['color']);
    }

    #[Test]
    public function it_includes_finalization_step_when_completed()
    {
        $file = AttachedFile::factory()->create([
            'processing_finalized_at' => now(),
            'finalized_source' => 'vlm',
            'contain_content' => true,
        ]);

        $timeline = $file->getProcessingTimeline();

        $finalStep = collect($timeline)->firstWhere('step', 'finalization');
        $this->assertNotNull($finalStep);
        $this->assertEquals('completed', $finalStep['status']);
        $this->assertEquals('vlm', $finalStep['details']['selected_source']);
    }

    #[Test]
    public function timeline_is_sorted_by_timestamp_descending()
    {
        $baseTime = now()->subHour(); // 基準時刻を設定
        $file = AttachedFile::factory()->create([
            'created_at' => $baseTime,
            'tika_processed_at' => $baseTime->copy()->addMinutes(5),
            'vlm_processed_at' => $baseTime->copy()->addMinutes(8),
            'ocr_processed_at' => $baseTime->copy()->addMinutes(10),
            'processing_finalized_at' => $baseTime->copy()->addMinutes(15),
        ]);

        $timeline = $file->getProcessingTimeline();

        // 最新のステップが最初に来ることを確認
        $this->assertEquals('finalization', $timeline[0]['step']);
        $this->assertEquals('upload', $timeline[count($timeline) - 1]['step']);
    }

    #[Test]
    public function it_includes_download_activities_when_relation_loaded()
    {
        $user = User::factory()->create();
        $file = AttachedFile::factory()->create();

        // ダウンロードアクティビティを記録
        activity()
            ->performedOn($file)
            ->causedBy($user)
            ->withProperties(['ip' => '127.0.0.1'])
            ->log('downloaded');

        $file->load('activities.causer');
        $timeline = $file->getProcessingTimeline();

        $downloadSteps = collect($timeline)->where('step', 'download');
        $this->assertCount(1, $downloadSteps);

        $downloadStep = $downloadSteps->first();
        $this->assertEquals($user->id, $downloadStep['user']->id);
        $this->assertEquals('info', $downloadStep['status']);
    }
}

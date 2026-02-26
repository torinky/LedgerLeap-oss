<?php

namespace Tests\Unit\Models;

use App\Models\AttachedFile;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFilePerformanceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function timeline_generation_completes_within_acceptable_time()
    {
        $creator = User::factory()->create();

        // 大量のアクティビティを持つファイル
        $file = AttachedFile::factory()->create([
            'creator_id' => $creator->id,
            'vlm_processed_at' => now(),
            'ocr_processed_at' => now(),
            'tika_processed_at' => now(),
            'processing_finalized_at' => now(),
        ]);

        // 100件のダウンロード履歴を追加
        for ($i = 0; $i < 100; $i++) {
            activity()
                ->performedOn($file)
                ->causedBy($creator)
                ->log('downloaded');
        }

        $file->load(['creator', 'activities.causer']);

        $startTime = microtime(true);
        $timeline = $file->getProcessingTimeline();
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // ミリ秒

        // 100ms以内に完了することを期待
        $this->assertLessThan(100, $executionTime);

        // タイムラインは最大でアップロード+処理ステップ+5件のダウンロード
        $this->assertLessThanOrEqual(10, count($timeline));
    }
}

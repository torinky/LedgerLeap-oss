<?php

namespace Tests\Unit\Models;

use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFileModelExtensionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_eager_loads_all_required_relations_without_n_plus_one()
    {
        $creator = User::factory()->create();
        $modifier = User::factory()->create();

        // 複数ファイルを作成
        $files = AttachedFile::factory()->count(3)->create([
            'creator_id' => $creator->id,
            'modifier_id' => $modifier->id,
        ]);

        // 各ファイルにアクティビティを追加
        foreach ($files as $file) {
            activity()
                ->performedOn($file)
                ->causedBy($creator)
                ->log('uploaded');
        }

        // Eager Loading
        \DB::enableQueryLog();

        $loadedFiles = AttachedFile::with([
            'creator',
            'modifier',
            'activities.causer',
        ])->get();

        $queries = \DB::getQueryLog();

        // 期待されるクエリ数: 4-5 (files + creators + modifiers + activities + causers)
        $this->assertLessThanOrEqual(6, count($queries));

        // リレーションがロードされていることを確認
        foreach ($loadedFiles as $file) {
            $this->assertTrue($file->relationLoaded('creator'));
            $this->assertTrue($file->relationLoaded('modifier'));
            $this->assertTrue($file->relationLoaded('activities'));
        }

        \DB::disableQueryLog();
    }

    #[Test]
    public function timeline_works_correctly_with_ledger_relation_chain()
    {
        $creator = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
        ]);

        $file = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $creator->id,
            'vlm_processed_at' => now(),
        ]);

        // Eager Loading: ledger.define.folder のチェーン
        $file->load([
            'ledger.define.folder',
            'creator',
        ]);

        $timeline = $file->getProcessingTimeline();

        $this->assertNotEmpty($timeline);
        $this->assertArrayHasKey('user', $timeline[0]);
    }

    #[Test]
    public function timeline_duration_calculation_is_accurate()
    {
        $createdAt = now()->subMinutes(10);
        $tikaAt = $createdAt->copy()->addMinutes(2);

        $file = AttachedFile::factory()->create([
            'created_at' => $createdAt,
            'tika_processed_at' => $tikaAt,
        ]);

        $timeline = $file->getProcessingTimeline();

        $tikaStep = collect($timeline)->firstWhere('step', 'tika');
        $this->assertNotNull($tikaStep['duration_ms']);

        // 約2分 = 120,000ミリ秒
        $expectedDuration = 2 * 60 * 1000;
        $this->assertEqualsWithDelta(
            $expectedDuration,
            $tikaStep['duration_ms'],
            5000 // 5秒の誤差許容
        );
    }
}

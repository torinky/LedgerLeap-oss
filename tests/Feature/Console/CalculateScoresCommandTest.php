<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CalculateScores;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class CalculateScoresCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 中央DBの基本データをシーディング
        $this->seed(UsersSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // ★★★ 簡素化版のコンフィグ設定（Phase 1） ★★★
        config([
            'ledgerleap.scoring.activity.windows' => [
                ['days' => 7, 'multiplier' => 10],
                ['days' => 30, 'multiplier' => 3],
            ],
            'ledgerleap.scoring.weights' => [
                'activity' => 0.40,
                'freshness' => 0.30,
                'importance' => 0.30,
                'relevance' => 0.00,
                'popularity' => 0.00,
            ],
        ]);
    }

    #[Test]
    public function it_calculates_scores_for_ledgers_in_a_tenant(): void
    {
        // 準備 (Arrange) - テナントを直接作成
        $tenant = Tenant::create(['id' => 'test-tenant']);
        $this->assertNotNull($tenant);

        $ledger = null;
        $tenant->run(function () use (&$ledger) {
            // ルートフォルダーを作成
            $folder = Folder::factory()->create(['parent_id' => null]);
            $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
            $ledger = Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine->id,
                'activity_score' => 0,
                'composite_score' => 0,
            ]);
            // 直近7日間の活動を2件作成
            activity()->performedOn($ledger)->log('created');
            activity()->performedOn($ledger)->log('updated');
        });
        $this->assertNotNull($ledger);

        // 実行 (Act)
        $command = $this->app->make(CalculateScores::class);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $exitCode = $this->app->call([$command, 'handle']);

        // 検証 (Assert)
        $this->assertEquals(Command::SUCCESS, $exitCode);

        $tenant->run(function () use ($ledger) {
            $updatedLedger = $ledger->fresh();

            // 簡素化版: 3件のイベント（factory作成時のcreated + 手動2件） × 10点 = 30点
            $this->assertGreaterThan(0, $updatedLedger->activity_score);
            $this->assertEquals(30, $updatedLedger->activity_score);

            // 複合スコアも計算されている
            $this->assertGreaterThan(0, $updatedLedger->composite_score);

            // activity: 30 × 0.4 = 12
            // freshness: ~73 × 0.3 = 21.9（テスト実行のタイミングで変動）
            // importance: 0 × 0.3 = 0
            // total: ~33.9
            $this->assertEqualsWithDelta(34, $updatedLedger->composite_score, 10);
        });
    }

    #[Test]
    public function it_does_not_accumulate_scores_on_repeated_calculations(): void
    {
        // 準備 (Arrange) - テナントを直接作成
        $tenant = Tenant::create(['id' => 'test-tenant-repeated']);
        $this->assertNotNull($tenant);

        $ledger = null;
        $tenant->run(function () use (&$ledger) {
            // ルートフォルダーを作成
            $folder = Folder::factory()->create(['parent_id' => null]);
            $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
            $ledger = Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine->id,
                'activity_score' => 0,
                'composite_score' => 0,
            ]);
            // 直近7日間の活動を2件作成
            activity()->performedOn($ledger)->log('created');
            activity()->performedOn($ledger)->log('updated');
        });
        $this->assertNotNull($ledger);

        // 実行 (Act) - 1回目のスコア計算
        $command = $this->app->make(CalculateScores::class);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $this->app->call([$command, 'handle']);

        // 1回目のスコアを記録
        $firstActivityScore = null;
        $firstCompositeScore = null;
        $tenant->run(function () use ($ledger, &$firstActivityScore, &$firstCompositeScore) {
            $updatedLedger = $ledger->fresh();
            $firstActivityScore = $updatedLedger->activity_score;
            $firstCompositeScore = $updatedLedger->composite_score;
        });

        // 実行 (Act) - 2回目のスコア計算（5分後を想定）
        sleep(1); // わずかに待機してタイムスタンプを変える
        $command2 = $this->app->make(CalculateScores::class);
        $output2 = new BufferedOutput;
        $command2->setOutput(new OutputStyle(new ArrayInput([]), $output2));
        $this->app->call([$command2, 'handle']);

        // 検証 (Assert) - スコアが累積していないことを確認
        $tenant->run(function () use ($ledger, $firstActivityScore, $firstCompositeScore) {
            $updatedLedger = $ledger->fresh();

            // 活動スコアは同じはず（新しいアクティビティが記録されていないため）
            $this->assertEquals($firstActivityScore, $updatedLedger->activity_score,
                'Activity score should not accumulate on repeated calculations');

            // 複合スコアも同程度のはず（新鮮度スコアがわずかに減少する可能性があるため、delta許容）
            $this->assertEqualsWithDelta($firstCompositeScore, $updatedLedger->composite_score, 1,
                'Composite score should not significantly change on repeated calculations');
        });
    }

    #[Test]
    public function it_does_not_log_score_updates_as_activity(): void
    {
        // 準備 (Arrange) - テナントを直接作成
        $tenant = Tenant::create(['id' => 'test-tenant-no-log']);
        $this->assertNotNull($tenant);

        $ledger = null;
        $tenant->run(function () use (&$ledger) {
            // ルートフォルダーを作成
            $folder = Folder::factory()->create(['parent_id' => null]);
            $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
            $ledger = Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine->id,
                'activity_score' => 0,
                'composite_score' => 0,
            ]);
            // 初期アクティビティを1件作成
            activity()->performedOn($ledger)->log('created');
        });
        $this->assertNotNull($ledger);

        // 初期のアクティビティログ件数を記録
        $initialActivityCount = null;
        $tenant->run(function () use ($ledger, &$initialActivityCount) {
            $initialActivityCount = Activity::query()
                ->where('subject_type', Ledger::class)
                ->where('subject_id', $ledger->id)
                ->count();
        });

        // 実行 (Act) - スコア計算を実行
        $command = $this->app->make(CalculateScores::class);
        $output = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $this->app->call([$command, 'handle']);

        // 検証 (Assert) - スコア更新がアクティビティログに記録されていないことを確認
        $tenant->run(function () use ($ledger, $initialActivityCount) {
            $afterCalculationActivityCount = Activity::query()
                ->where('subject_type', Ledger::class)
                ->where('subject_id', $ledger->id)
                ->count();

            $this->assertEquals($initialActivityCount, $afterCalculationActivityCount,
                'Score calculation should not create activity log entries');
        });
    }
}

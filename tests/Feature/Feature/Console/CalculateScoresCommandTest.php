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
}

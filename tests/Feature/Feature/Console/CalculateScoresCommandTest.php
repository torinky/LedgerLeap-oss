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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Console\OutputStyle;
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

        // ★★★ テストに必要なコンフィグ値を設定 ★★★
        config([
            'ledgerleap.scoring.activity' => [
                'created' => 10,
                'updated' => 5,
                'viewed' => 1,
            ],
            'ledgerleap.scoring.decay.rate' => 0.95,
        ]);
    }

    #[Test]
    public function it_calculates_scores_for_ledgers_in_a_tenant(): void
    {
        // 準備 (Arrange)
        Artisan::call('app:setup-tenant', [
            'tenant_id' => 'test-tenant',
            'name' => 'Test Tenant',
            'admin_email' => 'super_admin@ll.com'
        ]);
        $tenant = Tenant::find('test-tenant');
        $this->assertNotNull($tenant);

        $ledger = null;
        $tenant->run(function () use (&$ledger) {
            $folder = Folder::whereIsRoot()->first();
            $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
            $ledger = Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine->id,
                'activity_score' => 0,
                'composite_score' => 0,
            ]);
            activity()->performedOn($ledger)->log('created');
        });
        $this->assertNotNull($ledger);


        // 実行 (Act)
        $command = $this->app->make(CalculateScores::class);
        $output = new BufferedOutput();
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $exitCode = $this->app->call([$command, 'handle']);


        // 検証 (Assert)
        $this->assertEquals(Command::SUCCESS, $exitCode);

        $tenant->run(function () use ($ledger) {
            $updatedLedger = $ledger->fresh();

            $this->assertGreaterThan(0, $updatedLedger->activity_score);
            $this->assertEquals(10, $updatedLedger->activity_score);
            $this->assertGreaterThan(0, $updatedLedger->composite_score);
        });
    }
}

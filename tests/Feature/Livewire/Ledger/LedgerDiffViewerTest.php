<?php

namespace tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\LedgerDiffViewer;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCase;

class LedgerDiffViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $ledgerDefine = LedgerDefine::factory()->create();
        $this->ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();
    }

    #[Test]
    public function it_renders_correctly_with_data_from_processor(): void
    {
        // 1. LedgerContentProcessor のモックを作成
        $mock = $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            // processContentForDisplay が呼び出された際に返すダミーデータを定義
            $dummyDisplayData = [
                [
                    'group_name' => 'Test Group',
                    'is_required_group' => true,
                    'columns' => [
                        [
                            'id' => 'col1',
                            'name' => 'Test Column',
                            'hint' => 'A hint',
                            'is_required' => true,
                            'status' => 'modified',
                            'current_value_html' => '<div>Current Value</div>',
                            'old_value_html' => '<div>Old Value</div>',
                        ]
                    ]
                ]
            ];

            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->andReturn([
                    'displayData' => $dummyDisplayData,
                    'hasChangedColumns' => true,
                ]);
        });

        // 2. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger])
            ->assertOk()
            ->assertSet('hasChangedColumns', true)
            ->assertSee('Test Group')
            ->assertSee('Test Column')
            ->assertSeeHtml('<div>Current Value</div>');
    }

    #[Test]
    public function it_calls_processor_with_updated_display_level(): void
    {
        // 1. LedgerContentProcessor のモックを作成し、呼び出しを期待する設定を行う
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            // 最初に displayLevel=1 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 1, Mockery::any(), Mockery::any())
                ->andReturn(['displayData' => [], 'hasChangedColumns' => false]);

            // 次に displayLevel=2 で呼び出されることを期待
            $mock->shouldReceive('processContentForDisplay')
                ->once()
                ->with(Mockery::any(), Mockery::any(), 2, Mockery::any(), Mockery::any())
                ->andReturn(['displayData' => [], 'hasChangedColumns' => false]);
        });

        // 2. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger, 'displayLevel' => 1])
            ->dispatch('displayLevelUpdated', displayLevel: 2) // イベントを発行
            ->assertSet('displayLevel', 2);
    }

    #[Test]
    public function it_hides_diff_view_by_default(): void
    {
        // 1. プロセッサのモック
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('processContentForDisplay')->andReturn([
                'displayData' => [],
                'hasChangedColumns' => true,
            ]);
        });

        // 2. Livewire コンポーネントをテスト (showChanges はデフォルトで false)
        Livewire::test(LedgerDiffViewer::class, ['ledgerRecord' => $this->ledger])
            ->assertSet('showChanges', false)
            ->assertDontSeeHtml('Version.');
    }

    #[Test]
    public function it_shows_diff_view_when_show_changes_is_true(): void
    {
        // 2. データベースの状態を正確にセットアップ
        // 現在の台帳 (version 2)
        $ledger = Ledger::factory()->create(['version' => 2]);

        // 最新の差分 (version 2)
        $currentDiff = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 2,
        ]);

        // 比較対象となるべき過去の差分 (version 1)
        $pastDiff = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
        ]);

        // 台帳に最新の差分IDをセット
        $ledger->latest_diff_id = $currentDiff->id;
        $ledger->save();
        $ledger->refresh();

        // LedgerDiffProcessor のモック
        $this->mock(LedgerDiffProcessor::class, function (Mockery\MockInterface $mock) use ($pastDiff) {
            $mock->shouldReceive('findComparisonTargetDiff')
                ->once()
                ->andReturn($pastDiff); // version 1 のDiffを返すようにモック
        });

        // LedgerContentProcessor のモック
        $this->mock(LedgerContentProcessor::class, function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('processContentForDisplay')->andReturn([
                'displayData' => [
                    [
                        'group_name' => 'Test Group',
                        'is_required_group' => false,
                        'columns' => [
                            [
                                'id' => 'col1',
                                'name' => 'Test Column',
                                'hint' => 'A hint',
                                'is_required' => false,
                                'status' => 'unchanged',
                                'current_value_html' => '<div>Current Value</div>',
                                'old_value_html' => '<div>Old Value</div>',
                            ]
                        ]
                    ]
                ],
                'hasChangedColumns' => true,
            ]);
        });

        // 3. showChanges を true で初期化してコンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger,
            'showChanges' => true,
        ])
            ->assertSet('showChanges', true)
            ->assertSeeHtml('過去 Version. 1'); // 過去のバージョン(1)が表示されることを確認
    }
}
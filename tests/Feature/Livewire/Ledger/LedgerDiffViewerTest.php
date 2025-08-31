<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\LedgerDiffViewer;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerDiffViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ledger $ledger;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $ledgerDefine = LedgerDefine::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create(['tenant_id' => $this->tenant->id]);
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
        // このテストでは、プロセッサが実際に動作して差分を検出し、
        // ビューが正しくレンダリングされることを確認するため、モックは使用しない。

        // 1. データベースの状態を正確にセットアップ
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                ['id' => 1, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ],
            'tenant_id' => $this->tenant->id,
        ]);

        // 2. version 1 の Ledger と LedgerDiff を作成
        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create([
                'version' => 1,
                'content' => ['old value'],
                'tenant_id' => $this->tenant->id,
            ]);

        $diffV1 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 1,
            'content' => ['old value'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);
        $ledger->latest_diff_id = $diffV1->id;
        $ledger->save();

        // 3. Ledger を更新して version 2 にする
        $ledger->version = 2;
        $ledger->content = ['current value'];
        
        // 4. version 2 の LedgerDiff を作成
        $diffV2 = \App\Models\LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'version' => 2,
            'content' => ['current value'],
            'column_define' => $ledgerDefine->column_define,
            'tenant_id' => $this->tenant->id,
        ]);
        $ledger->latest_diff_id = $diffV2->id;
        $ledger->save();
        
        // 5. 最終状態をDBから読み込んでコンポーネントに渡す
        $ledger->refresh();

        // 6. Livewire コンポーネントをテスト
        Livewire::test(LedgerDiffViewer::class, [
            'ledgerRecord' => $ledger, // version 2 の Ledger
            'canView' => true,
            'hasChangedColumns' => true,
            'showChanges' => true,
        ])
            ->set('hasChangedColumns', true) // ->set() を使ってプロパティを有効化
            ->set('showChanges', true) // ->set() を使ってプロパティを有効化
            ->assertSeeHtml('Version. 1'); // 比較対象の version 1 が表示されることを確認
    }
}
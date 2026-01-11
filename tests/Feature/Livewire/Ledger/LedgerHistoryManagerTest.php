<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerHistoryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Ledger $ledger;

    protected LedgerDefine $ledgerDefine;

    protected LedgerDiff $diff1;

    protected LedgerDiff $diff2;

    protected LedgerDiff $diff3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Test Ledger',
            'column_define' => [
                ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ],
        ]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']],
        ]);

        // 作成された履歴（Diff）をシミュレート
        $this->diff1 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 1,
            'content' => [['0' => 'Value 1']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->diff2 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 2,
            'content' => [['0' => 'Value 2']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        $this->diff3 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $this->ledger->update(['latest_diff_id' => $this->diff3->id]);
        $this->ledger->setRelation('ledgerDiff', collect([$this->diff3, $this->diff2, $this->diff1]));
    }

    #[Test]
    public function it_renders_initial_history_list()
    {
        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->assertSee('Ver.1')
            ->assertSee('Ver.2')
            ->assertSee('Ver.3')
            ->assertViewHas('history', function ($history) {
                return $history->count() === 3;
            })
            ->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);
    }

    #[Test]
    public function it_selects_versions_for_comparison()
    {
        // テスト開始
        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id]);

        // 初期状態で $diff3 (base) のみが選択されているはず（target は null）
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);

        // $diff2 を追加選択
        $component->call('toggleSelection', $this->diff2->id);
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', $this->diff2->id);

        // $diff2 を解除
        $component->call('toggleSelection', $this->diff2->id);
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);

        // $diff3 を解除
        $component->call('toggleSelection', $this->diff3->id);
        $component->assertSet('baseDiffId', null)
            ->assertSet('targetDiffId', null);

        // $diff2 と $diff1 を選択（任意2バージョン比較の検証）
        $component->call('toggleSelection', $this->diff2->id) // base
            ->call('toggleSelection', $this->diff1->id); // target

        $component->assertSet('baseDiffId', $this->diff2->id)
            ->assertSet('targetDiffId', $this->diff1->id)
            ->assertViewHas('baseDiff', function ($diff) {
                return $diff->id === $this->diff2->id;
            })
            ->assertViewHas('targetDiff', function ($diff) {
                return $diff->id === $this->diff1->id;
            })
            ->assertViewHas('baseMeta', function ($meta) {
                return $meta['version'] === 2 && $meta['modifier_name'] === $this->user->name;
            })
            ->assertViewHas('targetMeta', function ($meta) {
                return $meta['version'] === 1 && $meta['modifier_name'] === $this->user->name;
            });
    }

    #[Test]
    public function it_loads_more_history_on_infinite_scroll()
    {
        // さらに履歴を作成
        for ($i = 4; $i <= 25; $i++) {
            LedgerDiff::create([
                'ledger_id' => $this->ledger->id,
                'ledger_define_id' => $this->ledgerDefine->id,
                'version' => $i,
                'content' => [['0' => "Value $i"]],
                'column_define' => $this->ledgerDefine->column_define,
                'completed_inspector_role_ids' => [],
                'completed_approver_role_ids' => [],
                'modifier_id' => $this->user->id,
                'creator_id' => $this->user->id,
                'created_at' => now()->subSeconds($i),
            ]);
        }

        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->assertViewHas('history', function ($history) {
                return $history->count() === 10; // デフォルト 10件
            })
            ->call('loadMore')
            ->assertViewHas('history', function ($history) {
                return $history->count() === 20; // 10 + 10
            })
            ->call('loadMore')
            ->assertViewHas('history', function ($history) {
                return $history->count() === 25; // 20 + 5
            });
    }

    #[Test]
    public function it_propagates_highlight_to_view()
    {
        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, [
                'ledgerId' => $this->ledger->id,
                'highlight' => 'Value',
            ])
            ->assertSet('highlight', 'Value')
            ->assertSee('Value'); // 検索ハイライトが存在する場合、ビューに反映されることを確認
    }

    #[Test]
    public function it_logs_performance_metrics_when_enabled()
    {
        config(['ledgerleap.performance.enabled' => true]);
        config(['ledgerleap.performance.log_destination' => 'log']);

        \Illuminate\Support\Facades\Log::spy();

        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->set('perPage', 1);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_mount'
                    && isset($context['duration_ms'])
                    && $context['ledger_id'] === $this->ledger->id;
            });

        // Toggle selection logic check
        $component->call('toggleSelection', $this->diff2->id);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_toggle_selection'
                    && isset($context['duration_ms']);
            });

        // Load more logic check
        $component->call('loadMore');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_load_more'
                    && isset($context['duration_ms']);
            });
    }
}

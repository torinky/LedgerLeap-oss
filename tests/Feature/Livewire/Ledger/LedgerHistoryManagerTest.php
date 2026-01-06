<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Models\LedgerDefine;
use App\Models\Folder;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Test Ledger',
            'column_define' => [
                ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1]
            ]
        ]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']]
        ]);

        // 作成された履歴（Diff）をシミュレート
        LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 1,
            'content' => [['0' => 'Value 1']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDays(2)
        ]);

        LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 2,
            'content' => [['0' => 'Value 2']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDay()
        ]);

        $diff3 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()
        ]);

        $this->ledger->update(['latest_diff_id' => $diff3->id]);
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
            ->assertSet('baseDiffId', LedgerDiff::where('version', 3)->first()->id)
            ->assertSet('targetDiffId', LedgerDiff::where('version', 2)->first()->id);
    }

    #[Test]
    public function it_selects_versions_for_comparison()
    {
        $diff1 = LedgerDiff::where('version', 1)->first();
        $diff2 = LedgerDiff::where('version', 2)->first();
        $diff3 = LedgerDiff::where('version', 3)->first();
        
        // テスト開始
        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id]);


        // 初期状態で $diff3 (base) と $diff2 (target) が選択されているはず
        $component->assertSet('baseDiffId', $diff3->id)
            ->assertSet('targetDiffId', $diff2->id);

        // $diff2 を解除
        $component->call('toggleSelection', $diff2->id);
        $component->assertSet('baseDiffId', $diff3->id)
            ->assertSet('targetDiffId', null);

        // $diff3 を解除
        $component->call('toggleSelection', $diff3->id);
        $component->assertSet('baseDiffId', null)
            ->assertSet('targetDiffId', null);

        // $diff2 と $diff1 を選択
        $component->call('toggleSelection', $diff2->id)
            ->call('toggleSelection', $diff1->id);
        $component->assertSet('baseDiffId', $diff2->id)
            ->assertSet('targetDiffId', $diff1->id);
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
                'created_at' => now()->subSeconds($i)
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
}

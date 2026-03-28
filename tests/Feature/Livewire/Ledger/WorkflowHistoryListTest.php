<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\WorkflowHistoryList;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class WorkflowHistoryListTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $ledgerDefine = LedgerDefine::factory()->create();
        $this->ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();

        // 履歴を作成
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()->subDays(2)]);
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()->subDay()]);
        LedgerDiff::factory()->for($this->ledger)->create(['created_at' => now()]);
    }

    #[Test]
    public function component_renders_successfully()
    {
        Livewire::test(WorkflowHistoryList::class, ['ledgerRecord' => $this->ledger])
            ->assertStatus(200);
    }

    #[Test]
    public function it_loads_workflow_history_on_mount()
    {
        $component = Livewire::test(WorkflowHistoryList::class, ['ledgerRecord' => $this->ledger]);

        $component->assertViewHas('workflowHistory', function ($history) {
            return $history->count() === 3 &&
                   $history[0]->created_at > $history[1]->created_at &&
                   $history[1]->created_at > $history[2]->created_at;
        });
    }

    #[Test]
    public function it_filters_redundant_sequential_entries()
    {
        $this->ledger->ledgerDiff()->delete(); // 初期データをクリア

        // 1. 下書き保存 (v1, draft, user 1)
        LedgerDiff::factory()->for($this->ledger)->create([
            'version' => 1,
            'status' => 'draft',
            'modifier_id' => $this->user->id,
            'created_at' => now()->subMinutes(10),
            'id' => 100,
        ]);

        // 2. 連続して下書き保存（重複） (v1, draft, user 1)
        LedgerDiff::factory()->for($this->ledger)->create([
            'version' => 1,
            'status' => 'draft',
            'modifier_id' => $this->user->id,
            'created_at' => now()->subMinutes(9),
            'id' => 101, // これが残るべき最新の draft (v1)
        ]);

        // 3. 点検依頼 (v1, pending_inspection, user 1)
        LedgerDiff::factory()->for($this->ledger)->create([
            'version' => 1,
            'status' => 'pending_inspection',
            'modifier_id' => $this->user->id,
            'created_at' => now()->subMinutes(8),
            'id' => 102,
        ]);

        // 4. 別のユーザーによる下書き保存（ステータス・バージョン同じだが操作者が違うので残るべき）
        $otherUser = User::factory()->create();
        LedgerDiff::factory()->for($this->ledger)->create([
            'version' => 1,
            'status' => 'pending_inspection',
            'modifier_id' => $otherUser->id,
            'created_at' => now()->subMinutes(7),
            'id' => 103,
        ]);

        $component = Livewire::test(WorkflowHistoryList::class, ['ledgerRecord' => $this->ledger]);

        $component->assertViewHas('workflowHistory', function ($history) {
            // ID 100 が消えて、101, 102, 103 の3件が残るはず（降順なので 103, 102, 101）
            return $history->count() === 3 &&
                   $history[0]->id === 103 &&
                   $history[1]->id === 102 &&
                   $history[2]->id === 101;
        });
    }
}

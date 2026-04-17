<?php

namespace Tests\Feature\Ledger;

use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerHistoryListTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    /**
     * テナント隔離下で全ての履歴が取得できることを検証する
     */
    public function test_component_receives_all_diffs_within_tenant_context(): void
    {
        $this->actingAs(User::factory()->create());
        tenancy()->initialize($this->getTenant());

        $ledger = Ledger::factory()->create([
            'version' => 10, // 意図的に進める
            'status' => \App\Enums\WorkflowStatus::DRAFT,
        ]);

        // 10件の履歴を作成
        for ($i = 1; $i <= 10; $i++) {
            LedgerDiff::create([
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledger->ledger_define_id,
                'content' => [],
                'column_define' => [],
                'version' => $i,
                'status' => \App\Enums\WorkflowStatus::DRAFT,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $ledger->modifier_id,
                'completed_inspector_role_ids' => [],
                'completed_approver_role_ids' => [],
            ]);
        }

        // コンポーネントをテスト
        Livewire::test(LedgerHistoryManager::class, ['ledgerId' => $ledger->id])
            ->assertViewHas('history', function ($history) {
                // 作成した10件が全て含まれているか
                return $history->count() === 10;
            });
    }

    /**
     * Livewire の初回描画時に tenant コンテキストが外れていても、
     * 台帳自身の tenant_id から履歴を復元できることを検証する
     */
    public function test_component_recovers_when_tenant_context_is_missing(): void
    {
        $this->actingAs(User::factory()->create());
        tenancy()->initialize($this->getTenant());

        $ledger = Ledger::factory()->create([
            'version' => 2,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
            'tenant_id' => $this->getTenant()->id,
        ]);

        $firstDiff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'tenant_id' => $ledger->tenant_id,
            'content' => [],
            'column_define' => [],
            'version' => 1,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);

        $secondDiff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'tenant_id' => $ledger->tenant_id,
            'content' => [],
            'column_define' => [],
            'version' => 2,
            'status' => \App\Enums\WorkflowStatus::DRAFT->value,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        // tenant コンテキストが空でも、ledger の tenant_id から復元できることを確認する
        Livewire::test(LedgerHistoryManager::class, ['ledgerId' => $ledger->id])
            ->assertViewHas('history', function ($history) use ($firstDiff, $secondDiff) {
                return $history->count() === 2
                    && $history->contains('id', $firstDiff->id)
                    && $history->contains('id', $secondDiff->id);
            });
    }
}

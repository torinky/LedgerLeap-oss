<?php

namespace Tests\Feature\Ledger;

use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * tenant_id が欠落したデータがある場合に、取得漏れが発生することを（負のテストとして）検証し、
     * 開発者が不整合に気づけるようにする
     */
    public function test_component_misses_diffs_with_missing_tenant_id(): void
    {
        $this->actingAs(User::factory()->create());

        $ledger = Ledger::factory()->create([
            'version' => 2,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
        ]);

        // 正当な履歴
        LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'content' => [],
            'column_define' => [],
            'version' => 1,
            'status' => \App\Enums\WorkflowStatus::DRAFT,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);

        // 不整合な履歴 (tenant_id を NULL にする。Eloquent イベントを完全に回避するため DB へ直接挿入)
        DB::table('ledger_diffs')->insert([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'content' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'column_define' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'version' => 2,
            'tenant_id' => null,
            'status' => \App\Enums\WorkflowStatus::DRAFT->value,
            'creator_id' => $ledger->creator_id,
            'modifier_id' => $ledger->modifier_id,
            'completed_inspector_role_ids' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_approver_role_ids' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 期待値: 1件のみ表示される (不整合データはフィルタリングされる)
        // このテストが存在することで、将来的に「なぜか表示されない」という問題が起きた際に
        // テナント隔離の影響であることを示唆できる
        Livewire::test(LedgerHistoryManager::class, ['ledgerId' => $ledger->id])
            ->assertViewHas('history', function ($history) {
                return $history->count() === 1;
            });
    }
}

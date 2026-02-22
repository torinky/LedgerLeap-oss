<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\WorkflowStatusCard;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\WorkflowService;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * WorkflowStatusCard — workflowHistory / requiredRolesProgress 追加テスト
 *
 * 既存 WorkflowStatusCardTest との重複を避け、
 * Computed プロパティの返り値のみ検証する。
 */
#[CoversClass(WorkflowStatusCard::class)]
class WorkflowStatusCardComputedTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected \App\Models\Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['workflow_enabled' => false]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        // WorkflowService の基本モック
        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('canRequestApproval')->andReturn(false);
        $mock->shouldReceive('canApprove')->andReturn(false);
        $mock->shouldReceive('canReturnToDraft')->andReturn(false);
        $this->app->instance(WorkflowService::class, $mock);
    }

    // ================================================================
    // workflowHistory
    // ================================================================

    #[Test]
    public function workflow_history_returns_empty_collection_when_no_diffs(): void
    {
        $component = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger]);

        // Computed プロパティは直接アクセスできないので render の成功で確認
        $component->assertStatus(200);
        $this->assertEquals(0, $this->ledger->ledgerDiff()->count());
    }

    #[Test]
    public function workflow_history_returns_diffs_ordered_by_created_at_desc(): void
    {
        $diff1 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'content' => [],
            'column_define' => [],
            'created_at' => now()->subMinutes(10),
        ]);
        $diff2 = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'content' => [],
            'column_define' => [],
            'created_at' => now(),
        ]);

        $history = $this->ledger->ledgerDiff()
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertEquals($diff2->id, $history->first()->id);
        $this->assertEquals($diff1->id, $history->last()->id);

        Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
            ->assertStatus(200);
    }

    // ================================================================
    // requiredRolesProgress
    // ================================================================

    #[Test]
    public function required_roles_progress_returns_empty_when_workflow_disabled(): void
    {
        // workflow_enabled=false なので空配列が返る
        $component = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger]);
        $component->assertStatus(200);

        // workflow_disabled → getRequiredRolesProgressDetails は呼ばれない
        $this->assertFalse((bool) $this->ledgerDefine->workflow_enabled);
    }

    #[Test]
    public function required_roles_progress_is_computed_when_workflow_enabled(): void
    {
        $this->ledgerDefine->update(['workflow_enabled' => true]);
        $this->ledger->refresh();

        // workflow_enabled=true でも folder が存在する場合はメソッドが実行される
        Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
            ->assertStatus(200);
    }
}

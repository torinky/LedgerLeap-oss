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
use Illuminate\Database\Eloquent\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * WorkflowStatusCard — workflowHistory / requiredRolesProgress 追加テスト
 *
 * Livewire の #[Computed] プロパティはビューから参照されて初めて実行されるため、
 * instance() 経由でメソッドを直接呼び出してカバレッジを計上する。
 */
#[CoversClass(WorkflowStatusCard::class)]
class WorkflowStatusCardComputedTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected Folder $folder;

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
    // workflowHistory — instance() 経由で直接呼び出す
    // ================================================================

    #[Test]
    public function workflow_history_returns_empty_collection_when_no_diffs(): void
    {
        /** @var WorkflowStatusCard $instance */
        $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
            ->instance();

        // #[Computed] メソッドを直接呼び出してカバレッジを計上
        $history = $instance->workflowHistory();

        $this->assertInstanceOf(Collection::class, $history);
        $this->assertTrue($history->isEmpty());
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

        /** @var WorkflowStatusCard $instance */
        $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
            ->instance();

        $history = $instance->workflowHistory();

        $this->assertInstanceOf(Collection::class, $history);
        $this->assertCount(2, $history);
        // DESC ソートで diff2 が先頭
        $this->assertEquals($diff2->id, $history->first()->id);
        $this->assertEquals($diff1->id, $history->last()->id);
    }

    // ================================================================
    // requiredRolesProgress — instance() 経由で直接呼び出す
    // ================================================================

    #[Test]
    public function required_roles_progress_returns_empty_when_workflow_disabled(): void
    {
        // workflow_enabled=false → 空配列
        /** @var WorkflowStatusCard $instance */
        $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $this->ledger])
            ->instance();

        $progress = $instance->requiredRolesProgress();

        $this->assertIsArray($progress);
        $this->assertEmpty($progress);
    }

    #[Test]
    public function required_roles_progress_is_computed_when_workflow_enabled(): void
    {
        // workflow_enabled=true で最初から別途 LedgerDefine + Ledger を作成
        // （setUp() で作成した ledgerDefine は workflow_enabled=false のため使用しない）
        $ledgerDefineEnabled = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['workflow_enabled' => true]);

        $ledgerEnabled = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefineEnabled->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        // define.folder を eager load した状態で渡す（Computed の条件を満たすため）
        $ledger = Ledger::with(['define.folder', 'latestDiff'])->find($ledgerEnabled->id);

        /** @var WorkflowStatusCard $instance */
        // Livewire::test() を呼ぶ前に workflow_enabled=true の ledger を渡し、
        // キャッシュ生成前にメソッドを呼び出す
        $instance = Livewire::test(WorkflowStatusCard::class, ['ledgerRecord' => $ledger])
            ->instance();

        // workflow_enabled=true, folder 存在 → getRequiredRolesProgressDetails() が呼ばれる
        // instance() 取得直後に呼ぶことでキャッシュが空の状態で実行させる
        $progress = $instance->requiredRolesProgress();

        $this->assertIsArray($progress);
        $this->assertArrayHasKey('inspection', $progress);
        $this->assertArrayHasKey('approval', $progress);
    }
}

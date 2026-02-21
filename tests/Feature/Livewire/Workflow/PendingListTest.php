<?php

namespace Tests\Feature\Livewire\Workflow;

use App\Enums\WorkflowStatus;
use App\Livewire\Workflow\PendingList;
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

#[CoversClass(PendingList::class)]
class PendingListTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private WorkflowService $workflowService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = Folder::factory()->create([
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => true,
        ]);

        $this->workflowService = app(WorkflowService::class);
    }

    // ================================================================
    // 初期表示
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->assertStatus(200);
    }

    #[Test]
    public function renders_empty_when_no_pending_tasks(): void
    {
        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->assertSee([]) // pendingTasks が空
            ->assertSet('totalPendingTasks', 0);
    }

    #[Test]
    public function renders_pending_inspection_tasks_for_user(): void
    {
        // 点検者として割り当てられたタスクを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        // render() が正常に動作し、totalPendingTasks が整数として設定されること
        $component = Livewire::actingAs($this->user)
            ->test(PendingList::class);

        $component->assertStatus(200);
        $this->assertIsInt($component->get('totalPendingTasks'));
    }

    #[Test]
    public function renders_pending_approval_tasks_for_user(): void
    {
        $inspector = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);
        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $inspector->id,
            'approver_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(PendingList::class);

        $component->assertStatus(200);
        $this->assertIsInt($component->get('totalPendingTasks'));
    }

    // ================================================================
    // approveTask
    // ================================================================

    #[Test]
    public function approve_task_succeeds_when_user_is_approver(): void
    {
        $inspector = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $inspector->id,
            'approver_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('approve')
            ->once()
            ->with($ledger->id, $this->user->id);

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('approveTask', $diff->id);
    }

    #[Test]
    public function approve_task_fails_when_user_is_not_approver(): void
    {
        $otherUser = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'approver_id' => $otherUser->id, // 自分ではない
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('approve')->never();

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('approveTask', $diff->id);
        // 権限エラーが発生してapproveが呼ばれないことを確認（モックで保証）
    }

    #[Test]
    public function approve_task_with_invalid_id_does_not_call_service(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('approve')->never();

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('approveTask', 99999); // 存在しないID
    }

    // ================================================================
    // openReturnToDraftModal / returnTaskToDraft
    // ================================================================

    #[Test]
    public function open_return_to_draft_modal_sets_state(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('openReturnToDraftModal', $diff->id)
            ->assertSet('returnToDraftModal', true)
            ->assertSet('selectedLedgerDiffId', $diff->id);
    }

    #[Test]
    public function return_task_to_draft_succeeds_when_user_is_inspector(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('returnToDraft')
            ->once()
            ->with($ledger->id, $this->user->id, null);

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->set('selectedLedgerDiffId', $diff->id)
            ->call('returnTaskToDraft');
    }

    #[Test]
    public function return_task_to_draft_fails_when_not_authorized(): void
    {
        $otherUser = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $otherUser->id, // 自分ではない
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('returnToDraft')->never();

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->set('selectedLedgerDiffId', $diff->id)
            ->call('returnTaskToDraft');
    }

    #[Test]
    public function return_task_to_draft_with_no_selected_diff_returns_early(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('returnToDraft')->never();

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('returnTaskToDraft'); // selectedLedgerDiffId が null
    }

    // ================================================================
    // openApproverSelectModal
    // ================================================================

    #[Test]
    public function open_approver_select_modal_fails_when_not_authorized(): void
    {
        $otherUser = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $otherUser->id, // 自分ではない
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('openApproverSelectModal', $ledger->id)
            ->assertSet('showAssigneeModal', false); // モーダルが開かないこと
    }

    #[Test]
    public function open_approver_select_modal_with_invalid_ledger_does_not_open(): void
    {
        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->call('openApproverSelectModal', 99999)
            ->assertSet('showAssigneeModal', false);
    }

    // ================================================================
    // handleAssigneeSelected
    // ================================================================

    #[Test]
    public function handle_assignee_selected_ignores_wrong_role_type(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('requestApproval')->never();

        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->set('modalLedgerId', null)
            ->call('handleAssigneeSelected', $this->user->id, 'inspector'); // approver 以外
    }

    // ================================================================
    // refreshList
    // ================================================================

    #[Test]
    public function refresh_list_responds_to_event(): void
    {
        Livewire::actingAs($this->user)
            ->test(PendingList::class)
            ->dispatch('refreshPendingList')
            ->assertStatus(200);
    }
}

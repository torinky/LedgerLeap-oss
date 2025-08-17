<?php

namespace tests\Unit\Traits;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Component;
use tests\TestCase;
use App\Traits\WorkflowActions; // テスト対象のトレイト
use Mary\Traits\Toast; // 追加

// ダミーのLivewireコンポーネント
class DummyWorkflowComponentForTraitTest extends Component
{
    use WorkflowActions, Toast; // Toast を追加

    public Ledger $ledgerRecord;

    public function mount(Ledger $ledgerRecord)
    {
        $this->ledgerRecord = $ledgerRecord;
    }

    public function boot(WorkflowService $workflowService): void
    {
        $this->bootWorkflowActions($workflowService);
    }

    public function render()
    {
        return <<<'blade'
            <div>
                <!-- Dummy content for rendering -->
            </div>
        blade;
    }
}

class WorkflowActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LedgerDefine $ledgerDefine;
    protected Ledger $ledger;
    protected WorkflowService $workflowServiceMock;
    protected \App\Models\Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = \App\Models\Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create();

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $this->workflowServiceMock = $this->mock(WorkflowService::class);
        $this->app->instance(WorkflowService::class, $this->workflowServiceMock);
    }

    private function setupDefaultRenderMocks(bool $canRequest, bool $canApprove, bool $canReturn)
    {
        $this->workflowServiceMock->shouldReceive('canRequestApproval')->andReturn($canRequest);
        $this->workflowServiceMock->shouldReceive('canApprove')->andReturn($canApprove);
        $this->workflowServiceMock->shouldReceive('canReturnToDraft')->andReturn($canReturn);
    }

    #[Test]
    public function it_opens_approver_select_modal_when_can_request_approval()
    {
        $this->setupDefaultRenderMocks(true, false, false);
        $this->workflowServiceMock->shouldReceive('getFrequentAssignees')->once()->andReturn([]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openApproverSelectModal')
            ->assertDispatched('open-assignee-modal');
    }

    #[Test]
    public function it_does_not_open_approver_select_modal_when_cannot_request_approval()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openApproverSelectModal')
            ->assertNotDispatched('open-assignee-modal')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function it_handles_assignee_selected_for_request_approval()
    {
        $this->setupDefaultRenderMocks(true, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('selectedApproverId', null)
            ->call('handleAssigneeSelected', 1, 'approver')
            ->assertSet('selectedApproverId', 1)
            ->assertDispatched('open-workflow-comment-modal');
    }

    #[Test]
    public function it_opens_return_to_draft_modal_when_can_return_to_draft()
    {
        $this->setupDefaultRenderMocks(false, false, true);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openReturnToDraftModal')
            ->assertDispatched('open-workflow-comment-modal');
    }

    #[Test]
    public function it_does_not_open_return_to_draft_modal_when_cannot_return_to_draft()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openReturnToDraftModal')
            ->assertNotDispatched('open-workflow-comment-modal')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function it_opens_comment_modal_for_approve_task_when_can_approve()
    {
        $this->setupDefaultRenderMocks(false, true, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('approveTask')
            ->assertDispatched('open-workflow-comment-modal');
    }

    #[Test]
    public function it_does_not_open_comment_modal_for_approve_task_when_cannot_approve()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('approveTask')
            ->assertNotDispatched('open-workflow-comment-modal')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function it_executes_approve_action_with_comment_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->workflowServiceMock->shouldReceive('approve')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve')
            ->set('commentForModal', 'Test comment')
            ->call('executeActionWithComment')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_executes_return_to_draft_action_with_comment_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(false, false, true);
        $this->workflowServiceMock->shouldReceive('returnToDraft')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'return_to_draft')
            ->set('commentForModal', 'Return comment')
            ->call('executeActionWithComment')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_handles_workflow_action_with_comment_for_approve_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->ledger->define->folder->requiredApproverRoles = collect();
        $this->workflowServiceMock->shouldReceive('approve')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'approve', $this->ledger->id, 'Comment for approve')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_handles_workflow_action_with_comment_for_return_to_draft_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(false, false, true);
        $this->workflowServiceMock->shouldReceive('returnToDraft')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'return_to_draft', $this->ledger->id, 'Comment for return')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_handles_workflow_action_with_comment_for_request_approval_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(true, false, false);
        $this->workflowServiceMock->shouldReceive('requestApproval')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('selectedApproverId', 1)
            ->call('handleActionWithComment', 'request_approval_with_comment', $this->ledger->id, 'Comment for request')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function it_handles_next_approver_selected_and_dispatches_workflow_updated()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->workflowServiceMock->shouldReceive('approve')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve_and_select_next')
            ->set('commentForModal', 'Intermediate approve comment')
            ->call('handleNextApproverSelected', 2, 'approver')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }
}

<?php

namespace Tests\Unit\Traits;

use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkflowService;
use App\Traits\WorkflowActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(WorkflowActions::class)]
class WorkflowActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected WorkflowService $workflowServiceMock;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = Folder::factory()->create();
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

    // ================================================================
    // returnTaskToDraft
    // ================================================================

    #[Test]
    public function return_task_to_draft_succeeds_when_authorized()
    {
        $this->setupDefaultRenderMocks(false, false, true);
        $this->workflowServiceMock->shouldReceive('returnToDraft')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('returnComment', 'Drafting back')
            ->call('returnTaskToDraft')
            ->assertSet('returnToDraftModal', false)
            ->assertSet('returnComment', '')
            ->assertDispatched('workflowUpdated')
            ->assertDispatched('test-mary-toast-success');
    }

    #[Test]
    public function return_task_to_draft_fails_when_service_throws()
    {
        $this->setupDefaultRenderMocks(false, false, true);
        $this->workflowServiceMock->shouldReceive('returnToDraft')
            ->once()
            ->andThrow(new \Exception('Service error'));

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('returnComment', 'Fail')
            ->call('returnTaskToDraft')
            ->assertSet('returnComment', '')
            ->assertDispatched('test-mary-toast-error');
    }

    // ================================================================
    // loadApproverOptions
    // ================================================================

    #[Test]
    public function load_approver_options_populates_approver_options()
    {
        $this->setupDefaultRenderMocks(false, false, false);
        $approver = User::factory()->create(['name' => 'Approver One']);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('loadApproverOptions')
            ->assertSet('approverOptions', function ($options) use ($approver) {
                return collect($options)->pluck('id')->contains($approver->id);
            });
    }

    #[Test]
    public function load_approver_options_sets_recommended_approver()
    {
        $this->setupDefaultRenderMocks(false, false, false);
        $recommended = User::factory()->create(['name' => 'Recommended']);
        $this->ledgerDefine->update(['recommended_approver_id' => $recommended->id]);
        $this->ledger->refresh();

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('loadApproverOptions')
            ->assertSet('selectedApproverId', $recommended->id);
    }

    // ================================================================
    // openNextApproverSelectModal
    // ================================================================

    #[Test]
    public function open_next_approver_select_modal_dispatches_open_assignee_modal()
    {
        $this->setupDefaultRenderMocks(false, false, false);
        $this->workflowServiceMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve_and_select_next')
            ->call('openNextApproverSelectModal')
            ->assertDispatched('open-assignee-modal');
    }

    // ================================================================
    // handleAssigneeSelected — 追加パス
    // ================================================================

    #[Test]
    public function handle_assignee_selected_delegates_to_handle_next_approver_when_action_is_approve_and_select_next()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->workflowServiceMock->shouldReceive('approve')->once()->andReturn($this->ledger);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve_and_select_next')
            ->call('handleAssigneeSelected', 5, 'approver')
            ->assertDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_assignee_selected_rejects_non_approver_role()
    {
        $this->setupDefaultRenderMocks(true, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleAssigneeSelected', 5, 'inspector')
            ->assertDispatched('test-mary-toast-error');
    }

    // ================================================================
    // openCommentModal — 各アクションタイプ
    // ================================================================

    #[Test]
    public function open_comment_modal_dispatches_for_approve_action()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openCommentModal', 'approve')
            ->assertDispatched('open-workflow-comment-modal');
    }

    #[Test]
    public function open_comment_modal_dispatches_for_return_to_draft_action()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('openCommentModal', 'return_to_draft')
            ->assertDispatched('open-workflow-comment-modal');
    }

    #[Test]
    public function open_comment_modal_opens_approver_modal_when_no_approver_selected()
    {
        $this->setupDefaultRenderMocks(true, false, false);
        $this->workflowServiceMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('selectedApproverId', null)
            ->call('openCommentModal', 'request_approval_with_comment')
            ->assertDispatched('open-assignee-modal');
    }

    #[Test]
    public function open_comment_modal_dispatches_for_request_approval_when_approver_set()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('selectedApproverId', 1)
            ->call('openCommentModal', 'request_approval_with_comment')
            ->assertDispatched('open-workflow-comment-modal');
    }

    // ================================================================
    // getCommentModal系ヘルパー
    // ================================================================

    #[Test]
    public function get_comment_modal_title_returns_correct_titles()
    {
        $comp = Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve');
        $this->assertStringContainsString(__('ledger.workflow.approve'), $comp->get('actionTypeForModal') === 'approve'
            ? $comp->instance()->getCommentModalTitle()
            : '');

        $comp->set('actionTypeForModal', 'return_to_draft');
        $this->assertStringContainsString(__('ledger.workflow.return_to_draft'), $comp->instance()->getCommentModalTitle());

        $comp->set('actionTypeForModal', 'unknown');
        $this->assertEquals(__('ledger.workflow.comments'), $comp->instance()->getCommentModalTitle());
    }

    #[Test]
    public function get_comment_modal_action_label_returns_correct_labels()
    {
        $comp = Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger]);

        $comp->set('actionTypeForModal', 'approve');
        $this->assertEquals(__('ledger.workflow.approve'), $comp->instance()->getCommentModalActionLabel());

        $comp->set('actionTypeForModal', 'return_to_draft');
        $this->assertEquals(__('ledger.workflow.return_to_draft'), $comp->instance()->getCommentModalActionLabel());

        $comp->set('actionTypeForModal', 'other');
        $this->assertEquals('Execute', $comp->instance()->getCommentModalActionLabel());
    }

    #[Test]
    public function get_comment_modal_action_class_returns_correct_classes()
    {
        $comp = Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger]);

        $comp->set('actionTypeForModal', 'approve');
        $this->assertEquals('btn-primary', $comp->instance()->getCommentModalActionClass());

        $comp->set('actionTypeForModal', 'return_to_draft');
        $this->assertEquals('btn-warning', $comp->instance()->getCommentModalActionClass());

        $comp->set('actionTypeForModal', 'other');
        $this->assertEquals('btn-secondary', $comp->instance()->getCommentModalActionClass());
    }

    // ================================================================
    // handleActionWithComment — 各パス
    // ================================================================

    #[Test]
    public function handle_action_with_comment_ignores_different_ledger_id()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'approve', 99999, 'comment')
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_action_with_comment_skips_duplicate_request_approval_when_already_pending()
    {
        $this->setupDefaultRenderMocks(true, false, false);
        $this->ledger->update(['status' => WorkflowStatus::PENDING_APPROVAL]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'request_approval_with_comment', $this->ledger->id, 'comment')
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_action_with_comment_skips_duplicate_approve_when_already_approved()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->ledger->update(['status' => WorkflowStatus::APPROVED]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'approve', $this->ledger->id, 'comment')
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_action_with_comment_skips_duplicate_return_to_draft_when_already_draft()
    {
        $this->setupDefaultRenderMocks(false, false, true);
        $this->ledger->update(['status' => WorkflowStatus::DRAFT]);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'return_to_draft', $this->ledger->id, 'comment')
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_action_with_comment_throws_error_when_approve_unauthorized()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'approve', $this->ledger->id, 'comment')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function handle_action_with_comment_approve_opens_next_approver_modal_when_not_all_done()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->workflowServiceMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        // requiredApproverRolesが存在するフォルダ（承認が全部終わらない状況）
        $role = Role::firstOrCreate(['name' => 'approver_role', 'guard_name' => 'web']);
        $this->folder->requiredApproverRoles()->sync([$role->id]);

        // ユーザーにロールなし → allApprovalsWillBeDone = false
        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'approve', $this->ledger->id, 'comment')
            ->assertSet('actionTypeForModal', 'approve_and_select_next')
            ->assertDispatched('open-assignee-modal');
    }

    #[Test]
    public function handle_action_with_comment_return_to_draft_throws_when_unauthorized()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->call('handleActionWithComment', 'return_to_draft', $this->ledger->id, 'comment')
            ->assertDispatched('test-mary-toast-error');
    }

    #[Test]
    public function handle_action_with_comment_request_approval_throws_when_no_approver()
    {
        $this->setupDefaultRenderMocks(true, false, false);
        // selectedApproverId = null のまま

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('selectedApproverId', null)
            ->call('handleActionWithComment', 'request_approval_with_comment', $this->ledger->id, 'comment')
            ->assertDispatched('test-mary-toast-error');
    }

    // ================================================================
    // handleNextApproverSelected — エラーパス
    // ================================================================

    #[Test]
    public function handle_next_approver_selected_ignores_wrong_role_type()
    {
        $this->setupDefaultRenderMocks(false, true, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve_and_select_next')
            ->call('handleNextApproverSelected', 2, 'inspector') // 'approver'以外
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_next_approver_selected_ignores_wrong_action_type()
    {
        $this->setupDefaultRenderMocks(false, true, false);

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve') // 'approve_and_select_next'以外
            ->call('handleNextApproverSelected', 2, 'approver')
            ->assertNotDispatched('workflowUpdated');
    }

    #[Test]
    public function handle_next_approver_selected_dispatches_error_on_exception()
    {
        $this->setupDefaultRenderMocks(false, true, false);
        $this->workflowServiceMock->shouldReceive('approve')
            ->once()
            ->andThrow(new \Exception('Service error'));

        Livewire::test(DummyWorkflowComponentForTraitTest::class, ['ledgerRecord' => $this->ledger])
            ->set('actionTypeForModal', 'approve_and_select_next')
            ->call('handleNextApproverSelected', 2, 'approver')
            ->assertSet('commentForModal', '')
            ->assertSet('actionTypeForModal', '')
            ->assertDispatched('test-mary-toast-error');
    }
}

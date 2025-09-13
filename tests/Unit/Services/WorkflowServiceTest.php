<?php

namespace Tests\Unit\Services;

use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;
    protected bool $tenancy = true;

    private WorkflowService $workflowService;
    private User $user;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $notificationServiceMock = $this->mock(NotificationService::class);
        $notificationServiceMock->shouldReceive('processActivityLog')->zeroOrMoreTimes();

        $this->workflowService = new WorkflowService(
            $notificationServiceMock,
            $this->mock(UserService::class)
        );
    }

    private function partialMockLedger(?bool $canProceed, ?bool $canBeFinally): Ledger|MockInterface
    {
        $ledger = Ledger::factory()->make(['ledger_define_id' => $this->ledgerDefine->id]);

        $mock = $this->partialMock(get_class($ledger), function (MockInterface $mock) use ($canProceed, $canBeFinally) {
            if ($canProceed !== null) {
                $mock->shouldReceive('canProceedToApprovalStep')->andReturn($canProceed);
            }
            if ($canBeFinally !== null) {
                $mock->shouldReceive('canBeFinallyApproved')->andReturn($canBeFinally);
            }
            $mock->shouldReceive('setAttribute')->passthru();
            $mock->shouldReceive('setRelation')->passthru();
        });

        foreach ($ledger->getAttributes() as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }

    //<editor-fold desc="canRequestApproval Tests">

    public function test_canRequestApproval_returns_true_when_all_conditions_are_met(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertTrue($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_cannot_proceed(): void
    {
        $ledger = $this->partialMockLedger(false, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_user_is_not_inspector(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => User::factory()->create()->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_status_is_not_pending_inspection(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::DRAFT;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }
    //</editor-fold>

    //<editor-fold desc="canApprove Tests">

    public function test_canApprove_returns_true_when_user_is_approver_and_status_is_pending(): void
    {
        $ledger = $this->partialMockLedger(null, false);
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertTrue($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_true_when_it_can_be_finally_approved(): void
    {
        $ledger = $this->partialMockLedger(null, true);
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertTrue($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_false_when_all_conditions_are_false(): void
    {
        $ledger = $this->partialMockLedger(null, false);
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_false_when_status_is_draft(): void
    {
        $ledger = $this->partialMockLedger(null, true);
        $ledger->status = WorkflowStatus::DRAFT;
        $ledger->latestDiff = LedgerDiff::factory()->make(['ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canApprove($this->user, $ledger));
    }

    //</editor-fold>

    //<editor-fold desc="canReturnToDraft Tests">

    public function test_canReturnToDraft_returns_true_when_user_is_inspector_in_pending_inspection(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_INSPECTION, 'ledger_define_id' => $this->ledgerDefine->id]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertTrue($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_true_when_user_is_approver_in_pending_approval(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_APPROVAL, 'ledger_define_id' => $this->ledgerDefine->id]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => $this->user->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertTrue($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_false_when_user_is_not_the_assignee(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_APPROVAL, 'ledger_define_id' => $this->ledgerDefine->id]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id, 'ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_false_when_status_is_not_pending(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::DRAFT, 'ledger_define_id' => $this->ledgerDefine->id]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['ledger_define_id' => $this->ledgerDefine->id]);

        $this->assertFalse($this->workflowService->canReturnToDraft($this->user, $ledger));
    }
    //</editor-fold>
}
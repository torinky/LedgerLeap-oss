<?php

namespace tests\Unit\Services;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflowService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // NotificationService のモックを作成し、processActivityLog の呼び出しを許容する
        $notificationServiceMock = $this->mock(NotificationService::class);
        $notificationServiceMock->shouldReceive('processActivityLog')->zeroOrMoreTimes();

        // サービスをインスタンス化
        $this->workflowService = new WorkflowService(
            $notificationServiceMock,
            $this->mock(UserService::class)
        );

        // テストで共通して使用するユーザーを作成
        $this->user = User::factory()->create();
    }

    /**
     * Ledgerモデルの部分的なモックを作成するヘルパーメソッド
     *
     * @param bool|null $canProceed
     * @param bool|null $canBeFinally
     * @return Ledger|MockInterface
     */
    private function partialMockLedger(?bool $canProceed, ?bool $canBeFinally): Ledger|MockInterface
    {
        return $this->partialMock(Ledger::class, function (MockInterface $mock) use ($canProceed, $canBeFinally) {
            if ($canProceed !== null) {
                $mock->shouldReceive('canProceedToApprovalStep')->andReturn($canProceed);
            }
            if ($canBeFinally !== null) {
                $mock->shouldReceive('canBeFinallyApproved')->andReturn($canBeFinally);
            }
        });
    }

    //<editor-fold desc="canRequestApproval Tests">

    public function test_canRequestApproval_returns_true_when_all_conditions_are_met(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id]);

        $this->assertTrue($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_cannot_proceed(): void
    {
        $ledger = $this->partialMockLedger(false, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id]);

        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_user_is_not_inspector(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => User::factory()->create()->id]);

        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }

    public function test_canRequestApproval_returns_false_when_status_is_not_pending_inspection(): void
    {
        $ledger = $this->partialMockLedger(true, null);
        $ledger->status = WorkflowStatus::DRAFT; // Not PENDING_INSPECTION
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id]);

        // The logic checks for PENDING_INSPECTION or PENDING_APPROVAL
        $this->assertFalse($this->workflowService->canRequestApproval($this->user, $ledger));
    }
    //</editor-fold>

    //<editor-fold desc="canApprove Tests">

    public function test_canApprove_returns_true_when_user_is_approver_and_status_is_pending(): void
    {
        $ledger = $this->partialMockLedger(null, false); // canBeFinallyApproved is false
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => $this->user->id]);

        $this->assertTrue($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_true_when_it_can_be_finally_approved(): void
    {
        $ledger = $this->partialMockLedger(null, true); // canBeFinallyApproved is true
        $ledger->status = WorkflowStatus::PENDING_APPROVAL; // Status is not DRAFT or APPROVED
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id]); // User is not the assignee

        $this->assertTrue($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_false_when_all_conditions_are_false(): void
    {
        $ledger = $this->partialMockLedger(null, false); // canBeFinallyApproved is false
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id]); // User is not the assignee

        $this->assertFalse($this->workflowService->canApprove($this->user, $ledger));
    }

    public function test_canApprove_returns_false_when_status_is_draft(): void
    {
        $ledger = $this->partialMockLedger(null, true); // canBeFinallyApproved is true
        $ledger->status = WorkflowStatus::DRAFT; // Status is DRAFT
        $ledger->latestDiff = LedgerDiff::factory()->make();

        $this->assertFalse($this->workflowService->canApprove($this->user, $ledger));
    }

    //</editor-fold>

    //<editor-fold desc="canReturnToDraft Tests">

    public function test_canReturnToDraft_returns_true_when_user_is_inspector_in_pending_inspection(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_INSPECTION]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['inspector_id' => $this->user->id]);

        $this->assertTrue($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_true_when_user_is_approver_in_pending_approval(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_APPROVAL]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => $this->user->id]);

        $this->assertTrue($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_false_when_user_is_not_the_assignee(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::PENDING_APPROVAL]);
        $ledger->latestDiff = LedgerDiff::factory()->make(['approver_id' => User::factory()->create()->id]);

        $this->assertFalse($this->workflowService->canReturnToDraft($this->user, $ledger));
    }

    public function test_canReturnToDraft_returns_false_when_status_is_not_pending(): void
    {
        $ledger = Ledger::factory()->make(['status' => WorkflowStatus::DRAFT]);
        $ledger->latestDiff = LedgerDiff::factory()->make();

        $this->assertFalse($this->workflowService->canReturnToDraft($this->user, $ledger));
    }
    //</editor-fold>
}
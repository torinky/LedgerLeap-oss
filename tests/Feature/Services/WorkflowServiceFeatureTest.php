<?php

namespace Tests\Feature\Services;

use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(WorkflowService::class)]
class WorkflowServiceFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private WorkflowService $workflowService;

    private User $creator;

    private User $inspector;

    private User $approver;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->workflowService = app(WorkflowService::class);

        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $this->creator = User::factory()->create();
        $this->inspector = User::factory()->create();
        $this->approver = User::factory()->create();

        // Notification types are used in WorkflowService
        NotificationType::firstOrCreate(['name' => 'inspection_requested'], ['description' => '']);
        NotificationType::firstOrCreate(['name' => 'approval_requested'], ['description' => '']);
        NotificationType::firstOrCreate(['name' => 'inspection_completed'], ['description' => '']);
        NotificationType::firstOrCreate(['name' => 'approved'], ['description' => '']);
        NotificationType::firstOrCreate(['name' => 'status_returned_to_draft'], ['description' => '']);
        NotificationType::firstOrCreate(['name' => 'task_claimed'], ['description' => '']);
    }

    public function test_request_inspection_creates_diff_and_transitions_status(): void
    {
        Notification::fake();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
            'content' => [0 => 'test'],
        ]);

        $initialPendingCount = $this->inspector->refresh()->pending_inspection_count;

        $updatedLedger = $this->workflowService->requestInspection(
            $ledger->id,
            $this->creator->id,
            $this->inspector->id,
            'Please inspect this'
        );

        $this->assertEquals(WorkflowStatus::PENDING_INSPECTION, $updatedLedger->status);
        $this->assertEquals($this->creator->id, $updatedLedger->modifier_id);

        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertNotNull($latestDiff);
        $this->assertEquals(WorkflowStatus::PENDING_INSPECTION, $latestDiff->status);
        $this->assertEquals($this->inspector->id, $latestDiff->inspector_id);
        $this->assertEquals('Please inspect this', $latestDiff->comments);

        $this->assertEquals($initialPendingCount + 1, $this->inspector->refresh()->pending_inspection_count);
    }

    public function test_request_approval_creates_diff_and_transitions_status(): void
    {
        Notification::fake();

        $userServiceMock = $this->mock(\App\Services\UserService::class);
        $userServiceMock->shouldReceive('hasFolderPermission')->andReturn(true);
        $userServiceMock->shouldReceive('getAllUniqueRolesForUser')->andReturn(collect());
        app()->instance(\App\Services\UserService::class, $userServiceMock);
        $workflowService = app(WorkflowService::class);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'content' => [0 => 'test'],
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->inspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $initialPendingApprovalCount = $this->approver->refresh()->pending_approval_count;

        $updatedLedger = $workflowService->requestApproval(
            $ledger->id,
            $this->approver->id,
            $this->inspector->id,
            'Inspection completed'
        );

        $this->assertEquals(WorkflowStatus::PENDING_APPROVAL, $updatedLedger->status);
        $this->assertEquals($this->inspector->id, $updatedLedger->modifier_id);

        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertNotNull($latestDiff);
        $this->assertEquals(WorkflowStatus::PENDING_APPROVAL, $latestDiff->status);
        $this->assertEquals($this->approver->id, $latestDiff->approver_id);
        $this->assertEquals('Inspection completed', $latestDiff->comments);

        $this->assertEquals($initialPendingApprovalCount + 1, $this->approver->refresh()->pending_approval_count);
    }

    public function test_approve_creates_diff_and_transitions_status(): void
    {
        Notification::fake();

        $userServiceMock = $this->mock(\App\Services\UserService::class);
        $userServiceMock->shouldReceive('hasFolderPermission')->andReturn(true);
        $userServiceMock->shouldReceive('getAllUniqueRolesForUser')->andReturn(collect());
        app()->instance(\App\Services\UserService::class, $userServiceMock);
        $workflowService = app(WorkflowService::class);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
            'version' => 1,
            'content' => [0 => 'test'],
        ]);

        $inspectorRole = Role::create(['name' => 'inspector-role2']);
        $this->ledgerDefine->folder->requiredInspectorRoles()->attach($inspectorRole->id);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
            'approver_id' => $this->approver->id,
            'completed_inspector_role_ids' => [$inspectorRole->id],
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $updatedLedger = $workflowService->approve(
            $ledger->id,
            $this->approver->id,
            'Looks good to me'
        );

        $this->assertEquals(WorkflowStatus::APPROVED, $updatedLedger->status);
        $this->assertEquals($this->approver->id, $updatedLedger->modifier_id);

        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertEquals(WorkflowStatus::APPROVED, $latestDiff->status);
        $this->assertEquals('Looks good to me', $latestDiff->comments);
    }

    public function test_return_to_draft_creates_diff_and_transitions_status(): void
    {
        Notification::fake();

        $userServiceMock = $this->mock(\App\Services\UserService::class);
        $userServiceMock->shouldReceive('hasFolderPermission')->andReturn(true);
        $userServiceMock->shouldReceive('getAllUniqueRolesForUser')->andReturn(collect());
        app()->instance(\App\Services\UserService::class, $userServiceMock);
        $workflowService = app(WorkflowService::class);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'content' => [0 => 'test'],
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->inspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        // デクリメントのガード(0以下にならない)を回避するため、事前にカウントを1以上に設定する
        // $fillable に含まれないため、クエリビルダで直接更新する
        User::where('id', $this->inspector->id)->update(['pending_inspection_count' => 1]);
        $initialPendingCount = $this->inspector->refresh()->pending_inspection_count;

        $updatedLedger = $workflowService->returnToDraft(
            $ledger->id,
            $this->inspector->id,
            'Needs changes'
        );

        $this->assertEquals(WorkflowStatus::DRAFT, $updatedLedger->status);
        $this->assertEquals($this->inspector->id, $updatedLedger->modifier_id);
        $this->assertEquals($initialPendingCount - 1, $this->inspector->refresh()->pending_inspection_count);

        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertEquals(WorkflowStatus::DRAFT, $latestDiff->status);
        $this->assertEquals('Needs changes', $latestDiff->comments);
    }

    public function test_save_draft_creates_new_ledger_and_diff(): void
    {
        // カラムIDの数値をキーに使用する（LedgerDefineFactory のデフォルト定義では ID=0 のカラムが存在）
        $content = [0 => 'value1'];
        $contentAttached = ['files' => []];

        $result = $this->workflowService->saveDraft(
            null,
            $this->ledgerDefine->id,
            $content,
            $contentAttached,
            $this->creator->id
        );

        $ledger = $result['ledger'];
        $diff = $result['ledgerDiff'];

        // AsColumnArrayJson は array_values() を適用するため、保存後は数値インデックス配列になる
        $expectedContent = array_values($content);

        $this->assertInstanceOf(Ledger::class, $ledger);
        $this->assertEquals(WorkflowStatus::DRAFT, $ledger->status);
        $this->assertEquals($expectedContent, $ledger->content);
        $this->assertEquals(1, $ledger->version);
        $this->assertEquals($diff->id, $ledger->latest_diff_id);

        $this->assertEquals(WorkflowStatus::DRAFT, $diff->status);
        $this->assertEquals($expectedContent, $diff->content);
        $this->assertEquals(1, $diff->version);
    }

    public function test_save_draft_updates_existing_ledger_and_versions(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
            'content' => [0 => 'old_data'],
        ]);

        // カラムIDの数値をキーに使用する
        $newContent = [0 => 'new_data'];
        $result = $this->workflowService->saveDraft(
            $ledger->id,
            $this->ledgerDefine->id,
            $newContent,
            [],
            $this->creator->id
        );

        $updatedLedger = $result['ledger'];
        // AsColumnArrayJson は array_values() を適用するため、保存後は数値インデックス配列になる
        $expectedContent = array_values($newContent);
        $this->assertEquals(2, $updatedLedger->version);
        $this->assertEquals($expectedContent, $updatedLedger->content);
    }

    public function test_save_draft_fails_if_locked(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 1,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot save draft for an approved record.');

        $this->workflowService->saveDraft(
            $ledger->id,
            $this->ledgerDefine->id,
            ['some' => 'change'],
            [],
            $this->creator->id
        );
    }

    public function test_claim_task_updates_assignee_and_notifies(): void
    {
        Notification::fake();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->inspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $userServiceMock = $this->mock(\App\Services\UserService::class);
        $userServiceMock->shouldReceive('hasFolderPermission')->andReturn(true);
        $userServiceMock->shouldReceive('getAllUniqueRolesForUser')->andReturn(collect());
        app()->instance(\App\Services\UserService::class, $userServiceMock);
        $workflowService = app(WorkflowService::class);

        $updatedLedger = $workflowService->claimTask(
            $ledger,
            $this->approver,
            'I will take over this task'
        );

        $this->assertEquals($this->approver->id, $updatedLedger->modifier_id);
        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertEquals($this->approver->id, $latestDiff->inspector_id);
        $this->assertEquals('I will take over this task', $latestDiff->comments);
    }

    public function test_save_edited_record_transitions_back_to_draft(): void
    {
        Notification::fake();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'content' => [0 => 'old_data'],
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->inspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        // カラムIDの数値をキーに使用する
        $newContent = [0 => 'new_data'];
        $result = $this->workflowService->saveEditedRecord(
            $ledger->refresh(),
            $newContent,
            [],
            $this->creator->id,
            'Editing record'
        );

        $updatedLedger = $result['ledger'];
        // AsColumnArrayJson は array_values() を適用するため、保存後は数値インデックス配列になる
        $expectedContent = array_values($newContent);
        $this->assertEquals(WorkflowStatus::DRAFT, $updatedLedger->status);
        $this->assertEquals(2, $updatedLedger->version);
        $this->assertEquals($expectedContent, $updatedLedger->content);

        $latestDiff = $updatedLedger->latestDiff()->first();
        $this->assertEquals(WorkflowStatus::DRAFT, $latestDiff->status);
        $this->assertEquals('Editing record', $latestDiff->comments);
    }

    public function test_permission_check_methods(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->inspector->id,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        // canRequestApproval
        $this->assertTrue($this->workflowService->canRequestApproval($this->inspector, $ledger));
        $this->assertFalse($this->workflowService->canRequestApproval($this->approver, $ledger));

        // canApprove
        // NOTE: $ledger->status is PENDING_INSPECTION.
        // canApprove returns true if (PENDING_APPROVAL && approver_id == user_id) OR (canBeFinallyApproved && status not Draft/Approved)
        // Let's set it to PENDING_APPROVAL and matching approver_id
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;
        $ledger->save();
        $diff->update(['status' => WorkflowStatus::PENDING_APPROVAL, 'approver_id' => $this->approver->id]);
        $ledger->refresh();

        $this->assertTrue($this->workflowService->canApprove($this->approver, $ledger));

        // canReturnToDraft
        $this->assertTrue($this->workflowService->canReturnToDraft($this->approver, $ledger));
        $this->assertFalse($this->workflowService->canReturnToDraft($this->inspector, $ledger));
    }

    public function test_frequent_assignees_retrieval(): void
    {
        LedgerDiff::factory()->count(3)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->inspector->id,
        ]);
        LedgerDiff::factory()->count(1)->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->approver->id,
        ]);

        $frequent = $this->workflowService->getFrequentAssignees(
            $this->ledgerDefine->id,
            'inspector',
            10
        );

        $this->assertCount(2, $frequent);
        $this->assertEquals($this->inspector->id, $frequent[0]['id']);
        $this->assertEquals(3, $frequent[0]['count']);
    }

    public function test_task_counter_methods(): void
    {
        $user = User::factory()->create([
            'pending_inspection_count' => 5,
            'pending_approval_count' => 10,
        ]);

        $this->workflowService->incrementPendingTaskCount($user->id, 'inspection');
        $this->assertEquals(6, $user->refresh()->pending_inspection_count);

        $this->workflowService->decrementPendingTaskCount($user->id, 'approval');
        $this->assertEquals(9, $user->refresh()->pending_approval_count);
    }
}

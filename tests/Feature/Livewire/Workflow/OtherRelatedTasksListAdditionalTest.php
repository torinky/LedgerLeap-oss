<?php

namespace Tests\Feature\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\Workflow\OtherRelatedTasksList;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\UserService;
use App\Services\WorkflowService;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * OtherRelatedTasksList 追加テスト
 *
 * 既存 OtherRelatedTasksListTest との重複を避け、以下の未カバー箇所を検証する:
 * - canUserClaimTask (0%): ステータス・申請者・担当者・権限チェック
 * - claimTaskWithComment: Ledger not found パス
 */
#[CoversClass(OtherRelatedTasksList::class)]
class OtherRelatedTasksListAdditionalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

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
    }

    private function makeClaimingTaskData(int $ledgerId): array
    {
        return [
            'ledger_id' => $ledgerId,
            'ledger_title' => 'テスト台帳',
            'status_value' => WorkflowStatus::PENDING_INSPECTION->value,
            'status_label' => WorkflowStatus::PENDING_INSPECTION->label(),
            'status_color_class' => WorkflowStatus::PENDING_INSPECTION->colorClass(),
            'current_inspector_name' => null,
            'current_approver_name' => null,
            'applicant_name' => null,
            'ledger_updated_at' => now(),
            'ledger_created_at' => now(),
            'task_type' => 'claimable',
            'is_locked' => false,
            'required_roles_progress_summary' => null,
        ];
    }

    // ================================================================
    // claimTaskWithComment — Ledger not found パス
    // ================================================================

    #[Test]
    public function claim_task_with_comment_fails_when_ledger_not_found(): void
    {
        // ledger_id に存在しないIDを設定
        Livewire::test(OtherRelatedTasksList::class)
            ->set('claimingTaskData', $this->makeClaimingTaskData(99999))
            ->set('showClaimCommentModal', true)
            ->call('claimTaskWithComment')
            ->assertSet('showClaimCommentModal', false);
    }

    #[Test]
    public function claim_task_with_comment_handles_exception_gracefully(): void
    {
        $originalUser = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $originalUser->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('claimTask')
            ->once()
            ->andThrow(new \RuntimeException('Test exception'));

        $userServiceMock = $this->mock(UserService::class);
        $userServiceMock->shouldReceive('getClaimableTasks')->andReturn(collect());

        Livewire::test(OtherRelatedTasksList::class)
            ->set('claimingTaskData', $this->makeClaimingTaskData($ledger->id))
            ->set('showClaimCommentModal', true)
            ->call('claimTaskWithComment')
            ->assertSet('showClaimCommentModal', false);
    }

    #[Test]
    public function render_uses_task_tenant_id_for_ledger_links(): void
    {
        $tenantId = $this->getTenant()->id;

        $task = [
            'ledger_id' => 999,
            'tenant_id' => $tenantId,
            'ledger_title' => 'テスト台帳',
            'status_value' => WorkflowStatus::PENDING_INSPECTION->value,
            'status_label' => WorkflowStatus::PENDING_INSPECTION->label(),
            'status_color_class' => WorkflowStatus::PENDING_INSPECTION->colorClass(),
            'current_inspector_name' => null,
            'current_approver_name' => null,
            'applicant_name' => null,
            'ledger_updated_at' => now(),
            'ledger_created_at' => now(),
            'task_type' => 'claimable',
            'is_locked' => false,
            'required_roles_progress_summary' => null,
        ];

        Livewire::test(OtherRelatedTasksList::class)
            ->set('tasksData', collect([$task]))
            ->assertSeeHtml(route('ledger.show', [
                'tenant' => $tenantId,
                'ledgerId' => 999,
            ]));
    }

    // ================================================================
    // canUserClaimTask — 各条件の検証（protected メソッドを直接テスト）
    // ================================================================

    #[Test]
    public function can_user_claim_task_returns_false_when_not_workflow_pending(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::NONE, // ワークフロー対象外
        ]);

        $userServiceMock = $this->mock(UserService::class);
        $userServiceMock->shouldReceive('getClaimableTasks')->andReturn(collect());

        $component = new OtherRelatedTasksList;
        // boot でサービスを注射
        app()->call([$component, 'boot']);

        $result = $this->invokeProtectedMethod($component, 'canUserClaimTask', [$ledger, $this->user]);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_user_claim_task_returns_false_when_user_is_creator(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id, // 申請者本人
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $userServiceMock = $this->mock(UserService::class);
        $userServiceMock->shouldReceive('getClaimableTasks')->andReturn(collect());

        $component = new OtherRelatedTasksList;
        app()->call([$component, 'boot']);

        $result = $this->invokeProtectedMethod($component, 'canUserClaimTask', [$ledger, $this->user]);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_user_claim_task_returns_false_when_user_is_inspector(): void
    {
        $originalUser = User::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $originalUser->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->user->id, // 自分が点検者
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => [],
            'column_define' => [],
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);
        $ledger = $ledger->fresh()->load(['latestDiff']);

        $userServiceMock = $this->mock(UserService::class);
        $userServiceMock->shouldReceive('getClaimableTasks')->andReturn(collect());

        $component = new OtherRelatedTasksList;
        app()->call([$component, 'boot']);

        $result = $this->invokeProtectedMethod($component, 'canUserClaimTask', [$ledger, $this->user]);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_user_claim_task_returns_true_when_user_has_permission(): void
    {
        $originalUser = User::factory()->create();
        $inspector = User::factory()->create();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $originalUser->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $inspector->id, // 別のユーザーが担当
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => [],
            'column_define' => [],
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);
        $ledger = $ledger->fresh()->load(['latestDiff', 'define.folder']);

        $userServiceMock = $this->mock(UserService::class);
        $userServiceMock->shouldReceive('getClaimableTasks')->andReturn(collect());
        $userServiceMock->shouldReceive('hasFolderPermission')
            ->with($this->user, \Mockery::any(), FolderPermissionType::INSPECT)
            ->andReturn(true); // 権限あり

        $component = new OtherRelatedTasksList;
        app()->call([$component, 'boot']);

        $result = $this->invokeProtectedMethod($component, 'canUserClaimTask', [$ledger, $this->user]);

        $this->assertTrue($result);
    }

    // ================================================================
    // ヘルパー: protected メソッドを呼び出す
    // ================================================================

    private function invokeProtectedMethod(object $object, string $methodName, array $params = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $params);
    }
}

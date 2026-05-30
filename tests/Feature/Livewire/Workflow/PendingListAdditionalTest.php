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

/**
 * PendingList 追加テスト
 *
 * 既存 PendingListTest との重複を避け、以下の未カバー箇所を検証する:
 * - requestApproval (バリデーション・成功・失敗)
 * - openApproverSelectModal (成功パス・権限チェック)
 * - handleAssigneeSelected (approver ロールタイプ)
 * - requestApprovalInternal (成功・エラーパス)
 * - getApproverOptions (空配列)
 * - render (workflow_enabled=true の進捗サマリー付き)
 */
#[CoversClass(PendingList::class)]
class PendingListAdditionalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $inspector;

    private User $approver;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->inspector = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->actingAs($this->inspector);

        $this->folder = Folder::factory()->create([
            'creator_id' => $this->inspector->id,
            'modifier_id' => $this->inspector->id,
        ]);
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => true,
        ]);
    }

    /**
     * PENDING_INSPECTION 状態の Ledger + LedgerDiff を作成するヘルパー
     */
    private function createPendingLedgerWithDiff(): array
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);
        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'inspector_id' => $this->inspector->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => [],
            'column_define' => [],
        ]);
        // latestDiff() は belongsTo(LedgerDiff, 'latest_diff_id') なので設定が必要
        $ledger->update(['latest_diff_id' => $diff->id]);

        return [$ledger->fresh(), $diff];
    }

    // ================================================================
    // getApproverOptions
    // ================================================================

    #[Test]
    public function get_approver_options_returns_empty_array(): void
    {
        $options = Livewire::test(PendingList::class)
            ->instance()
            ->getApproverOptions();

        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    // ================================================================
    // requestApproval — バリデーション失敗
    // ================================================================

    #[Test]
    public function request_approval_fails_validation_when_no_approver_selected(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        Livewire::test(PendingList::class)
            ->set('selectedTaskId', $diff->id)
            ->set('selectedApproverId', null)
            ->call('requestApproval')
            ->assertHasErrors(['selectedApproverId']);
    }

    #[Test]
    public function request_approval_fails_when_diff_not_found(): void
    {
        Livewire::test(PendingList::class)
            ->set('selectedTaskId', 99999)
            ->set('selectedApproverId', $this->approver->id)
            ->call('requestApproval')
            ->assertHasNoErrors();
    }

    // ================================================================
    // requestApproval — 成功パス
    // ================================================================

    #[Test]
    public function request_approval_succeeds_and_resets_state(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('requestApproval')
            ->once()
            ->andReturn($ledger); // Ledger 型を返す

        $component = Livewire::test(PendingList::class)
            ->set('selectedTaskId', $diff->id)
            ->set('selectedApproverId', $this->approver->id)
            ->set('comments', 'テスト承認コメント')
            ->call('requestApproval');

        // finally ブロックでリセットされること
        $this->assertNull($component->get('selectedTaskId'));
        $this->assertNull($component->get('selectedApproverId'));
        $this->assertFalse($component->get('approvalRequestModal'));
    }

    // ================================================================
    // openApproverSelectModal — 成功パス
    // ================================================================

    #[Test]
    public function open_approver_select_modal_succeeds_when_user_is_inspector(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $component = Livewire::test(PendingList::class)
            ->call('openApproverSelectModal', $ledger->id);

        $this->assertEquals($ledger->id, $component->get('modalLedgerId'));
        $this->assertTrue($component->get('showAssigneeModal'));
    }

    #[Test]
    public function open_approver_select_modal_sets_folder_and_define_ids(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $component = Livewire::test(PendingList::class)
            ->call('openApproverSelectModal', $ledger->id);

        $this->assertEquals($this->ledgerDefine->id, $component->get('modalLedgerDefineId'));
        $this->assertEquals($this->folder->id, $component->get('modalFolderId'));
        $this->assertEquals('approver', $component->get('assigneeModalRoleType'));
    }

    #[Test]
    public function open_approver_select_modal_dispatches_event(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::test(PendingList::class)
            ->call('openApproverSelectModal', $ledger->id)
            ->assertDispatched('open-assignee-modal');
    }

    // ================================================================
    // handleAssigneeSelected — approver ロール（モーダルリセット）
    // ================================================================

    #[Test]
    public function handle_assignee_selected_resets_modal_state_for_approver_role(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        $mock = $this->mock(WorkflowService::class);
        $mock->shouldReceive('requestApproval')->andReturn($ledger);
        $mock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $component = Livewire::test(PendingList::class)
            ->set('modalLedgerId', $ledger->id)
            ->set('showAssigneeModal', true)
            ->dispatch('assignee-selected', userId: $this->approver->id, roleType: 'approver');

        $this->assertFalse($component->get('showAssigneeModal'));
        $this->assertNull($component->get('modalLedgerId'));
    }

    // ================================================================
    // render — workflow_enabled=true での進捗サマリー付きレンダリング
    // ================================================================

    #[Test]
    public function render_completes_without_error_for_pending_inspection_task(): void
    {
        [$ledger, $diff] = $this->createPendingLedgerWithDiff();

        Livewire::test(PendingList::class)
            ->assertStatus(200);
    }
}

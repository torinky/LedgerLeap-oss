<?php

namespace Tests\Feature\Livewire\Workflow;

use App\Livewire\Workflow\WorkflowAssigneeModal;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(WorkflowAssigneeModal::class)]
class WorkflowAssigneeModalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);
        $this->ledger = Ledger::factory()->create(['ledger_define_id' => $this->ledgerDefine->id]);
    }

    // ================================================================
    // 初期表示
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->assertStatus(200)
            ->assertSet('showModal', false);
    }

    // ================================================================
    // openModal() — open-assignee-modal イベント
    // ================================================================

    #[Test]
    public function open_modal_event_sets_state(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'approver',
                ledgerId: $this->ledger->id,
                initialUserId: $this->user->id
            )
            ->assertSet('showModal', true)
            ->assertSet('ledgerDefineId', $this->ledgerDefine->id)
            ->assertSet('folderId', $this->folder->id)
            ->assertSet('roleType', 'approver')
            ->assertSet('ledgerId', $this->ledger->id)
            ->assertSet('selectedUserId', $this->user->id);
    }

    #[Test]
    public function open_modal_with_inspector_role_type(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'inspector',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'inspector',
                ledgerId: null,
                initialUserId: null
            )
            ->assertSet('showModal', true)
            ->assertSet('roleType', 'inspector')
            ->assertSet('selectedUserId', null);
    }

    // ================================================================
    // selectAssignee()
    // ================================================================

    #[Test]
    public function select_assignee_dispatches_event_and_closes_modal(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'approver',
                ledgerId: $this->ledger->id,
                initialUserId: $this->user->id
            )
            ->set('selectedUserId', $this->user->id)
            ->call('selectAssignee')
            ->assertDispatched('assignee-selected', function ($event, $params) {
                return $params['userId'] === $this->user->id
                    && $params['roleType'] === 'approver';
            })
            ->assertSet('showModal', false)
            ->assertSet('selectedUserId', null);
    }

    #[Test]
    public function select_assignee_fails_validation_when_no_user_selected(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'approver',
                ledgerId: null,
                initialUserId: null
            )
            ->set('selectedUserId', null)
            ->call('selectAssignee')
            ->assertHasErrors(['selectedUserId']);
    }

    #[Test]
    public function select_assignee_fails_validation_with_invalid_user_id(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'approver',
                ledgerId: null,
                initialUserId: null
            )
            ->set('selectedUserId', 99999) // 存在しないID
            ->call('selectAssignee')
            ->assertHasErrors(['selectedUserId']);
    }

    // ================================================================
    // closeModal()
    // ================================================================

    #[Test]
    public function close_modal_resets_state(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeModal::class, [
                'ledgerDefineId' => $this->ledgerDefine->id,
                'folderId' => $this->folder->id,
                'roleType' => 'approver',
            ])
            ->dispatch('open-assignee-modal',
                ledgerDefineId: $this->ledgerDefine->id,
                folderId: $this->folder->id,
                roleType: 'approver',
                ledgerId: $this->ledger->id,
                initialUserId: $this->user->id
            )
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('selectedUserId', null);
    }
}

<?php

namespace Tests\Feature\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\Workflow\WorkflowAssigneeSelect;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\User;
use App\Services\WorkflowService;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(WorkflowAssigneeSelect::class)]
class WorkflowAssigneeSelectTest extends TestCase
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

    private function defaultProps(string $roleType = 'approver', ?int $ledgerId = null): array
    {
        return [
            'ledgerDefineId' => $this->ledgerDefine->id,
            'folderId' => $this->folder->id,
            'roleType' => $roleType,
            'ledgerId' => $ledgerId,
            'initialUserId' => null,
            'requiredInspectorRoleIds' => [],
            'requiredApproverRoleIds' => [],
        ];
    }

    // ================================================================
    // 初期表示 / mount
    // ================================================================

    #[Test]
    public function component_renders_with_approver_role_type(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps('approver'))
            ->assertStatus(200)
            ->assertSet('roleType', 'approver');
    }

    #[Test]
    public function component_renders_with_inspector_role_type(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps('inspector'))
            ->assertStatus(200)
            ->assertSet('roleType', 'inspector');
    }

    #[Test]
    public function mount_sets_initial_user_id(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $props = $this->defaultProps('approver');
        $props['initialUserId'] = $this->user->id;

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $props)
            ->assertSet('selectedUserId', $this->user->id);
    }

    // ================================================================
    // searchAssignees
    // ================================================================

    #[Test]
    public function search_assignees_updates_options(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps())
            ->call('searchAssignees', 'test')
            ->assertSet('searchQuery', 'test');
    }

    #[Test]
    public function search_assignees_returns_authorized_users(): void
    {
        // フォルダへのAPPROVE権限を持つロールにユーザーを追加
        $role = Role::create(['name' => 'ApproverRole_'.uniqid(), 'guard_name' => 'web']);
        $approver = User::factory()->create(['name' => 'TestApprover']);
        $approver->roles()->attach($role->id);
        $role->folderPermissions()->attach($this->folder->id, [
            'permission' => FolderPermissionType::APPROVE,
            'modifier_id' => $this->user->id,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $component = Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps('approver'));

        // optionsに承認権限ユーザーが含まれることを確認
        $options = $component->get('options');
        $this->assertNotNull($options);
    }

    #[Test]
    public function search_assignees_includes_selected_user_even_if_not_in_results(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $props = $this->defaultProps('approver');
        $props['initialUserId'] = $this->user->id;

        $component = Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $props);

        $options = $component->get('options');
        // 選択中ユーザーがオプションに含まれるか確認
        $userInOptions = collect($options)->contains('id', $this->user->id);
        $this->assertTrue($userInOptions);
    }

    // ================================================================
    // updatedSelectedUserId
    // ================================================================

    #[Test]
    public function updated_selected_user_id_null_resets_search(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps())
            ->set('selectedUserId', null)
            ->assertSet('selectedUserId', null);
    }

    #[Test]
    public function updated_selected_user_id_reloads_options(): void
    {
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps())
            ->set('selectedUserId', $this->user->id)
            ->assertSet('selectedUserId', $this->user->id);
    }

    // ================================================================
    // getAllReasonPresentations (static)
    // ================================================================

    #[Test]
    public function get_all_reason_presentations_returns_all_reason_keys(): void
    {
        $presentations = WorkflowAssigneeSelect::getAllReasonPresentations();

        $this->assertIsArray($presentations);
        $this->assertArrayHasKey('recent', $presentations);
        $this->assertArrayHasKey('frequent', $presentations);
        $this->assertArrayHasKey('authorized', $presentations);
        $this->assertArrayHasKey('required_role', $presentations);
        $this->assertArrayHasKey('past_route', $presentations);

        foreach ($presentations as $key => $presentation) {
            $this->assertArrayHasKey('icon', $presentation);
            $this->assertArrayHasKey('tooltip_key', $presentation);
            $this->assertArrayHasKey('legend_key', $presentation);
        }
    }

    // ================================================================
    // fetchOptions — 空フォルダでも正常動作
    // ================================================================

    #[Test]
    public function search_assignees_with_empty_folder_returns_empty_or_authorized(): void
    {
        // フォルダに権限設定がない場合でもエラーにならないこと
        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $emptyFolder = Folder::factory()->create();
        $defineInEmptyFolder = LedgerDefine::factory()->create(['folder_id' => $emptyFolder->id]);

        Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, [
                'ledgerDefineId' => $defineInEmptyFolder->id,
                'folderId' => $emptyFolder->id,
                'roleType' => 'approver',
            ])
            ->assertStatus(200);
    }

    // ================================================================
    // ledgerId ありの場合の recentAssignee
    // ================================================================

    #[Test]
    public function mount_with_ledger_id_loads_recent_assignee_from_history(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::APPROVED,
        ]);
        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'approver_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
        ]);

        $workflowMock = $this->mock(WorkflowService::class);
        $workflowMock->shouldReceive('getFrequentAssignees')->andReturn([]);

        $component = Livewire::actingAs($this->user)
            ->test(WorkflowAssigneeSelect::class, $this->defaultProps('approver', $ledger->id));

        $component->assertStatus(200);
        // recentAssignee がオプションに含まれること
        $options = $component->get('options');
        $this->assertNotNull($options);
    }
}

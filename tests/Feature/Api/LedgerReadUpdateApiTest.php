<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerReadUpdateApiTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $writerUser;

    private User $viewerUser;

    private User $inspectorUser;

    private Folder $folder;

    private LedgerDefine $workflowLedgerDefine;

    private LedgerDefine $directLedgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Queue::fake();

        config(['tenancy.central_domains' => ['127.0.0.1']]);
        if (! $this->getTenant()->domains()->where('domain', 'localhost')->exists()) {
            $this->getTenant()->domains()->create(['domain' => 'localhost']);
        }

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('view_ledgers', 'web');
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledgers']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo(['view_ledgers']);

        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);
        $this->inspectorUser = User::factory()->create()->assignRole($writerRole);

        $this->folder = Folder::factory()->create(['title' => 'API Folder']);

        RoleFolderPermission::create([
            'role_id' => $writerRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
        ]);

        $this->workflowLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => true,
        ]);

        $this->directLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => false,
        ]);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function it_returns_a_single_ledger_for_update_confirmation(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Current value'],
        ]);

        $response = $this->getJson(route('api.v1.ledgers.show', $ledger));

        $response->assertOk()
            ->assertJsonPath('data.id', $ledger->id)
            ->assertJsonPath('data.workflow.status', WorkflowStatus::NONE->value)
            ->assertJsonPath('data.workflow.editable', true)
            ->assertJsonPath('data.content_by_column_id.0', 'Current value')
            ->assertJsonPath('data.column_definitions.0.id', 0);
    }

    #[Test]
    public function user_without_read_permission_cannot_view_a_single_ledger(): void
    {
        $this->actingAs($this->viewerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'Current value'],
        ]);

        $response = $this->getJson(route('api.v1.ledgers.show', $ledger));

        $response->assertForbidden();
    }

    #[Test]
    public function it_updates_a_non_workflow_ledger_via_patch(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Before update'],
            'content_attached' => [],
        ]);

        $response = $this->patchJson(route('api.v1.ledgers.update', $ledger), [
            'content_patch' => [0 => 'After update'],
            'comment' => 'API patch update',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.workflow.status', WorkflowStatus::NONE->value)
            ->assertJsonPath('data.content_by_column_id.0', 'After update')
            ->assertJsonPath('meta.returned_to_draft', false);

        $ledger->refresh();

        $this->assertSame('After update', $ledger->content[0]);
        $this->assertSame(2, $ledger->version);
        $this->assertNotNull($ledger->latest_diff_id);
    }

    #[Test]
    public function it_returns_a_pending_inspection_ledger_to_draft_when_updated(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'content' => [0 => 'Before review fix'],
            'content_attached' => [],
        ]);

        $latestDiff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'content' => [0 => 'Before review fix'],
            'column_define' => $this->workflowLedgerDefine->column_define,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'inspector_id' => $this->inspectorUser->id,
            'requested_at' => now()->subHour(),
            'comments' => '確認して修正してください',
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);

        $ledger->update(['latest_diff_id' => $latestDiff->id]);

        $response = $this->patchJson(route('api.v1.ledgers.update', $ledger), [
            'content_patch' => [0 => 'After review fix'],
            'comment' => '差し戻し内容を反映',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.workflow.status', WorkflowStatus::DRAFT->value)
            ->assertJsonPath('data.workflow.returns_to_draft_on_save', false)
            ->assertJsonPath('data.content_by_column_id.0', 'After review fix')
            ->assertJsonPath('meta.previous_status', WorkflowStatus::PENDING_INSPECTION->value)
            ->assertJsonPath('meta.current_status', WorkflowStatus::DRAFT->value)
            ->assertJsonPath('meta.returned_to_draft', true);

        $ledger->refresh();

        $this->assertSame(WorkflowStatus::DRAFT, $ledger->status);
        $this->assertSame('After review fix', $ledger->content[0]);
        $this->assertNotSame($latestDiff->id, $ledger->latest_diff_id);
    }

    #[Test]
    public function it_rejects_updates_to_approved_ledgers(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 1,
            'content' => [0 => 'Approved content'],
        ]);

        $response = $this->patchJson(route('api.v1.ledgers.update', $ledger), [
            'content_patch' => [0 => 'Should fail'],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath(
                'message',
                'This ledger is approved and cannot be updated via the initial REST update contract.'
            );
    }

    #[Test]
    public function it_rejects_unknown_column_ids_in_content_patch(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
            'content' => [0 => 'Before update'],
        ]);

        $response = $this->patchJson(route('api.v1.ledgers.update', $ledger), [
            'content_patch' => [999 => 'Unknown column'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_patch']);
    }

    #[Test]
    public function it_rejects_tag_update_inputs_for_the_initial_rest_contract(): void
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
            'content' => [0 => 'Before update'],
        ]);

        $response = $this->patchJson(route('api.v1.ledgers.update', $ledger), [
            'content_patch' => [0 => 'After update'],
            'tag_operation' => 'replace',
            'tag_values' => ['urgent'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tag_operation', 'tag_values']);
    }
}

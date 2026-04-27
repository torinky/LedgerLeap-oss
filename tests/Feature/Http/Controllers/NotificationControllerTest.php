<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\WorkflowStatus;
use App\Http\Controllers\NotificationController;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Tenant;
use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(NotificationController::class)]
class NotificationControllerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    protected Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_index_returns_ok_for_global_route_and_renders_translation_headings(): void
    {
        $response = $this->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee(__('ledger.notifications'));
        $response->assertSee(__('ledger.workflow.pending_tasks'));
        $response->assertSee(__('ledger.activity.title'));
        $response->assertViewHas('activeTab', 'notifications');
    }

    #[Test]
    public function test_index_sets_active_tab_to_activity_when_tab_query_is_activity(): void
    {
        $response = $this->get(route('notifications.index', ['tab' => 'activity']));

        $response->assertOk();
        $response->assertViewHas('activeTab', 'activity');
    }

    #[Test]
    public function test_index_defaults_active_tab_to_tasks_when_user_has_pending_counts(): void
    {
        $this->user->forceFill([
            'pending_inspection_count' => 2,
            'pending_approval_count' => 1,
        ])->save();

        $response = $this->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewHas('activeTab', 'tasks');
    }

    #[Test]
    public function test_index_renders_workflow_links_with_tenant_ids_on_global_route(): void
    {
        $folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'folder_id' => $folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'workflow_enabled' => true,
        ]);

        $pendingLedger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $pendingDiff = LedgerDiff::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $pendingLedger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'inspector_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => [],
            'column_define' => [],
        ]);
        $pendingLedger->update(['latest_diff_id' => $pendingDiff->id]);

        $relatedLedger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
        $relatedDiff = LedgerDiff::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_id' => $relatedLedger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
            'inspector_id' => null,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'content' => [],
            'column_define' => [],
        ]);
        $relatedLedger->update(['latest_diff_id' => $relatedDiff->id]);

        $response = $this->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee(route('ledger.show', [
            'tenant' => $this->tenant->id,
            'ledgerId' => $pendingLedger->id,
        ]));
    }

    #[Test]
    public function workflow_pending_redirects_to_global_notifications_tasks_tab(): void
    {
        $response = $this->get(route('workflow.pending'));

        $response->assertRedirect(route('notifications.index', ['tab' => 'tasks']));
    }
}

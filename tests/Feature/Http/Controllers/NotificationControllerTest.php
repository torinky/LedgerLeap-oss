<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\NotificationController;
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
    public function test_index_returns_ok_for_tenant_route_and_renders_translation_headings(): void
    {
        $response = $this->get(route('notifications.index', ['tenant' => $this->tenant->id]));

        $response->assertOk();
        $response->assertSee(__('ledger.notifications'));
        $response->assertSee(__('ledger.workflow.pending_tasks'));
        $response->assertSee(__('ledger.activity.title'));
        $response->assertViewHas('activeTab', 'notifications');
    }

    #[Test]
    public function test_index_sets_active_tab_to_activity_when_tab_query_is_activity(): void
    {
        $response = $this->get(route('notifications.index', [
            'tenant' => $this->tenant->id,
            'tab' => 'activity',
        ]));

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

        $response = $this->get(route('notifications.index', ['tenant' => $this->tenant->id]));

        $response->assertOk();
        $response->assertViewHas('activeTab', 'tasks');
    }
}




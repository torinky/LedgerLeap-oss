<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\WorkflowActionButtons;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class WorkflowActionButtonsTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected WorkflowService $workflowServiceMock;

    protected \App\Models\Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = \App\Models\Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create();

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        $this->workflowServiceMock = $this->mock(WorkflowService::class);
        $this->app->instance(WorkflowService::class, $this->workflowServiceMock);
    }

    private function setupDefaultRenderMocks(bool $canRequest, bool $canApprove, bool $canReturn)
    {
        $this->workflowServiceMock->shouldReceive('canRequestApproval')->andReturn($canRequest);
        $this->workflowServiceMock->shouldReceive('canApprove')->andReturn($canApprove);
        $this->workflowServiceMock->shouldReceive('canReturnToDraft')->andReturn($canReturn);
    }

    #[Test]
    public function component_renders_successfully()
    {
        $this->setupDefaultRenderMocks(false, false, false);

        Livewire::test(WorkflowActionButtons::class, ['ledgerRecord' => $this->ledger])
            ->assertStatus(200)
            ->assertSee(__('ledger.action_bar_open'))
            ->assertSee(__('ledger.action_bar_close'));
    }
}

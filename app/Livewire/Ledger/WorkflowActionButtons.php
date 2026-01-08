<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Ledger;
use App\Services\WorkflowService;
use App\Traits\WorkflowActions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Mary\Traits\Toast;

class WorkflowActionButtons extends BaseLivewireComponent
{
    use AuthorizesRequests, InitializesTenantContext, Toast, WorkflowActions;

    public Ledger $ledgerRecord;

    public function boot(WorkflowService $workflowService)
    {
        $this->bootWorkflowActions($workflowService);
    }

    public function render()
    {
        return view('livewire.ledger.workflow-action-buttons');
    }
}

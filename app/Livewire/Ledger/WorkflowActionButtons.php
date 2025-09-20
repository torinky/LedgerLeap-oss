<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Services\WorkflowService;
use App\Traits\WorkflowActions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use App\Livewire\Traits\InitializesTenantContext;

class WorkflowActionButtons extends Component
{
    use AuthorizesRequests, Toast, WorkflowActions, InitializesTenantContext;

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
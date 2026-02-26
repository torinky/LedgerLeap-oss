<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Models\Ledger;
use App\Services\WorkflowService;
use App\Traits\WorkflowActions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

class WorkflowStatusCard extends BaseLivewireComponent
{
    use AuthorizesRequests, Toast, WorkflowActions;

    public Ledger $ledgerRecord;

    public function boot(WorkflowService $workflowService): void
    {
        $this->bootWorkflowActions($workflowService);
    }

    // #[Computed] プロパティとしてワークフロー履歴を定義
    #[Computed]
    public function workflowHistory(): Collection
    {
        return $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    // #[Computed] プロパティとして必須ロール進捗を定義
    #[Computed]
    public function requiredRolesProgress(): array
    {
        if ($this->ledgerRecord->define->workflow_enabled && $this->ledgerRecord->define?->folder) {
            return $this->ledgerRecord->getRequiredRolesProgressDetails();
        }

        return [];
    }

    public function render()
    {
        return view('livewire.ledger.workflow-status-card');
    }
}

<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class WorkflowHistoryList extends Component
{
    public Ledger $ledgerRecord;

    public Collection $workflowHistory;

    public function mount(): void
    {
        $this->loadWorkflowHistory();
    }

    protected function loadWorkflowHistory(): void
    {
        $this->workflowHistory = $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.ledger.workflow-history-list');
    }
}

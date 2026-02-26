<?php

namespace Tests\Unit\Traits;

use App\Models\Ledger;
use App\Services\WorkflowService;
use App\Traits\WorkflowActions;
use Livewire\Component;
use Mary\Traits\Toast;

// ダミーのLivewireコンポーネント
class DummyWorkflowComponentForTraitTest extends Component
{
    use Toast, WorkflowActions;

    public Ledger $ledgerRecord;

    public function mount(Ledger $ledgerRecord)
    {
        $this->ledgerRecord = $ledgerRecord;
    }

    public function boot(WorkflowService $workflowService): void
    {
        $this->bootWorkflowActions($workflowService);
    }

    public function render()
    {
        return <<<'blade'
            <div>
                <!-- Dummy content for rendering -->
            </div>
        blade;
    }
}

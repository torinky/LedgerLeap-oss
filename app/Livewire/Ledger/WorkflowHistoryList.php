<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Collection;

class WorkflowHistoryList extends BaseLivewireComponent
{
    public Ledger $ledgerRecord;

    public Collection $workflowHistory;

    public function mount(): void
    {
        $this->loadWorkflowHistory();
    }

    protected function loadWorkflowHistory(): void
    {
        $allHistory = $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // 連続する重複エントリをフィルタリング（バージョン、ステータス、操作者、コメントが同じ場合）
        $filtered = new Collection();
        $previous = null;

        // 逆順（古い順）に処理して、連続する重複の「最新」を残すようにする
        // または、降順のままで「次のエントリ」と比較する
        foreach ($allHistory as $current) {
            if ($previous === null) {
                $filtered->push($current);
                $previous = $current;
                continue;
            }

            $isDuplicate = $current->version === $previous->version &&
                           $current->status === $previous->status &&
                           $current->modifier_id === $previous->modifier_id &&
                           $current->comments === $previous->comments;

            if (!$isDuplicate) {
                $filtered->push($current);
                $previous = $current;
            }
        }

        $this->workflowHistory = $filtered;
    }

    public function render()
    {
        return view('livewire.ledger.workflow-history-list');
    }
}

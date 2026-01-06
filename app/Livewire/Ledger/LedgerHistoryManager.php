<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Attributes\On;

class LedgerHistoryManager extends Component
{
    public int $ledgerId;

    public int $historyDisplayLevel = 3;

    // ページング用
    public int $perPage = 10;
    public int $pageCount = 1;
    public bool $hasMore = true;

    // 比較対象
    public ?int $baseDiffId = null; // 基準（新しい方、通常は最新）
    public ?int $targetDiffId = null; // 比較対象（古い方）

    // 表示用データ
    public ?Ledger $ledgerRecord = null;
    
    // 表示状態の維持用
    public ?string $highlight = '';

    public function mount(int $ledgerId, int $displayLevel = 3, ?string $highlight = null, ?int $targetDiffId = null): void
    {
        $this->ledgerId = $ledgerId;
        $this->historyDisplayLevel = $displayLevel;
        $this->highlight = $highlight ?? '';
        $this->targetDiffId = $targetDiffId;
        
        $this->ledgerRecord = Ledger::findOrFail($this->ledgerId);
        
        // 最新の diff ID を取得
        $latestDiff = $this->ledgerRecord->ledgerDiff()->latest('id')->first();
        if ($latestDiff) {
            $this->baseDiffId = $latestDiff->id;
        }

        // 比較対象が明示されていない場合、プロセッサを使用してフォールバック先（直前など）を特定する
        if ($this->targetDiffId === null) {
            $target = app(\App\Services\Ledger\LedgerDiffProcessor::class)->findComparisonTargetDiff($this->ledgerRecord);
            if ($target) {
                $this->targetDiffId = $target->id;
            }
        }


        // 比較対象が指定されている場合、新しい方を base にする
        if ($this->baseDiffId && $this->targetDiffId && $this->baseDiffId < $this->targetDiffId) {
            $tmp = $this->baseDiffId;
            $this->baseDiffId = $this->targetDiffId;
            $this->targetDiffId = $tmp;
        }
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        if ($this->historyDisplayLevel !== $displayLevel) {
            $this->historyDisplayLevel = $displayLevel;
        }
    }

    public function updatedHistoryDisplayLevel(int $level): void
    {
        $this->dispatch('displayLevelUpdated', displayLevel: $level);
    }

    public function loadMore(): void
    {
        if (!$this->hasMore) return;
        
        $this->pageCount++;
    }

    // 比較対象を選択（トグル）する
    public function toggleSelection(int $id): void
    {
        if ($this->baseDiffId === $id) {
            $this->baseDiffId = null;
        } elseif ($this->targetDiffId === $id) {
            $this->targetDiffId = null;
        } else {
            // 新しく選択する場合
            if ($this->baseDiffId === null) {
                $this->baseDiffId = $id;
            } elseif ($this->targetDiffId === null) {
                $this->targetDiffId = $id;
            } else {
                // 両方埋まっている場合、targetDiffId を追い出して新しく選択
                $this->targetDiffId = $id;
            }
        }

        // ソート処理（常に大きい方を baseDiffId に、1つだけなら baseDiffId に寄せる）
        $ids = collect([$this->baseDiffId, $this->targetDiffId])->filter()->sortDesc()->values();
        
        $this->baseDiffId = $ids->get(0);
        $this->targetDiffId = $ids->get(1);

        $this->dispatch('versionsSelected', baseId: $this->baseDiffId, targetId: $this->targetDiffId);
    }

    public function render()
    {
        $diffsQuery = $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $totalCount = $diffsQuery->count();
        $diffs = $diffsQuery->take($this->perPage * $this->pageCount)->get();
        
        $this->hasMore = $diffs->count() < $totalCount;

        // 比較対象のデータを取得
        $baseDiff = $this->baseDiffId ? LedgerDiff::find($this->baseDiffId) : null;
        $targetDiff = $this->targetDiffId ? LedgerDiff::find($this->targetDiffId) : null;

        // メタ情報の準備
        $baseMeta = $baseDiff ? [
            'modifier_name' => $baseDiff->modifier?->name ?? '?',
            'updated_at' => $baseDiff->created_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $baseDiff->version,
            'comment' => $baseDiff->comment,
        ] : null;

        $targetMeta = $targetDiff ? [
            'modifier_name' => $targetDiff->modifier?->name ?? '?',
            'updated_at' => $targetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $targetDiff->version,
            'comment' => $targetDiff->comment,
        ] : null;

        return view('livewire.ledger.ledger-history-manager', [
            'history' => $diffs,
            'baseDiff' => $baseDiff,
            'targetDiff' => $targetDiff,
            'baseMeta' => $baseMeta,
            'targetMeta' => $targetMeta,
            'historyDisplayLevel' => $this->historyDisplayLevel,
        ]);
    }
}

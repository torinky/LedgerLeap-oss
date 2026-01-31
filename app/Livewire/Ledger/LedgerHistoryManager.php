<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\LogPerformance;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class LedgerHistoryManager extends BaseLivewireComponent
{
    use LogPerformance;

    public int $ledgerId;

    public int $historyDisplayLevel = 3;

    // ページング用
    public int $perPage = 10;

    public int $pageCount = 1;

    public bool $hasMore = true;

    // 比較対象 - これらは内部で変更されるため Reactive にはしません
    #[Url(as: 'bd')]
    public ?int $baseDiffId = null; // 基準（新しい方、通常は最新）

    #[Url(as: 'td')]
    public ?int $targetDiffId = null; // 比較対象（古い方）

    // 表示用データ
    public ?Ledger $ledgerRecord = null;

    // 表示状態の維持用
    public ?string $highlight = '';

    public bool $canRollback = false;

    public function mount(
        int $ledgerId,
        int $displayLevel = 3,
        ?string $highlight = null
    ): void {
        $startTime = microtime(true);

        $this->ledgerId = $ledgerId;
        $this->historyDisplayLevel = $displayLevel;
        $this->highlight = $highlight ?? '';

        $this->ledgerRecord = Ledger::findOrFail($this->ledgerId);

        // ロールバック権限の事前チェック (WRITE権限があればUIを表示)
        $folder = $this->ledgerRecord->define?->folder;
        $this->canRollback = false;
        if ($folder) {
            $userService = app(\App\Services\UserService::class);
            if ($userService) {
                $this->canRollback = $userService->hasFolderPermission(auth()->user(), $folder, \App\Enums\FolderPermissionType::WRITE);
            }
        }

        // 基準バージョンの決定
        // URL パラメータ (bd) から既にセットされている場合は何もしない
        if (! $this->baseDiffId) {
            // 指定がない場合、最新の diff ID を取得
            $latestDiff = $this->ledgerRecord->latestDiff;
            if ($latestDiff) {
                $this->baseDiffId = $latestDiff->id;
            }
        }

        // 比較対象が指定されている場合、新しい方を base にする（整合性維持のためのソート）
        if ($this->baseDiffId && $this->targetDiffId && $this->baseDiffId < $this->targetDiffId) {
            $tmp = $this->baseDiffId;
            $this->baseDiffId = $this->targetDiffId;
            $this->targetDiffId = $tmp;
        }

        $this->logPerformance('ledger_mount', (microtime(true) - $startTime) * 1000);
    }

    protected function getPerformanceContext(): array
    {
        return [
            'ledger_id' => $this->ledgerId,
        ];
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        // Reactive により不要になる可能性がありますが、
        // 他のイベントソースがある場合のために最小限で残します。
        $this->historyDisplayLevel = $displayLevel;
    }

    #[On('versionsSelected')]
    public function onVersionsSelected(?int $baseId, ?int $targetId): void
    {
        $this->baseDiffId = $baseId;
        $this->targetDiffId = $targetId;
    }

    public function updatedHistoryDisplayLevel(int $level): void
    {
        // 内部で変更された場合のみ必要
        $this->dispatch('displayLevelUpdated', displayLevel: $level);
    }

    public function loadMore(): void
    {
        $startTime = microtime(true);

        if (! $this->hasMore) {
            return;
        }

        $this->pageCount++;
        Log::debug("HistoryManager mount finished. base: $this->baseDiffId, target: $this->targetDiffId");

        $this->logPerformance('ledger_load_more', (microtime(true) - $startTime) * 1000);
    }

    #[On('ledger.rollback.completed')]
    public function onRollbackCompleted(): void
    {
        // ページネーションをリセットして最新を表示
        $this->pageCount = 1;
        $this->hasMore = true;

        // モデルをリフレッシュして最新情報を取得
        $this->ledgerRecord->refresh();

        // 最新のdiffを選択状態にする
        $latestDiff = $this->ledgerRecord->ledgerDiff()->latest('id')->first();
        if ($latestDiff) {
            $this->baseDiffId = $latestDiff->id;
            // 比較対象はリセット（または直前のバージョンにする？）
            $this->targetDiffId = null;
        }

        $this->dispatch('targetDiffIdUpdated', targetDiffId: null); // 他のコンポーネント（DiffViewer等）とも同期
    }

    public function rollback(int $diffId): void
    {
        // 確認モーダルを開くイベントをディスパッチ
        $this->dispatch('ledger.rollback.open-modal',
            ledgerId: $this->ledgerId,
            targetDiffId: $diffId,
            expectedVersion: $this->ledgerRecord->version
        );
    }

    public function toggleSelection(int $id): void
    {
        $startTime = microtime(true);

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

        $this->logPerformance('ledger_toggle_selection', (microtime(true) - $startTime) * 1000);
    }

    public function render()
    {
        $startTime = microtime(true);

        $diffsQuery = $this->ledgerRecord->ledgerDiff()
            ->with([
                'modifier.organizations',
                'inspector.organizations',
                'approver.organizations',
            ])
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
            'comment' => $baseDiff->comments,
        ] : null;

        $targetMeta = $targetDiff ? [
            'modifier_name' => $targetDiff->modifier?->name ?? '?',
            'updated_at' => $targetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $targetDiff->version,
            'comment' => $targetDiff->comments,
        ] : null;

        // コンテンツが完全に一致するかチェック
        $isContentIdentical = false;
        if ($targetDiff && $this->ledgerRecord) {
            $processor = app(\App\Services\Ledger\LedgerDiffProcessor::class);
            // 現在のレコード($this->ledgerRecord) と 比較対象($targetDiff) の差分を計算
            // prepareContentDiff は $ledgerRecord と $comparisonTargetDiff を比較する
            // ここでは「現在のレコード」と「ロールバック対象(targetDiff)」を比較したい
            // ロールバック対象の内容に「戻す」ということは、
            // 「現在のレコード」が「ロールバック対象」と同じになるということ。
            // つまり、diff がない = hasChangedColumns が false であれば一致している。
            $diffResult = $processor->prepareContentDiff($this->ledgerRecord, $targetDiff);
            $isContentIdentical = ! ($diffResult['hasChangedColumns'] ?? true);
        }

        $this->logPerformance('ledger_diff_render', (microtime(true) - $startTime) * 1000, [
            'diffs_count' => $diffs->count(),
            'has_more' => $this->hasMore,
        ]);

        return view('livewire.ledger.ledger-history-manager', [
            'history' => $diffs,
            'baseDiff' => $baseDiff,
            'targetDiff' => $targetDiff,
            'baseMeta' => $baseMeta,
            'targetMeta' => $targetMeta,
            'historyDisplayLevel' => $this->historyDisplayLevel,
            'canRollback' => $this->canRollback,
            'isContentIdentical' => $isContentIdentical,
        ]);
    }
}

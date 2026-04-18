<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

class Show extends BaseLivewireComponent
{
    use AuthorizesRequests, InitializesTenantContext, Toast;

    public bool $canView = false;

    public Ledger $ledgerRecord;

    public bool $canUpdate = false;

    public ?Collection $currentLedgerAttachments = null;

    #[Url(as: 'tab')]
    public string $selectedTab = 'details';

    /** @var array<int, string> */
    public array $loadedTabs = [];

    #[Url(as: 'dl')]
    public int $displayLevel = 3;

    #[Url(as: 'refresh')]
    public bool $refresh = false;

    #[Url(as: 'sc')]
    public bool $showChanges = false;

    #[Url(as: 'td')]
    public ?int $targetDiffId = null;

    #[Url(as: 'bd')]
    public ?int $baseDiffId = null;

    #[Url(as: 'highlight')]
    public ?string $highlight = null;

    /** 関連案件タブのバッジ件数（RelatedLedgers コンポーネントから通知） */
    public int $relatedCount = 0;

    public ?LedgerDiff $comparisonTargetDiffModel = null;

    /** @var array<int, \App\Models\Folder> */
    public array $breadcrumbs = [];

    public function isComparingWithPrevious(): bool
    {
        if (! $this->showChanges || ! $this->targetDiffId) {
            return false;
        }

        $currentDiff = $this->ledgerRecord->latestDiff;
        if (! $currentDiff) {
            return false;
        }

        $previousDiff = LedgerDiff::where('ledger_id', $this->ledgerRecord->id)
            ->where('version', '<', $currentDiff->version)
            ->orderByDesc('version')
            ->first();

        return $previousDiff && $this->targetDiffId === $previousDiff->id;
    }

    public function mount(int $ledgerId): void
    {
        // highlightは#[Url]属性により自動的にクエリパラメータから設定される
        // 明示的に取得する必要はない

        $this->ledgerRecord = Ledger::with([
            'define',
            'modifier:id,name,email,chat_link',
            'modifier.organizations',
            'creator:id,name,email,chat_link',
            'creator.organizations',
            'latestDiff.inspector:id,name',
            'latestDiff.approver:id,name',
        ])->findOrFail($ledgerId);

        $this->currentLedgerAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)->with('ledger')->withTrashed()->get();

        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord]);

        if (! in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
        }

        if ($this->refresh) {
            $this->js("localStorage.setItem('ledger_list_needs_refresh', Date.now());");
        }

        if ($this->targetDiffId) {
            $this->loadComparisonTarget();
        }

        // ── 関連案件タブの初期件数計算 ──────────────────────────────────────
        // RelatedLedgers は #[Lazy] のため、タブを開くまで render() が実行されない。
        // Show の mount() で識別番号 + 意味検索（RAG）の両方を先行実行してタブバッジを初期表示する。
        try {
            $relatedComponent = new RelatedLedgers;
            $relatedComponent->ledgerId = $this->ledgerRecord->id;

            $identifierKeys = $relatedComponent->extractAutoNumberValues($this->ledgerRecord);
            $identifierCollection = ! empty($identifierKeys)
                ? $relatedComponent->searchByIdentifiers($identifierKeys)
                : collect();

            // RAG が利用不可の場合は空コレクションを返す（グレースフルデグラデーション）
            // EmbeddingService のタイムアウトを防ぐため、rag.enabled が false の場合は検索をスキップする
            $semanticCollection = config('rag.enabled', false)
                ? $relatedComponent->searchBySemantic($this->ledgerRecord)
                : collect();

            $merged = $relatedComponent->mergeResults($identifierCollection, $semanticCollection);
            $this->relatedCount = count($merged);
        } catch (\Throwable $e) {
            // 件数計算の失敗はサイレントに無視（タブバッジが空のまま → タブを開いたときに更新）
        }

        // ── パンくずリストの取得 ──────────────────────────────────────
        $this->breadcrumbs = [];
        if ($this->ledgerRecord->define && $this->ledgerRecord->define->folder_id) {
            $folder = \App\Models\Folder::with('ancestors')->find($this->ledgerRecord->define->folder_id);
            if ($folder) {
                $this->breadcrumbs = $folder->ancestors->all();
                $this->breadcrumbs[] = $folder;
            }
        }

        if (empty($this->loadedTabs)) {
            $this->loadedTabs = [$this->selectedTab];
        }
    }

    #[On('workflowUpdated')]
    public function refreshLedgerRecord(): void
    {
        $this->mount($this->ledgerRecord->id);
    }

    #[On('navigate-to-ledger-tab')]
    public function navigateToTab(string $tab): void
    {
        $this->selectedTab = $tab;
        $this->markTabLoaded($tab);
    }

    #[On('relatedCountUpdated')]
    public function updateRelatedCount(int $count): void
    {
        $this->relatedCount = $count;
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        if ($this->displayLevel !== $displayLevel) {
            $this->displayLevel = $displayLevel;
        }
    }

    #[On('relatedDisplayLevelRequested')]
    public function syncRelatedDisplayLevel(int $displayLevel): void
    {
        if ($this->displayLevel !== $displayLevel) {
            $this->displayLevel = $displayLevel;
            $this->dispatch('displayLevelUpdated', displayLevel: $displayLevel);
        }
    }

    public function updatedDisplayLevel(int $level): void
    {
        // 履歴マネージャなどの非リアクティブコンポーネントのためにイベントを通知します
        $this->dispatch('displayLevelUpdated', displayLevel: $level);
    }

    public function setDisplayLevel(int $level): void
    {
        if (in_array($level, [1, 2, 3])) {
            $this->displayLevel = $level;
            $this->dispatch('displayLevelUpdated', displayLevel: $level);
        }
    }

    public function updatedShowChanges(bool $value): void
    {
        if ($value && ! $this->targetDiffId) {
            $this->activateCompareWithPrevious();
        }
        // 他のコンポーネントとの同期が必要な場合は dispatch を検討しますが、
        // 現状は Reactive または個別管理されているため最小限に留めます。
    }

    #[On('versionsSelected')]
    public function updateVersions(?int $baseId, ?int $targetId): void
    {
        // 基本情報タブの基準(base)は常に最新(null)を維持するため、baseId は無視する
        $this->targetDiffId = $targetId;
        $this->loadComparisonTarget();

        // 子コンポーネント（特に Reactive でない LedgerHistoryManager 等）との同期を確実にする
        $this->dispatch('targetDiffIdUpdated', targetDiffId: $targetId);
    }

    private function loadComparisonTarget(): void
    {
        if ($this->targetDiffId) {
            $this->comparisonTargetDiffModel = LedgerDiff::with([
                'modifier:id,name,email,chat_link',
                'modifier.organizations',
                'approver:id,name,email,chat_link',
                'approver.organizations',
            ])->find($this->targetDiffId);
        } else {
            $this->comparisonTargetDiffModel = null;
        }
    }

    public function activateCompareWithPrevious(): void
    {
        $this->showChanges = true;

        // 現在表示中のデータ（最新または選択中）の直前を特定する
        $currentDiff = $this->ledgerRecord->latestDiff;
        if ($currentDiff) {
            $previousDiff = LedgerDiff::where('ledger_id', $this->ledgerRecord->id)
                ->where('version', '<', $currentDiff->version)
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if ($previousDiff) {
                $this->baseDiffId = $currentDiff->id;
                $this->targetDiffId = $previousDiff->id;
                $this->loadComparisonTarget();
                // 履歴タブのステートと同期させるため
                $this->dispatch('versionsSelected', baseId: $this->baseDiffId, targetId: $this->targetDiffId);
            }
        }
    }

    #[On('switchToHistoryTab')]
    public function switchToHistoryTab(): void
    {
        $this->selectedTab = 'history';
        $this->markTabLoaded('history');
    }

    public function updatedSelectedTab(string $tab): void
    {
        $this->markTabLoaded($tab);
    }

    /**
     * Alpine.js のタブ切り替えから非同期で呼び出される。
     * UI の切り替えは Alpine 側が先行済みのため、
     * このメソッドは URL 同期と初回コンテンツ DOM 追加のみを担当する。
     */
    public function notifyTabChange(string $tab): void
    {
        $this->selectedTab = $tab;
        $this->markTabLoaded($tab);
    }

    protected function markTabLoaded(string $tab): void
    {
        if (! in_array($tab, $this->loadedTabs, true)) {
            $this->loadedTabs[] = $tab;
        }
    }

    public function isTabLoaded(string $tab): bool
    {
        return in_array($tab, $this->loadedTabs, true);
    }

    #[On('retryProcessingEvent')]
    #[On('retry-file-processing')]
    public function retryProcessing(?int $attachedFileId = null, ?int $fileId = null): void
    {
        // 両方のパラメータ名に対応
        $id = $attachedFileId ?? $fileId;

        if (! $id) {
            $this->addError('retryProcessing', __('ledger.uploadedFile.retry_failed'));

            return;
        }

        try {
            $attachedFile = AttachedFile::findOrFail($id);
            $attachedFile->retryProcessing();
            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', title: __('ledger.uploadedFile.retry_success'), type: 'success');
            } else {
                $this->success(__('ledger.uploadedFile.retry_success'));
            }
        } catch (\Exception $e) {
            Log::error("AttachedFile retryProcessing failed for ID: {$id}. Error: ".$e->getMessage());
            $this->addError('retryProcessing', __('ledger.uploadedFile.retry_failed'));
        }
        $this->mount($this->ledgerRecord->id);
    }

    public function deleteAttachedFile(int $fileId): void
    {
        try {
            $attachedFile = AttachedFile::findOrFail($fileId);
            $attachedFile->delete();
            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', title: __('file.delete_success'), type: 'success');
            } else {
                $this->success(__('file.delete_success'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete attached file: '.$e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', title: __('file.delete_failed'), type: 'error');
            } else {
                $this->error(__('file.delete_failed'));
            }
        }
        $this->mount($this->ledgerRecord->id);
    }

    public function notifyCopySuccess(): void
    {
        if (app()->runningUnitTests()) {
            $this->dispatch('mary-toast', title: __('ledger.vlm.copied'), type: 'success');
        } else {
            $this->success(__('ledger.vlm.copied'));
        }
    }

    public function notifyCopyFailed(): void
    {
        if (app()->runningUnitTests()) {
            $this->dispatch('mary-toast', title: __('ledger.vlm.copy_failed'), type: 'error');
        } else {
            $this->error(__('ledger.vlm.copy_failed'));
        }
    }

    #[On('ledger.rollback.completed')]
    public function handleRollbackCompleted(int $ledgerId, ?int $targetDiffId = null): void
    {
        if ($this->ledgerRecord->id !== $ledgerId) {
            return;
        }

        // 最新情報を再読み込み
        $this->mount($ledgerId);

        // UI状態の自動更新: 詳細タブへ戻り、差分表示を有効化する
        $this->selectedTab = 'details';
        $this->showChanges = true;

        if ($targetDiffId) {
            // ロールバック元のバージョンとの比較を設定
            $this->targetDiffId = $targetDiffId;
            $this->loadComparisonTarget();
            $this->dispatch('targetDiffIdUpdated', targetDiffId: $this->targetDiffId);

            $currentDiff = $this->ledgerRecord->latestDiff;
            if ($currentDiff) {
                $this->dispatch('versionsSelected', baseId: $currentDiff->id, targetId: $this->targetDiffId);
            }
        } else {
            // 直前バージョンとの比較を自動設定 (フォールバック)
            $this->activateCompareWithPrevious();
        }

        $this->dispatch('showChangesUpdated', showChanges: true);
    }

    public function render()
    {
        return view('livewire.ledger.show', [
            'ledgerDefineRecord' => $this->ledgerRecord->define,
        ])->layout('layouts.app');
    }
}

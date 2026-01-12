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

    #[Url(as: 'dl')]
    public int $displayLevel = 3;

    #[Url(as: 'refresh')]
    public bool $refresh = false;

    #[Url(as: 'sc')]
    public bool $showChanges = false;

    #[Url(as: 'td')]
    public ?int $targetDiffId = null;

    #[Url(as: 'highlight')]
    public ?string $highlight = null;

    public ?LedgerDiff $comparisonTargetDiffModel = null;

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
            'modifier:id,name,email',
            'modifier.organizations',
            'creator:id,name,email',
            'creator.organizations',
            'latestDiff.inspector:id,name',
            'latestDiff.approver:id,name',
        ])->findOrFail($ledgerId);

        $this->currentLedgerAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)->get();

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
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        if ($this->displayLevel !== $displayLevel) {
            $this->displayLevel = $displayLevel;
        }
    }

    public function updatedDisplayLevel(int $level): void
    {
        // LedgerDiffViewer に displayLevel の変更を通知するイベントを発火
        $this->dispatch('displayLevelUpdated', displayLevel: $level);
    }

    public function setDisplayLevel(int $level): void
    {
        if (in_array($level, [1, 2, 3])) {
            $this->displayLevel = $level;
            // LedgerDiffViewer に displayLevel の変更を通知するイベントを発火
            $this->dispatch('displayLevelUpdated', displayLevel: $level);
        }
    }

    public function updatedShowChanges(bool $value): void
    {
        if ($value && ! $this->targetDiffId) {
            $this->activateCompareWithPrevious();
        }
        $this->dispatch('showChangesUpdated', showChanges: $value);
    }

    #[On('versionsSelected')]
    public function updateVersions(?int $baseId, ?int $targetId): void
    {
        // $baseId は通常最新(最新のdiffId)を想定しているが、
        // 詳細タブで表示するのは基本的に「現在」との比較なので、targetId を反映する。
        $this->targetDiffId = $targetId;
        $this->loadComparisonTarget();
        $this->dispatch('targetDiffIdUpdated', targetDiffId: $targetId);
    }

    private function loadComparisonTarget(): void
    {
        if ($this->targetDiffId) {
            $this->comparisonTargetDiffModel = LedgerDiff::with([
                'modifier:id,name,email',
                'modifier.organizations',
                'approver:id,name,email',
                'approver.organizations'
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
                ->first();

            if ($previousDiff) {
                $this->targetDiffId = $previousDiff->id;
                $this->loadComparisonTarget();
                $this->dispatch('targetDiffIdUpdated', targetDiffId: $this->targetDiffId);
                // 履歴タブのステートと同期させるため、baseIdとtargetIdをディスパッチ
                $this->dispatch('versionsSelected', baseId: $currentDiff->id, targetId: $previousDiff->id);
            }
        }
        $this->dispatch('showChangesUpdated', showChanges: true);
    }

    #[On('switchToHistoryTab')]
    public function switchToHistoryTab(): void
    {
        $this->selectedTab = 'history';
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
            $this->success(__('ledger.uploadedFile.retry_success'));
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
        $this->success(__('ledger.vlm.copied'));
    }

    public function notifyCopyFailed(): void
    {
        $this->error(__('ledger.vlm.copy_failed'));
    }

    public function render()
    {
        return view('livewire.ledger.show', [
            'ledgerDefineRecord' => $this->ledgerRecord->define,
        ])->layout('layouts.app');
    }
}

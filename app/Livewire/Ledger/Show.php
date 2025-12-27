<?php

namespace App\Livewire\Ledger;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use AuthorizesRequests, InitializesTenantContext, Toast;

    public bool $canView = false;

    public Ledger $ledgerRecord;

    public bool $canUpdate = false;

    public ?Collection $currentLedgerAttachments = null;

    public string $selectedTab = 'details';

    #[Url(as: 'dl')]
    public int $displayLevel = 3;

    #[Url(as: 'refresh')]
    public bool $refresh = false;

    #[Url(as: 'highlight')]
    public ?string $highlight = null;

    public bool $showVlmModal = false;

    public ?int $previewingFileId = null;

    public function mount(int $ledgerId): void
    {
        // highlightは#[Url]属性により自動的にクエリパラメータから設定される
        // 明示的に取得する必要はない

        $this->ledgerRecord = Ledger::with([
            'define',
            'modifier:id,name',
            'creator:id,name',
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
    }

    #[On('workflowUpdated')]
    public function refreshLedgerRecord(): void
    {
        $this->mount($this->ledgerRecord->id);
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

    #[On('retryProcessingEvent')]
    public function retryProcessing(int $attachedFileId): void
    {
        try {
            $attachedFile = AttachedFile::findOrFail($attachedFileId);
            $attachedFile->retryProcessing();
            $this->success(__('ledger.uploadedFile.retry_success'));
        } catch (\Exception $e) {
            Log::error("AttachedFile retryProcessing failed for ID: {$attachedFileId}. Error: ".$e->getMessage());
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

    #[Computed]
    public function previewingFile(): ?AttachedFile
    {
        if (! $this->previewingFileId) {
            return null;
        }

        return AttachedFile::find($this->previewingFileId);
    }

    #[On('showVlmPreviewEvent')]
    public function showVlmPreview(int $fileId): void
    {
        $file = AttachedFile::find($fileId);

        if (! $file || ! $file->hasVlmResult()) {
            $this->dispatch('mary-toast', title: __('ledger.vlm.result_not_found'), type: 'error');

            return;
        }

        $this->previewingFileId = $fileId;
        $this->showVlmModal = true;
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

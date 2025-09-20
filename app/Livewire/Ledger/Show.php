<?php

namespace App\Livewire\Ledger;

use App\Models\AttachedFile;
use App\Models\Ledger;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;
use App\Livewire\Traits\InitializesTenantContext;

class Show extends Component
{
    use AuthorizesRequests, Toast, InitializesTenantContext;

    public bool $canView = false;
    public Ledger $ledgerRecord;

    public bool $canUpdate = false;

    public ?Collection $currentLedgerAttachments = null;
    public string $selectedTab = 'details';

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    public ?string $highlight = null;

    public function mount(int $ledgerId): void
    {
        $this->highlight = request()->query('highlight');

        $this->ledgerRecord = Ledger::with([
            'define',
            'modifier:id,name',
            'creator:id,name',
            'latestDiff.inspector:id,name',
            'latestDiff.approver:id,name',
        ])->findOrFail($ledgerId);

        $this->currentLedgerAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)->get();

        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord]);

        if (!in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
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
            $this->success(__('file.status.retry_success'));
        } catch (\Exception $e) {
            Log::error("AttachedFile retryProcessing failed for ID: {$attachedFileId}. Error: " . $e->getMessage());
            $this->addError('retryProcessing', __('file.status.retry_failed'));
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

    public function render()
    {
        return view('livewire.ledger.show', [
            'ledgerDefineRecord' => $this->ledgerRecord->define,
        ])->layout('layouts.app');
    }
}

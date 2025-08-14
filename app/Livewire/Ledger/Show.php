<?php

namespace App\Livewire\Ledger;

use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use AuthorizesRequests, Toast;

    public bool $canView = false;
    public Ledger $ledgerRecord;
    protected $ledgerDefineRecord; // Changed to protected
    public bool $canUpdate = false;

    // --- 差分表示用 ---
    public ?LedgerDiff $comparisonTargetDiff = null;
    public array $contentChanges = [];
    public bool $hasChangedColumns = false;
    public bool $showChanges = false;

    protected LedgerContentProcessor $ledgerContentProcessor;
    protected LedgerDiffProcessor $ledgerDiffProcessor;

    public ?Collection $currentLedgerAttachments = null;
    public string $selectedTab = 'details';

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    public ?string $highlight = null;

    public array $collapsedStates = [];
    public array $filteredColumns = [];
    public array $displayColumns = [];

    public function boot(LedgerContentProcessor $ledgerContentProcessor, LedgerDiffProcessor $ledgerDiffProcessor): void
    {
        $this->ledgerContentProcessor = $ledgerContentProcessor;
        $this->ledgerDiffProcessor = $ledgerDiffProcessor;
    }

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

        $this->ledgerDefineRecord = $this->ledgerRecord->define;
        $this->ledgerDefineRecord->refresh(); // Ensure column_define is loaded

        $this->currentLedgerAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)->get();
        $this->prepareContentDiff();

        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord]);

        if (!in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
        }

        $this->filteredColumns = $this->calculateFilteredColumns();

        $allGroups = collect($this->ledgerDefineRecord->column_define)
            ->pluck('group')
            ->filter()
            ->unique()
            ->toArray();

        foreach ($allGroups as $groupName) {
            $this->collapsedStates[$groupName] = false;
        }
        $this->collapsedStates[__('ledger.form.group_default')] = false;

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $columnObject = is_array($column) ? new \App\Models\ColumnDefine($column) : $column;
            if ($columnObject->required) {
                $groupName = $columnObject->group ?? __('ledger.form.group_default');
                $this->collapsedStates[$groupName] = false;
            }
        }
    }

    #[On('workflowUpdated')]
    public function refreshLedgerRecord(): void
    {
        $this->mount($this->ledgerRecord->id);
    }

    public function updatedDisplayLevel(int $level): void
    {
        $this->filteredColumns = $this->calculateFilteredColumns();
    }

    public function setDisplayLevel(int $level): void
    {
        if (in_array($level, [1, 2, 3])) {
            $this->displayLevel = $level;
            $this->filteredColumns = $this->calculateFilteredColumns();
        }
    }

    protected function calculateFilteredColumns(): array
    {
        if (empty($this->ledgerDefineRecord) || empty($this->ledgerDefineRecord->column_define)) {
            return [];
        }

        return collect($this->ledgerDefineRecord->column_define)
            ->filter(function ($column) {
                $columnDisplayLevel = is_array($column) ? ($column['display_level'] ?? 3) : ($column->display_level ?? 3);
                return $columnDisplayLevel <= $this->displayLevel;
            })
            ->sortBy(function($column) {
                return is_array($column) ? $column['order'] : $column->order;
            })
            ->map(function ($column) {
                // ColumnDefine オブジェクトまたは配列から必要なプロパティを抽出して新しい配列を作成
                $columnArray = is_array($column) ? $column : (
                    method_exists($column, 'toArray') ? $column->toArray() : (array) $column
                );
                return [
                    'id' => $columnArray['id'] ?? null,
                    'name' => $columnArray['name'] ?? null,
                    'type' => $columnArray['type'] ?? null,
                    'order' => $columnArray['order'] ?? null,
                    'useOptions' => $columnArray['useOptions'] ?? false,
                    'options' => $columnArray['options'] ?? [],
                    'required' => $columnArray['required'] ?? false,
                    'unique' => $columnArray['unique'] ?? false,
                    'sortBy' => $columnArray['sortBy'] ?? false,
                    'hint' => $columnArray['hint'] ?? '',
                    'file' => $columnArray['file'] ?? [],
                    'display_level' => $columnArray['display_level'] ?? 3,
                    'group' => $columnArray['group'] ?? '',
                ];
            })
            ->all();
    }

    public function toggleGroup(string $groupName): void
    {
        if (!isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = false;
        }
        $this->collapsedStates[$groupName] = !$this->collapsedStates[$groupName];
    }

    protected function prepareContentDiff(): void
    {
        $this->comparisonTargetDiff = $this->ledgerDiffProcessor->findComparisonTargetDiff($this->ledgerRecord);
        $diffResult = $this->ledgerDiffProcessor->prepareContentDiff(
            $this->ledgerRecord,
            $this->ledgerDefineRecord,
            $this->comparisonTargetDiff
        );
        $this->contentChanges = $diffResult['contentChanges'];
        $this->hasChangedColumns = $diffResult['hasChangedColumns'];
    }

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
        $groupedColumns = collect($this->filteredColumns)
            ->groupBy(function ($column) {
                $group = is_array($column) ? ($column['group'] ?? '') : ($column->group ?? '');
                return $group === '' ? __('ledger.form.group_default') : $group;
            })
            ->sortBy(function ($columns, $groupName) {
                if ($columns->isNotEmpty()) {
                    $firstColumn = $columns->first();
                    return is_array($firstColumn) ? ($firstColumn['order'] ?? PHP_INT_MAX) : ($firstColumn->order ?? PHP_INT_MAX);
                }
                return $groupName;
            });

        $this->displayColumns = $this->ledgerContentProcessor->processContentForDisplay(
            $this->ledgerRecord,
            $this->ledgerDefineRecord,
            $this->highlight
        );

        return view('livewire.ledger.show', [
            'groupedColumns' => $groupedColumns,
            'filteredColumns' => $this->filteredColumns,
            'displayColumns' => $this->displayColumns,
            'ledgerDefineRecord' => $this->ledgerDefineRecord,
        ])->layout('layouts.app');
    }
}

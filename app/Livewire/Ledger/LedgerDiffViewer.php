<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class LedgerDiffViewer extends Component
{
    public Ledger $ledgerRecord;

    // --- 差分表示用プロパティ ---
    public ?LedgerDiff $comparisonTargetDiff = null;
    public array $contentChanges = [];
    public bool $hasChangedColumns = false;
    public bool $showChanges = false;

    // Show.php から渡されるプロパティ
    public bool $canView = false;
    public ?EloquentCollection $currentLedgerAttachments = null;
    public ?string $highlight = null;
    public array $collapsedStates = []; // グループの開閉状態 (LedgerDiffViewer 自身で管理)
    public ?Collection $groupedColumns = null; // 表示用のグループ化されたカラム (Illuminate\Support\Collection)
    public int $displayLevel = 1;

    protected LedgerDiffProcessor $ledgerDiffProcessor;

    public function boot(LedgerDiffProcessor $ledgerDiffProcessor): void
    {
        $this->ledgerDiffProcessor = $ledgerDiffProcessor;
    }

    public function mount(): void
    {
        $this->prepareContentDiff();
        $this->updateGroupedColumns(); // groupedColumns の初期化
    }

    // displayLevel の変更を検知して groupedColumns を更新する
    public function updatedDisplayLevel(int $level): void
    {
        $this->updateGroupedColumns();
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevelFromParent(int $displayLevel): void
    {
        $this->displayLevel = $displayLevel;
        $this->updateGroupedColumns();
    }

    // collapsedStates は LedgerDiffViewer 自身で管理するため、このメソッドは不要になる
    // #[On('collapsedStatesUpdated')]
    // public function updateCollapsedStatesFromParent(array $collapsedStates): void
    // {
    //     $this->collapsedStates = $collapsedStates;
    // }

    // グループの開閉を LedgerDiffViewer 自身で管理するメソッド
    public function toggleGroup(string $groupName): void
    {
        if (!isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = false;
        }
        $this->collapsedStates[$groupName] = !$this->collapsedStates[$groupName];
    }

    protected function updateGroupedColumns(): void
    {
        // collapsedStates の初期化ロジックをここに移動
        if (empty($this->collapsedStates)) { // 初回のみ初期化
            $allGroups = collect($this->ledgerRecord->define->column_define)
                ->pluck('group')
                ->filter()
                ->unique()
                ->toArray();

            foreach ($allGroups as $groupName) {
                $this->collapsedStates[$groupName] = false;
            }
            $this->collapsedStates[__('ledger.form.group_default')] = false;

            foreach ($this->ledgerRecord->define->column_define as $column) {
                $columnObject = is_array($column) ? new \App\Models\ColumnDefine($column) : $column;
                if ($columnObject->required) {
                    $groupName = $columnObject->group ?? __('ledger.form.group_default');
                    $this->collapsedStates[$groupName] = false;
                }
            }
        }

        $this->groupedColumns = collect($this->ledgerRecord->define->column_define)
            ->filter(function ($column) {
                $columnDisplayLevel = is_array($column) ? ($column['display_level'] ?? 3) : ($column->display_level ?? 3);
                return $columnDisplayLevel <= $this->displayLevel;
            })
            ->map(function ($column) {
                if (is_array($column)) {
                    return $column;
                }
                return [
                    'id' => $column->id ?? null,
                    'name' => $column->name ?? null,
                    'type' => $column->type ?? null,
                    'order' => $column->order ?? null,
                    'useOptions' => $column->useOptions ?? false,
                    'options' => $column->options ?? [],
                    'required' => $column->required ?? false,
                    'unique' => $column->unique ?? false,
                    'sortBy' => $column->sortBy ?? false,
                    'hint' => $column->hint ?? '',
                    'file' => $column->file ?? [],
                    'display_level' => $column->display_level ?? 3,
                    'group' => $column->group ?? '',
                ];
            })
            ->groupBy(function ($column) {
                $group = $column['group'] ?? '';
                return $group === '' ? __('ledger.form.group_default') : $group;
            })
            ->sortBy(function ($columns, $groupName) {
                if ($columns->isNotEmpty()) {
                    $firstColumn = $columns->first();
                    return $firstColumn['order'] ?? PHP_INT_MAX;
                }
                return $groupName;
            });
    }

    protected function prepareContentDiff(): void
    {
        $this->comparisonTargetDiff = $this->ledgerDiffProcessor->findComparisonTargetDiff($this->ledgerRecord);
        $diffResult = $this->ledgerDiffProcessor->prepareContentDiff(
            $this->ledgerRecord,
            $this->ledgerRecord->define,
            $this->comparisonTargetDiff
        );
        $this->contentChanges = $diffResult['contentChanges'];
        $this->hasChangedColumns = $diffResult['hasChangedColumns'];
    }

    public function render()
    {
        return view('livewire.ledger.ledger-diff-viewer');
    }
}
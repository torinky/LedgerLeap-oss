<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Livewire\Traits\InitializesTenantContext;

class LedgerDiffViewer extends Component
{
    use InitializesTenantContext;

    public Ledger $ledgerRecord;

    // --- 差分表示用プロパティ ---
    public ?LedgerDiff $comparisonTargetDiff = null;
    public bool $hasChangedColumns = false;
    public bool $showChanges = false;

    // Show.php から渡されるプロパティ
    public bool $canView = false;
    public ?EloquentCollection $allAttachments = null;
    public ?string $highlight = null;
    public int $displayLevel = 3;

    // 表示データ
    public array $displayData = [];

    // グループの開閉状態
    public array $collapsedStates = [];

    protected LedgerContentProcessor $ledgerContentProcessor;
    protected LedgerDiffProcessor $ledgerDiffProcessor; // comparisonTargetDiff の取得にのみ使用

    public function boot(
        LedgerContentProcessor $ledgerContentProcessor,
        LedgerDiffProcessor $ledgerDiffProcessor
    ): void {
        $this->ledgerContentProcessor = $ledgerContentProcessor;
        $this->ledgerDiffProcessor = $ledgerDiffProcessor;
    }

    public function mount(): void
    {
        // 差分比較対象と添付ファイルコレクションを準備
        $this->comparisonTargetDiff = $this->ledgerDiffProcessor->findComparisonTargetDiff($this->ledgerRecord);
        $this->allAttachments = $this->ledgerRecord->attachedFiles->keyBy('hashedbasename');

        // グループの開閉状態を初期化
        $this->initializeCollapsedStates();
    }

    // 親コンポーネントから displayLevel が更新されたときに呼ばれる
    #[On('displayLevelUpdated')]
    public function updateDisplayLevelFromParent(int $displayLevel): void
    {
        $this->displayLevel = $displayLevel;
        // render() が自動的に再実行され、新しい displayLevel でデータが再計算される
    }

    // グループの開閉状態をトグルする
    public function toggleGroup(string $groupName): void
    {
        if (isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = !$this->collapsedStates[$groupName];
        }
    }

    // グループの開閉状態を初期化する
    protected function initializeCollapsedStates(): void
    {
        if (empty($this->collapsedStates)) {
            $allGroups = collect($this->ledgerRecord->define->column_define)
                ->pluck('group')
                ->filter()
                ->unique()
                ->push(__('ledger.form.group_default')) // デフォルトグループを追加
                ->unique();

            foreach ($allGroups as $groupName) {
                $this->collapsedStates[$groupName] = false;
            }

            // 必須項目を含むグループはデフォルトで開く
            foreach ($this->ledgerRecord->define->column_define as $column) {
                $columnObject = is_array($column) ? new \App\Models\ColumnDefine($column) : $column;
                if ($columnObject->required) {
                    $groupName = $columnObject->group ?? __('ledger.form.group_default');
                    $this->collapsedStates[$groupName] = false; // falseは「開いている」状態
                }
            }
        }
    }

    public function render()
    {
        // LedgerContentProcessor を呼び出して表示データを取得
        $result = $this->ledgerContentProcessor->processContentForDisplay(
            $this->ledgerRecord,
            $this->comparisonTargetDiff,
            $this->displayLevel,
            $this->allAttachments,
            $this->highlight
        );

        $this->displayData = $result['displayData'];
        $this->hasChangedColumns = $result['hasChangedColumns'];

        // ビューに渡すバージョン情報
        $currentVersion = $this->ledgerRecord->version;
        $pastVersion = $this->comparisonTargetDiff ? $this->comparisonTargetDiff->version : null;

        return view('livewire.ledger.ledger-diff-viewer', [
            'currentVersion' => $currentVersion,
            'pastVersion' => $pastVersion,
        ]);
    }

    public function placeholder(): string
    {
        return '<div>Loading diff viewer...</div>';
    }
}

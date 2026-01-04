<?php

namespace App\Livewire\Ledger;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\On;
use Livewire\Component;

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

    public ?array $baseMeta = null;

    public ?array $targetMeta = null;

    public ?int $targetDiffId = null;

    public function mount(?LedgerDiff $comparisonTargetDiff = null, ?array $baseMeta = null, ?array $targetMeta = null, ?int $targetDiffId = null): void
    {
        $this->baseMeta = $baseMeta;
        $this->targetMeta = $targetMeta;
        $this->targetDiffId = $targetDiffId;

        // 差分比較対象を準備
        if ($this->targetDiffId) {
            $this->comparisonTargetDiff = LedgerDiff::with(['modifier:id,name'])->find($this->targetDiffId);
        } elseif ($comparisonTargetDiff) {
            $this->comparisonTargetDiff = $comparisonTargetDiff;
        } else {
            $this->comparisonTargetDiff = app(LedgerDiffProcessor::class)->findComparisonTargetDiff($this->ledgerRecord);
        }

        if ($this->comparisonTargetDiff && ! $this->targetMeta) {
            $this->targetMeta = [
                'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                'version' => $this->comparisonTargetDiff->version,
            ];
        }

        $this->allAttachments = $this->ledgerRecord->attachedFiles ? $this->ledgerRecord->attachedFiles->keyBy('hashedbasename') : collect();
    }

    // 親コンポーネントから displayLevel が更新されたときに呼ばれる
    #[On('displayLevelUpdated')]
    public function updateDisplayLevelFromParent(int $displayLevel): void
    {
        $this->displayLevel = $displayLevel;
    }

    #[On('showChangesUpdated')]
    public function updateShowChangesFromParent(bool $showChanges): void
    {
        $this->showChanges = $showChanges;
    }

    #[On('targetDiffIdUpdated')]
    public function updateTargetDiffIdFromParent(?int $targetDiffId): void
    {
        $this->targetDiffId = $targetDiffId;
        if ($this->targetDiffId) {
            $this->comparisonTargetDiff = LedgerDiff::with(['modifier:id,name'])->find($this->targetDiffId);
            if ($this->comparisonTargetDiff) {
                $this->targetMeta = [
                    'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                    'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                    'version' => $this->comparisonTargetDiff->version,
                ];
            }
        } else {
            $this->comparisonTargetDiff = app(LedgerDiffProcessor::class)->findComparisonTargetDiff($this->ledgerRecord);
            $this->targetMeta = null;
        }
    }

    // グループの開閉状態をトグルする (Alpine 移行のため PHP 側は廃止検討だが、暫定維持または削除)
    // 今回は Alpine に移行するため削除し、ビュー側で Alpine.store を使う方式にする

    // グループの初期状態（必須項目があるかどうか）を取得する
    protected function getRequiredGroups(): array
    {
        return collect(optional($this->ledgerRecord->define)->column_define ?? [])
            ->filter(fn ($column) => is_array($column) ? ($column['required'] ?? false) : ($column->required ?? false))
            ->map(fn ($column) => is_array($column) ? ($column['group'] ?? __('ledger.form.group_default')) : ($column->group ?? __('ledger.form.group_default')))
            ->unique()
            ->values()
            ->toArray();
    }

    public function render()
    {
        // LedgerContentProcessor を呼び出して表示データを取得
        $result = app(LedgerContentProcessor::class)->processContentForDisplay(
            $this->ledgerRecord,
            $this->comparisonTargetDiff,
            $this->displayLevel,
            $this->allAttachments,
            $this->highlight
        );

        $this->displayData = $result['displayData'];
        $this->hasChangedColumns = $result['hasChangedColumns'];

        // ビューに渡すバージョン情報
        $baseMeta = $this->baseMeta ?? [
            'modifier_name' => $this->ledgerRecord->modifier?->name ?? '?',
            'updated_at' => $this->ledgerRecord->updated_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $this->ledgerRecord->version,
        ];
        $targetMeta = $this->targetMeta;

        $currentVersion = $baseMeta['version'];
        $pastVersion = $targetMeta['version'] ?? ($this->comparisonTargetDiff ? $this->comparisonTargetDiff->version : null);

        return view('livewire.ledger.ledger-diff-viewer', [
            'currentVersion' => $currentVersion,
            'pastVersion' => $pastVersion,
            'baseMeta' => $baseMeta,
            'targetMeta' => $targetMeta,
            'requiredGroups' => $this->getRequiredGroups(),
        ]);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
    <div  class="z-50 fixed inset-0 bg-base-300/50 transition-opacity">
        <div class="flex h-screen justify-center items-center">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

HTML;
    }
}

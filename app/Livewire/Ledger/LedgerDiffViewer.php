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

    public ?\Illuminate\Support\Collection $allAttachments = null;

    public ?string $highlight = null;

    public int $displayLevel = 3;

    // 表示データ
    public array $displayData = [];

    public ?array $baseMeta = null;

    public ?array $targetMeta = null;

    public ?int $targetDiffId = null;

    public ?int $baseDiffId = null;

    public bool $useFallback = true;

    public function mount(Ledger $ledgerRecord, ?LedgerDiff $comparisonTargetDiff = null, ?array $baseMeta = null, ?array $targetMeta = null, ?int $targetDiffId = null, ?int $baseDiffId = null, bool $useFallback = true): void
    {
        $this->ledgerRecord = $ledgerRecord;
        
        $this->baseMeta = $baseMeta;
        $this->targetMeta = $targetMeta;
        $this->targetDiffId = $targetDiffId;
        $this->baseDiffId = $baseDiffId;
        $this->useFallback = $useFallback;

        // 差分比較対象を準備
        // Livewire 3 の DI により空のインスタンスが渡されることがあるため exists を確認
        if ($this->targetDiffId) {
            $this->comparisonTargetDiff = LedgerDiff::with(['modifier:id,name'])->find($this->targetDiffId);
        } elseif ($comparisonTargetDiff && $comparisonTargetDiff->exists) {
            $this->comparisonTargetDiff = $comparisonTargetDiff;
        } elseif ($this->useFallback) {
            $this->comparisonTargetDiff = app(LedgerDiffProcessor::class)->findComparisonTargetDiff($this->ledgerRecord, $this->baseDiffId);
        }
        
        if ($this->comparisonTargetDiff && ! $this->targetMeta) {
            $this->targetMeta = [
                'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                'version' => $this->comparisonTargetDiff->version,
            ];
        }

        if ($this->allAttachments === null && $this->ledgerRecord->relationLoaded('attachedFiles')) {
            $this->allAttachments = $this->ledgerRecord->attachedFiles;
        }

        if ($this->allAttachments !== null && !($this->allAttachments instanceof \Illuminate\Support\Collection && $this->allAttachments->hasAny($this->allAttachments->keys()->toArray()))) {
             // すでにキーイングされているかチェックするのは難しいので、単純にキーイングする
             $this->allAttachments = $this->allAttachments->keyBy('hashedbasename');
        }
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
            if ($this->useFallback) {
                $this->comparisonTargetDiff = app(LedgerDiffProcessor::class)->findComparisonTargetDiff($this->ledgerRecord, $this->baseDiffId);
            } else {
                $this->comparisonTargetDiff = null;
            }
            $this->targetMeta = null;
        }
    }

    // グループの開閉状態をトグルする (Alpine 移行のため PHP 側は廃止検討だが、暫定維持または削除)
    // 今回は Alpine に移行するため削除し、ビュー側で Alpine.store を使う方式にする

    #[On('versionsSelected')]
    public function updateBaseAndTargetFromParent(?int $baseId, ?int $targetId): void
    {
        $this->baseDiffId = $baseId;
        $this->targetDiffId = $targetId;
        
        // メタ情報のリセット（renderで再生成されるか、あるいはここで取得）
        // シンプルにするため、render時に ID があれば取得するように調整する
        $this->baseMeta = null;
        $this->targetMeta = null;
        $this->comparisonTargetDiff = null;
    }

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
        // データの準備（ID が指定されているがモデルがない場合）
        if ($this->baseDiffId && (!isset($this->baseMeta) || $this->baseMeta === null)) {
            $baseDiff = LedgerDiff::with(['modifier:id,name'])->find($this->baseDiffId);
            if ($baseDiff) {
                $this->baseMeta = [
                    'modifier_name' => $baseDiff->modifier?->name ?? '?',
                    'updated_at' => $baseDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                    'version' => $baseDiff->version,
                ];
            }
        }

        if ($this->targetDiffId && (!$this->comparisonTargetDiff || $this->comparisonTargetDiff->id !== $this->targetDiffId)) {
            $this->comparisonTargetDiff = LedgerDiff::with(['modifier:id,name'])->find($this->targetDiffId);
            if ($this->comparisonTargetDiff) {
                $this->targetMeta = [
                    'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                    'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                    'version' => $this->comparisonTargetDiff->version,
                ];
            }
        }

        // attachments が準備されていない場合のフォールバック（履歴タブなど）
        if ($this->allAttachments === null || $this->allAttachments->isEmpty()) {
            $this->allAttachments = \App\Models\AttachedFile::where('ledger_id', $this->ledgerRecord->id)->get()->keyBy('hashedbasename');
        } elseif (!$this->allAttachments->first() instanceof \App\Models\AttachedFile || !is_string($this->allAttachments->keys()->first())) {
            // キーイングされていない可能性があるため、強制的に再キーイング
            $this->allAttachments = $this->allAttachments->keyBy('hashedbasename');
        }

        // LedgerContentProcessor を呼び出して表示データを取得
        // もし baseDiffId が指定されている場合は、その時点の内容を base とする必要があるが、
        // 現状の processContentForDisplay は $ledgerRecord (最新) を前提としている。
        // LedgerDiffProcessor を介して、任意の2つの Diff を比較するロジックが必要。
        
        $result = app(LedgerContentProcessor::class)->processContentForDisplay(
            $this->ledgerRecord,
            $this->comparisonTargetDiff,
            $this->displayLevel,
            $this->allAttachments,
            $this->highlight,
            $this->baseDiffId // 第6引数として追加（後で processor も修正する）
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

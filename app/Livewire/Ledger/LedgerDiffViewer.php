<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerContentProcessor;
use App\Services\Ledger\LedgerDiffProcessor;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

class LedgerDiffViewer extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public Ledger $ledgerRecord;

    // --- 差分表示用プロパティ ---
    public ?LedgerDiff $comparisonTargetDiff = null;

    public bool $hasChangedColumns = false;

    #[Reactive]
    public bool $showChanges = false;

    // Show.php から渡されるプロパティ
    #[Reactive]
    public bool $canView = false;

    public ?\Illuminate\Support\Collection $allAttachments = null;

    #[Reactive]
    public ?string $highlight = null;

    public int $displayLevel = 3;

    // 表示データ
    public array $displayData = [];

    public ?array $baseMeta = null;

    public ?array $targetMeta = null;

    #[Reactive]
    public ?int $targetDiffId = null;

    #[Reactive]
    public ?int $baseDiffId = null;

    public bool $useFallback = true;

    public bool $showInduction = true;

    public function mount(
        Ledger $ledgerRecord,
        ?LedgerDiff $comparisonTargetDiff = null,
        ?array $baseMeta = null,
        ?array $targetMeta = null,
        ?int $targetDiffId = null,
        ?int $baseDiffId = null,
        bool $useFallback = true,
        bool $showInduction = true,
        ?\Illuminate\Support\Collection $allAttachments = null
    ): void {
        $this->ledgerRecord = $ledgerRecord;
        $this->allAttachments = $allAttachments;

        // テナントIDの確実な設定
        $this->tenantId = $this->tenantId ?? $this->ledgerRecord->tenant_id;

        $this->baseMeta = $baseMeta;
        $this->targetMeta = $targetMeta;
        // #[Reactive] プロパティへの代入を削除。これらは親から渡される値が自動設定されます。
        $this->useFallback = $useFallback;
        $this->showInduction = $showInduction;

        // 差分比較対象を準備
        // Livewire 3 の DI により空のインスタンスが渡されることがあるため exists を確認
        if ($this->targetDiffId) {
            $this->comparisonTargetDiff = LedgerDiff::with([
                'modifier:id,name,email,chat_link',
                'modifier.organizations',
                'approver:id,name,email,chat_link',
                'approver.organizations',
            ])->find($this->targetDiffId);
        } elseif ($comparisonTargetDiff && $comparisonTargetDiff->exists) {
            $this->comparisonTargetDiff = $comparisonTargetDiff;
        } elseif ($this->useFallback) {
            $this->comparisonTargetDiff = app(LedgerDiffProcessor::class)
                ->findComparisonTargetDiff($this->ledgerRecord, $this->baseDiffId);
        }

        if ($this->comparisonTargetDiff && ! $this->targetMeta) {
            $this->targetMeta = [
                'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                'version' => $this->comparisonTargetDiff->version,
                'status' => $this->comparisonTargetDiff->status,
            ];
        }

        // attachments が準備されていない場合のフォールバック（履歴タブなど）
        if (empty($this->allAttachments)) {
            $this->allAttachments = $this->ledgerRecord->attachedFiles()->withTrashed()->get()->keyBy('hashedbasename');
        }
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        $this->displayLevel = $displayLevel;
    }

    // グループの初期状態（必須項目があるかどうか）を取得する
    protected function getRequiredGroups(): array
    {
        return collect(optional($this->ledgerRecord->define)->column_define ?? [])
            ->filter(fn ($column) => is_array($column) ? ($column['required'] ?? false) : ($column->required ?? false))
            ->map(fn ($column) => is_array($column)
                ? ($column['group'] ?? __('ledger.form.group_default'))
                : ($column->group ?? __('ledger.form.group_default')))
            ->unique()
            ->values()
            ->toArray();
    }

    public function render()
    {
        // データの準備（ID が指定されているがモデルがない場合）
        if ($this->baseDiffId && (! isset($this->baseMeta) || $this->baseMeta === null)) {
            $baseDiff = LedgerDiff::with([
                'modifier:id,name,email,chat_link',
                'modifier.organizations',
                'approver:id,name,email,chat_link',
                'approver.organizations',
            ])->find($this->baseDiffId);
            if ($baseDiff) {
                $this->baseMeta = [
                    'modifier_name' => $baseDiff->modifier?->name ?? '?',
                    'updated_at' => $baseDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                    'version' => $baseDiff->version,
                    'status' => $baseDiff->status,
                ];
            }
        }

        if ($this->targetDiffId && (! $this->comparisonTargetDiff
            || $this->comparisonTargetDiff->id !== $this->targetDiffId)) {
            $this->comparisonTargetDiff = LedgerDiff::with([
                'modifier:id,name,email,chat_link',
                'modifier.organizations',
                'approver:id,name,email,chat_link',
                'approver.organizations',
            ])->find($this->targetDiffId);
            if ($this->comparisonTargetDiff) {
                $this->targetMeta = [
                    'modifier_name' => $this->comparisonTargetDiff->modifier?->name ?? '?',
                    'updated_at' => $this->comparisonTargetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
                    'version' => $this->comparisonTargetDiff->version,
                    'status' => $this->comparisonTargetDiff->status,
                ];
            }
        }

        // attachments プロパティを変更せず、ローカル変数を使用する（Reactive プロパティ変異エラー防止）
        // 外部サービスでのコレクション操作による変異を防ぐため、必ずクローンを作成する
        $resolvedAttachments = $this->allAttachments ? clone $this->allAttachments : collect()->keyBy('hashedbasename');

        // キーイングされていない可能性があるため、強制的に再キーイング
        if (! $resolvedAttachments->isEmpty() && (! $resolvedAttachments->first() instanceof \App\Models\AttachedFile
            || ! is_string($resolvedAttachments->keys()->first()))) {
            $resolvedAttachments = $resolvedAttachments->keyBy('hashedbasename');
        }

        // LedgerContentProcessor を呼び出して表示データを取得
        // もし baseDiffId が指定されている場合は、その時点の内容を base とする必要があるが、
        // 現状の processContentForDisplay は $ledgerRecord (最新) を前提としている。
        // LedgerDiffProcessor を介して、任意の2つの Diff を比較するロジックが必要。

        $result = app(LedgerContentProcessor::class)->processContentForDisplay(
            $this->ledgerRecord,
            $this->comparisonTargetDiff,
            $this->displayLevel,
            $resolvedAttachments,
            $this->highlight,
            $this->baseDiffId, // 第6引数として追加（後で processor も修正する）
            $this->showChanges // 第7引数として追加
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
        $pastVersion = $targetMeta['version']
            ?? ($this->comparisonTargetDiff ? $this->comparisonTargetDiff->version : null);

        return view('livewire.ledger.ledger-diff-viewer', [
            'currentVersion' => $currentVersion,
            'pastVersion' => $pastVersion,
            'baseMeta' => $baseMeta,
            'targetMeta' => $targetMeta,
            'requiredGroups' => $this->getRequiredGroups(),
        ]);
    }

    public function placeholder()
    {
        return view('livewire.ledger.ledger-diff-viewer-placeholder');
    }
}

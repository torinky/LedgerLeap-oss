<?php

namespace App\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Livewire\Traits\LogPerformance;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\Tenant;
use App\Services\Ledger\LedgerDiffProcessor;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Stancl\Tenancy\Tenancy;

class LedgerHistoryManager extends BaseLivewireComponent
{
    use InitializesTenantContext, LogPerformance;

    public int $ledgerId;

    public int $historyDisplayLevel = 3;

    // ページング用
    public int $perPage = 10;

    public int $pageCount = 1;

    public bool $hasMore = true;

    // 比較対象 - これらは内部で変更されるため Reactive にはしません
    #[Url(as: 'bd')]
    public ?int $baseDiffId = null; // 基準（新しい方、通常は最新）

    #[Url(as: 'td')]
    public ?int $targetDiffId = null; // 比較対象（古い方）

    // 表示用データ
    public ?Ledger $ledgerRecord = null;

    // 表示状態の維持用
    public ?string $highlight = '';

    public bool $canRollback = false;

    // 添付ファイル（LedgerDiffViewerに渡すため）
    public ?Collection $allAttachments = null;

    public function mount(
        int $ledgerId,
        int $displayLevel = 3,
        ?string $highlight = null
    ): void {
        $startTime = microtime(true);

        $this->ledgerId = $ledgerId;
        $this->historyDisplayLevel = $displayLevel;
        $this->highlight = $highlight ?? '';

        $this->reloadLedgerRecordWithoutTenancy();
        $this->initializeTenantContextFromLedger();

        // ロールバック権限の事前チェック (WRITE権限があればUIを表示)
        $folder = $this->ledgerRecord->define?->folder;
        $this->canRollback = false;
        if ($folder) {
            $userService = app(UserService::class);
            if ($userService) {
                $this->canRollback = $userService->hasFolderPermission(
                    auth()->user(),
                    $folder,
                    FolderPermissionType::WRITE
                );
            }
        }

        // 基準バージョンの決定
        // URL パラメータ (bd) から既にセットされている場合は何もしない
        if (! $this->baseDiffId) {
            // 指定がない場合、最新の diff ID を取得
            $latestDiff = $this->ledgerRecord->latestDiff;
            if ($latestDiff) {
                $this->baseDiffId = $latestDiff->id;
            }
        }

        // 比較対象が指定されている場合、新しい方を base にする（整合性維持のためのソート）
        if ($this->baseDiffId && $this->targetDiffId && $this->baseDiffId < $this->targetDiffId) {
            $tmp = $this->baseDiffId;
            $this->baseDiffId = $this->targetDiffId;
            $this->targetDiffId = $tmp;
        }

        // 添付ファイルの取得（LedgerDiffViewerに渡すため）
        $this->allAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)
            ->with('ledger')
            ->withTrashed()
            ->get();

        $this->logPerformance('ledger_mount', (microtime(true) - $startTime) * 1000);
    }

    protected function getPerformanceContext(): array
    {
        return [
            'ledger_id' => $this->ledgerId,
        ];
    }

    #[On('displayLevelUpdated')]
    public function updateDisplayLevel(int $displayLevel): void
    {
        // Reactive により不要になる可能性がありますが、
        // 他のイベントソースがある場合のために最小限で残します。
        $this->historyDisplayLevel = $displayLevel;
    }

    #[On('versionsSelected')]
    public function onVersionsSelected(?int $baseId, ?int $targetId): void
    {
        $this->baseDiffId = $baseId;
        $this->targetDiffId = $targetId;
    }

    public function updatedHistoryDisplayLevel(int $level): void
    {
        // 内部で変更された場合のみ必要
        $this->dispatch('displayLevelUpdated', displayLevel: $level);
    }

    public function loadMore(): void
    {
        $startTime = microtime(true);

        if (! $this->hasMore) {
            return;
        }

        $this->pageCount++;
        Log::debug("HistoryManager mount finished. base: $this->baseDiffId, target: $this->targetDiffId");

        $this->logPerformance('ledger_load_more', (microtime(true) - $startTime) * 1000);
    }

    #[On('ledger.rollback.completed')]
    public function onRollbackCompleted(): void
    {
        // ページネーションをリセットして最新を表示
        $this->pageCount = 1;
        $this->hasMore = true;

        // モデルをリフレッシュして最新情報を取得
        $this->ledgerRecord->refresh();

        // 最新のdiffを選択状態にする
        $latestDiff = $this->ledgerRecord->ledgerDiff()->latest('id')->first();
        if ($latestDiff) {
            $this->baseDiffId = $latestDiff->id;
            // 比較対象はリセット（または直前のバージョンにする？）
            $this->targetDiffId = null;
        }

        $this->dispatch('targetDiffIdUpdated', targetDiffId: null); // 他のコンポーネント（DiffViewer等）とも同期
    }

    public function rollback(int $diffId): void
    {
        // 確認モーダルを開くイベントをディスパッチ
        $this->dispatch(
            'ledger.rollback.open-modal',
            ledgerId: $this->ledgerId,
            targetDiffId: $diffId,
            expectedVersion: $this->ledgerRecord->version
        );
    }

    public function toggleSelection(int $id): void
    {
        $startTime = microtime(true);

        if ($this->baseDiffId === $id) {
            $this->baseDiffId = null;
        } elseif ($this->targetDiffId === $id) {
            $this->targetDiffId = null;
        } else {
            // 新しく選択する場合
            if ($this->baseDiffId === null) {
                $this->baseDiffId = $id;
            } elseif ($this->targetDiffId === null) {
                $this->targetDiffId = $id;
            } else {
                // 両方埋まっている場合、targetDiffId を追い出して新しく選択
                $this->targetDiffId = $id;
            }
        }

        // ソート処理（常に大きい方を baseDiffId に、1つだけなら baseDiffId に寄せる）
        $ids = collect([$this->baseDiffId, $this->targetDiffId])->filter()->sortDesc()->values();

        $this->baseDiffId = $ids->get(0);
        $this->targetDiffId = $ids->get(1);

        $this->dispatch('versionsSelected', baseId: $this->baseDiffId, targetId: $this->targetDiffId);

        $this->logPerformance('ledger_toggle_selection', (microtime(true) - $startTime) * 1000);
    }

    public function render()
    {
        $startTime = microtime(true);

        $this->reloadLedgerRecordWithoutTenancy();
        $this->initializeTenantContextFromLedger();
        $currentTenantId = $this->resolveTenantId($this->ledgerRecord?->tenant_id);
        if (! is_string($currentTenantId) && ! is_int($currentTenantId)) {
            Log::warning('Ledger history rendering skipped because tenant_id is missing', [
                'ledger_id' => $this->ledgerRecord?->id,
            ]);

            return view('livewire.ledger.ledger-history-manager', [
                'history' => collect(),
                'baseDiff' => null,
                'targetDiff' => null,
                'baseMeta' => null,
                'targetMeta' => null,
                'historyDisplayLevel' => $this->historyDisplayLevel,
                'canRollback' => $this->canRollback,
                'isContentIdentical' => false,
                'allAttachments' => $this->allAttachments,
            ]);
        }

        $diffsQuery = $this->ledgerDiffQuery($currentTenantId)
            ->with([
                'modifier.organizations',
                'inspector.organizations',
                'approver.organizations',
            ])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        $totalCount = $diffsQuery->count();
        $allFetchedDiffs = $diffsQuery->take($this->perPage * $this->pageCount)->get();

        // 連続する冗長な履歴エントリをフィルタリング（同じバージョン、ステータス、更新者、コメントが連続する場合）
        $diffs = $allFetchedDiffs->filter(function ($diff, $key) use ($allFetchedDiffs) {
            // 前のエントリ（時系列ではより新しいもの）と比較
            $prev = $allFetchedDiffs->get($key - 1);

            if ($prev) {
                // コメントを正規化（null と "" を同一視し、前後の空白を除去）
                $isSameComment = trim((string) $prev->comments) === trim((string) $diff->comments);

                if (
                    $prev->version === $diff->version &&
                    ($prev->status?->value ?? null) === ($diff->status?->value ?? null) &&
                    $prev->modifier_id === $diff->modifier_id &&
                    $isSameComment
                ) {
                    return false;
                }
            }

            return true;
        })->values();

        Log::info(
            'Ledger History Filter: Initial count '.$allFetchedDiffs->count().
            ' -> Filtered count '.$diffs->count().
            ' for Ledger '.$this->ledgerId,
            []
        );

        $this->hasMore = $diffs->count() < $totalCount;

        // 比較対象のデータを取得
        $baseDiff = $this->baseDiffId
            ? $this->ledgerDiffQuery($currentTenantId)->find($this->baseDiffId)
            : null;
        $targetDiff = $this->targetDiffId
            ? $this->ledgerDiffQuery($currentTenantId)->find($this->targetDiffId)
            : null;

        // メタ情報の準備
        $baseMeta = $baseDiff ? [
            'modifier_name' => $baseDiff->modifier?->name ?? '?',
            'updated_at' => $baseDiff->created_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $baseDiff->version,
            'comment' => $baseDiff->comments,
        ] : null;

        $targetMeta = $targetDiff ? [
            'modifier_name' => $targetDiff->modifier?->name ?? '?',
            'updated_at' => $targetDiff->created_at?->format('Y-m-d H:i:s') ?? '',
            'version' => $targetDiff->version,
            'comment' => $targetDiff->comments,
        ] : null;

        // コンテンツが完全に一致するかチェック
        $isContentIdentical = false;
        if ($targetDiff && $this->ledgerRecord) {
            $processor = app(LedgerDiffProcessor::class);
            // 現在のレコード($this->ledgerRecord) と 比較対象($targetDiff) の差分を計算
            // prepareContentDiff は $ledgerRecord と $comparisonTargetDiff を比較する
            // ここでは「現在のレコード」と「ロールバック対象(targetDiff)」を比較したい
            // ロールバック対象の内容に「戻す」ということは、
            // 「現在のレコード」が「ロールバック対象」と同じになるということ。
            // つまり、diff がない = hasChangedColumns が false であれば一致している。
            $diffResult = $processor->prepareContentDiff($this->ledgerRecord, $targetDiff);
            $isContentIdentical = ! ($diffResult['hasChangedColumns'] ?? true);
        }

        $this->logPerformance('ledger_diff_render', (microtime(true) - $startTime) * 1000, [
            'diffs_count' => $diffs->count(),
            'has_more' => $this->hasMore,
        ]);

        return view('livewire.ledger.ledger-history-manager', [
            'history' => $diffs,
            'baseDiff' => $baseDiff,
            'targetDiff' => $targetDiff,
            'baseMeta' => $baseMeta,
            'targetMeta' => $targetMeta,
            'historyDisplayLevel' => $this->historyDisplayLevel,
            'canRollback' => $this->canRollback,
            'isContentIdentical' => $isContentIdentical,
            'allAttachments' => $this->allAttachments,
        ]);
    }

    protected function initializeTenantContextFromLedger(): void
    {
        if (! $this->ledgerRecord) {
            return;
        }

        // Livewire の初回/再描画や CI の実行順によって tenancy が外れていても、
        // 台帳自身の tenant_id を根拠に復元する。
        $ledgerTenantId = $this->ledgerRecord->tenant_id;
        $this->tenantId = (is_string($ledgerTenantId) || is_int($ledgerTenantId))
            ? $ledgerTenantId
            : $this->resolveTenantId($ledgerTenantId);

        if (! $this->tenantId) {
            return;
        }

        $tenancy = app(Tenancy::class);

        try {
            // tenant id が同一でも、テスト実行中に接続設定が再読込されるケースに備えて毎回再初期化する
            $tenancy->initialize($this->tenantId);
        } catch (\Throwable $exception) {
            Log::warning('Ledger history tenant re-initialization by id failed. Fallback to tenant model resolution.', [
                'ledger_id' => $this->ledgerId,
                'tenant_id' => $this->tenantId,
                'error' => $exception->getMessage(),
            ]);

            // stancl/tenancy の実装差異で ID 指定初期化が失敗する環境向けにモデル解決で再試行する
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                $tenancy->initialize($tenant);
            }
        }
    }

    protected function reloadLedgerRecordWithoutTenancy(): void
    {
        $this->ledgerRecord = Ledger::withoutTenancy()->findOrFail($this->ledgerId);
    }

    protected function ledgerDiffQuery(string|int $tenantId): Builder
    {
        return LedgerDiff::withoutTenancy()
            ->where('ledger_id', $this->ledgerRecord->id)
            ->where('tenant_id', $tenantId);
    }
}

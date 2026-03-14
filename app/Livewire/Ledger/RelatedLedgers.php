<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Ledger\RelatedLedgerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Reactive;
use Livewire\WithPagination;

#[Lazy]
class RelatedLedgers extends BaseLivewireComponent
{
    use InitializesTenantContext;
    use WithPagination;

    public int $ledgerId;

    /** 識別番号フィルタートグル */
    public bool $showIdentifier = true;

    /** 意味検索フィルタートグル */
    public bool $showSemantic = true;

    /** 重複排除した合計件数（フィルター前） */
    public int $totalCount = 0;

    /** 識別番号検索の件数（フィルター前） */
    public int $identifierCount = 0;

    /** 意味検索の件数（フィルター前） */
    public int $semanticCount = 0;

    /** RAGサービスが利用可能か */
    public bool $ragAvailable = false;

    /** auto_number カラムを持つか */
    public bool $hasAutoNumber = false;

    /** 表示レベル（基本情報タブと同期） */
    #[Reactive]
    public int $displayLevel = 1;

    /** 1ページあたりの件数 */
    protected int $perPage = 20;

    /** エラーメッセージ（内部用） */
    private string $lastError = '';

    protected string $paginationTheme = 'tailwind';

    public function mount(int $ledgerId): void
    {
        $this->ledgerId = $ledgerId;
        // boot より後に実行されるため、ここで明示的にテナントコンテキストを初期化する
        // （bootInitializesTenantContext は lazy コンポーネントの初回リクエスト時に
        //   ルートパラメータがまだ利用できない場合があるため）
        if (is_null($this->tenantId)) {
            $route = request()->route();
            if ($route) {
                $this->tenantId = $route->originalParameters()['tenant'] ?? null;
            }
        }

        if ($this->tenantId) {
            $tenancy = app(\Stancl\Tenancy\Tenancy::class);
            if (! $tenancy->initialized) {
                $tenant = \App\Models\Tenant::find($this->tenantId);
                if ($tenant) {
                    $tenancy->initialize($tenant);
                }
            }
        }
    }

    /**
     * Lazy ロード後に呼ばれる初期化処理（placeholder から実コンテンツに切り替わる際に実行）
     */
    public function placeholder()
    {
        return view('livewire.ledger.related-ledgers-placeholder');
    }

    // ─────────────────────────────────────────────
    // トグル変更時にページをリセット
    // ─────────────────────────────────────────────

    public function updatedShowIdentifier(): void
    {
        $this->resetPage('related_page');
    }

    public function updatedShowSemantic(): void
    {
        $this->resetPage('related_page');
    }

    // ─────────────────────────────────────────────
    // 識別番号（auto_number）関連
    // ─────────────────────────────────────────────

    /**
     * 現在のレコードが持つ識別番号値を抽出する（パターンA・B両対応）
     *
     * - パターンA: auto_number 型列の値を直接取得
     * - パターンB: 全 auto_number パターンで自レコードのテキスト列にマッチング
     *
     * @return array<string, array{source: string, column: string}>
     */
    public function extractAutoNumberValues(Ledger $ledger): array
    {
        return app(RelatedLedgerService::class)->extractAutoNumberValues($ledger);
    }

    /**
     * 識別番号の連想配列で台帳を横断検索し、自身を除外した結果を返す
     *
     * @param  array<string, array{source: string, column: string}>  $identifierKeys
     * @return Collection<int, array{ledger: Ledger, matched_keys: array<int, array<string, string>>}>
     */
    public function searchByIdentifiers(array $identifierKeys): Collection
    {
        return app(RelatedLedgerService::class)->searchByIdentifiers(
            identifierKeys: $identifierKeys,
            user: auth()->user(),
            sourceLedgerId: $this->ledgerId,
        );
    }

    // ─────────────────────────────────────────────
    // 意味検索関連
    // ─────────────────────────────────────────────

    /**
     * レコードのコンテンツを意味検索クエリ文字列に変換する
     *
     * files タイプは除外、トークン長の上限を設ける
     */
    public function buildSemanticQuery(Ledger $ledger): string
    {
        return app(RelatedLedgerService::class)->buildSemanticQuery($ledger);
    }

    /**
     * 意味検索を実行して関連レコードを返す（スコア付き）
     * RAGサービスが利用できない場合は空コレクションを返す（グレースフルデグラデーション）
     *
     * @return Collection<int, array{ledger: Ledger, score: float}>
     */
    public function searchBySemantic(Ledger $ledger): Collection
    {
        $resolved = app(RelatedLedgerService::class)->searchBySemantic(
            ledger: $ledger,
            user: auth()->user(),
            sourceLedgerId: $this->ledgerId,
        );

        $this->ragAvailable = $resolved['rag_available'];
        $this->lastError = $resolved['error'];

        return $resolved['results'];
    }

    // ─────────────────────────────────────────────
    // Sprint 3: マージ・フィルター・ページング・グルーピング
    // ─────────────────────────────────────────────

    /**
     * 識別番号検索と意味検索の結果をマージし、識別理由（reason）・スコア・matched_keys を付与する
     * ソート: semantic/both はスコア降順、identifier のみは末尾
     *
     * @param  Collection<int, array{ledger: Ledger, matched_keys: array<int, array<string, string>>}>  $identifiers
     * @param  Collection<int, array{ledger: Ledger, score: float}>  $semantics
     * @return array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>
     */
    public function mergeResults(Collection $identifiers, Collection $semantics): array
    {
        return app(RelatedLedgerService::class)->mergeResults($identifiers, $semantics);
    }

    /**
     * トグル状態に基づき結果をフィルタリングする
     *
     * reason='both' はどちらかのトグルがオンなら表示
     *
     * @param  array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>  $merged
     * @return array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>
     */
    public function applyFilter(array $merged): array
    {
        return array_values(array_filter($merged, function (array $item) {
            return match ($item['reason']) {
                'identifier' => $this->showIdentifier,
                'semantic' => $this->showSemantic,
                'both' => $this->showIdentifier || $this->showSemantic,
                default => true,
            };
        }));
    }

    /**
     * フィルター済み配列を LengthAwarePaginator でラップする
     *
     * pageName='related_page' で他のページネーターとの衝突を回避
     *
     * @param  array<int, mixed>  $filtered
     */
    public function buildPaginator(array $filtered): LengthAwarePaginator
    {
        $total = count($filtered);
        $currentPage = (int) request()->get('related_page', 1);
        $currentPage = max(1, $currentPage);

        $sliced = array_slice($filtered, ($currentPage - 1) * $this->perPage, $this->perPage);

        return new LengthAwarePaginator(
            items: $sliced,
            total: $total,
            perPage: $this->perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'pageName' => 'related_page',
            ]
        );
    }

    /**
     * ページ内のアイテムを ledger_define_id でグループ化する
     *
     * @param  array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>  $pageItems
     * @return Collection<int|string, Collection<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>>
     */
    public function groupByDefine(array $pageItems): Collection
    {
        return collect($pageItems)
            ->groupBy(fn (array $item) => $item['ledger']->ledger_define_id);
    }

    // ─────────────────────────────────────────────
    // レンダリング
    // ─────────────────────────────────────────────

    public function render()
    {
        $ledger = Ledger::with(['define'])->findOrFail($this->ledgerId);

        // 識別番号検索
        $identifierKeys = $this->extractAutoNumberValues($ledger);
        $this->hasAutoNumber = ! empty($identifierKeys);
        $identifierCollection = $this->searchByIdentifiers($identifierKeys);

        // 意味検索
        $semanticCollection = $this->searchBySemantic($ledger);

        // マージ・件数集計（フィルター前）
        $merged = $this->mergeResults($identifierCollection, $semanticCollection);
        $this->identifierCount = $identifierCollection->count();
        $this->semanticCount = $semanticCollection->count();
        $this->totalCount = count($merged);

        // フィルター適用
        $filtered = $this->applyFilter($merged);

        // ページング
        $paginator = $this->buildPaginator($filtered);

        // ページ内アイテムをグルーピング
        $groupedResults = $this->groupByDefine($paginator->items());

        // 台帳定義モデルのマップ（ヘッダー表示用）
        $defineIds = $groupedResults->keys()->toArray();
        $defines = LedgerDefine::whereIn('id', $defineIds)->with('folder')->get()->keyBy('id');

        // ページ内の全 ledger_id を収集
        $pageledgerIds = $groupedResults->flatten(1)->pluck('ledger.id')->toArray();

        // 添付ファイルを一括取得（table-row コンポーネントで使用）
        $allAttachments = AttachedFile::whereIn('ledger_id', $pageledgerIds)
            ->get()
            ->groupBy('ledger_id');

        // content / content_attached を台帳定義に基づいて正規化（table-row が期待する形式）
        $groupedResults->flatten(1)->each(function (array $item) {
            $ledger = $item['ledger'];
            $define = $ledger->define;
            if ($define) {
                $ledger->content = $define->normalizeByColumnDefine($ledger->content ?? []);
                $ledger->content_attached = $define->normalizeByColumnDefine($ledger->content_attached ?? []);
            }
        });

        // semantic_score を Ledger インスタンスに動的付与（table-row のスコアオーバーレイで使用）
        // identifier のみのレコードは score=null のまま（オーバーレイなし）
        $groupedResults->flatten(1)->each(function (array $item) {
            if ($item['score'] !== null) {
                $item['ledger']->semantic_score = $item['score'];
            }
        });

        // 台帳定義ごとの表示カラム（displayLevel に応じてフィルタリング）
        $filteredColumnDefinesPerDefine = $defines->map(function (LedgerDefine $define) {
            return collect($define->column_define)
                ->filter(fn ($col) => ($col->display_level ?? 3) <= $this->displayLevel)
                ->sortBy('order')
                ->values();
        });

        // 権限チェック（ビューで再利用）
        $user = auth()->user();
        $permissionsPerDefine = $defines->map(fn (LedgerDefine $define) => [
            'canUpdate' => $user?->can('ledgerUpdate', $define) ?? false,
            'canView' => $user?->can('ledgerView', $define) ?? false,
        ]);

        // テナントID取得: $this->tenantId → tenant() → Ledgerのtenant_id の優先順でフォールバック
        // Lazy コンポーネントの後続リクエスト時に tenant() が null になる場合に備え、
        // すでに取得済みの $ledger から tenant_id を取得するのが最も確実。
        $currentTenantId = $this->tenantId ?? tenant()?->id ?? $ledger->tenant_id;

        // 親コンポーネントにバッジ件数を通知
        $this->dispatch('relatedCountUpdated', count: $this->totalCount);

        return view('livewire.ledger.related-ledgers', [
            'ledger' => $ledger,
            'identifierKeys' => $identifierKeys,
            'groupedResults' => $groupedResults,
            'defines' => $defines,
            'allAttachments' => $allAttachments,
            'filteredColumnDefinesPerDefine' => $filteredColumnDefinesPerDefine,
            'permissionsPerDefine' => $permissionsPerDefine,
            'paginator' => $paginator,
            'filteredCount' => count($filtered),
            'currentTenantId' => $currentTenantId,
        ]);
    }
}

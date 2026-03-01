<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Repositories\WritableFolderRepository;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
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
     * 現在のレコードが持つ auto_number カラムの値を抽出する
     *
     * @return string[] 例: ['SPEC-001', 'EQ-042']
     */
    public function extractAutoNumberValues(Ledger $ledger): array
    {
        $ledger->loadMissing('define');
        $define = $ledger->define;
        if (! $define) {
            return [];
        }

        $values = [];
        foreach ($define->column_define as $column) {
            if ($column->type !== 'auto_number') {
                continue;
            }
            // AsColumnArrayJson cast 済みの配列に直接アクセス（data_get() は不可）
            $value = $ledger->content[$column->id] ?? null;
            if (! empty($value)) {
                $values[] = (string) $value;
            }
        }

        return array_unique(array_values($values));
    }

    /**
     * 識別番号の配列で台帳を横断検索し、自身を除外した結果を返す
     *
     * @param  string[]  $identifierKeys
     * @return Collection<int, Ledger>
     */
    public function searchByIdentifiers(array $identifierKeys): Collection
    {
        if (empty($identifierKeys)) {
            return collect();
        }

        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        /** @var WritableFolderRepository $folderRepo */
        $folderRepo = app(WritableFolderRepository::class);
        $readableFolderIds = $folderRepo->getReadableFolderIds($user);

        if (empty($readableFolderIds)) {
            return collect();
        }

        // 閲覧可能フォルダに紐づく台帳定義IDを取得
        $allowedDefineIds = LedgerDefine::whereIn('folder_id', $readableFolderIds)
            ->pluck('id')
            ->toArray();

        if (empty($allowedDefineIds)) {
            return collect();
        }

        // 各 auto_number 値に対して全文検索を実施し、結果をマージ
        // Mroonga 制約: 複合インデックス不可 → 単一カラム MATCH() AGAINST() を使用
        $allIds = collect();
        foreach ($identifierKeys as $key) {
            // LedgerLookupController と同じロジック: SearchContext + scopeSearchContext
            $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => false, 'useTechnicalTerm' => false]);
            $synonymService = new SynonymService($synonymServiceConfig);
            $searchContext = new SearchContext($synonymService);
            $searchContext->setSearch($key);

            $ids = Ledger::whereIn('ledger_define_id', $allowedDefineIds)
                ->searchContext($searchContext)
                ->where('id', '!=', $this->ledgerId)
                ->pluck('id');

            $allIds = $allIds->merge($ids);
        }

        $uniqueIds = $allIds->unique()->values()->toArray();

        if (empty($uniqueIds)) {
            return collect();
        }

        return Ledger::whereIn('id', $uniqueIds)
            ->with(['define'])
            ->get();
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
        $ledger->loadMissing('define');
        $define = $ledger->define;
        if (! $define) {
            return '';
        }

        $parts = [];
        foreach ($define->column_define as $column) {
            // files タイプは除外
            if ($column->type === 'files') {
                continue;
            }
            $value = $ledger->content[$column->id] ?? null;
            if (empty($value)) {
                continue;
            }
            // 配列型（chk 等）はカンマ結合
            if (is_array($value)) {
                $value = implode(' ', array_filter($value));
            }
            $text = strip_tags((string) $value);
            if (! empty($text)) {
                $parts[] = mb_substr(trim($text), 0, 200);
            }
        }

        // 全体でのトークン長上限（RAGへの入力を節約）
        $query = implode(' ', $parts);

        return mb_substr($query, 0, 500);
    }

    /**
     * 意味検索を実行して関連レコードを返す
     * RAGサービスが利用できない場合は空コレクションを返す（グレースフルデグラデーション）
     *
     * @return Collection<int, Ledger>
     */
    public function searchBySemantic(Ledger $ledger): Collection
    {
        $query = $this->buildSemanticQuery($ledger);
        if (empty($query)) {
            return collect();
        }

        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        try {
            $ragService = app(\App\Services\RagSearchService::class);

            $ragResults = $ragService->searchLedgers(
                query: $query,
                limit: 20,
                filters: ['user' => $user]
            );

            if (empty($ragResults)) {
                return collect();
            }

            // 自身を除外
            $ledgerIds = collect($ragResults)
                ->pluck('ledger_id')
                ->filter(fn ($id) => $id !== $this->ledgerId)
                ->unique()
                ->values()
                ->toArray();

            if (empty($ledgerIds)) {
                return collect();
            }

            $this->ragAvailable = true;

            return Ledger::whereIn('id', $ledgerIds)
                ->with(['define'])
                ->orderByRaw('FIELD(id, '.implode(',', $ledgerIds).')')
                ->get();
        } catch (\Throwable $e) {
            // RAGサービス未起動・利用不可はエラーにしない（グレースフルデグラデーション）
            $this->ragAvailable = false;
            $this->lastError = $e->getMessage();

            return collect();
        }
    }

    // ─────────────────────────────────────────────
    // Sprint 3: マージ・フィルター・ページング・グルーピング
    // ─────────────────────────────────────────────

    /**
     * 識別番号検索と意味検索の結果をマージし、識別理由（reason）を付与する
     *
     * @param  Collection<int, Ledger>  $identifiers
     * @param  Collection<int, Ledger>  $semantics
     * @return array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: string[]}>
     */
    public function mergeResults(Collection $identifiers, Collection $semantics): array
    {
        $merged = [];

        // 識別番号検索結果を追加
        foreach ($identifiers as $ledger) {
            $merged[$ledger->id] = [
                'ledger' => $ledger,
                'reason' => 'identifier',
                'score' => null,
                'matched_keys' => [],
            ];
        }

        // 意味検索結果をマージ（識別番号検索にも含まれる場合は 'both' に昇格）
        foreach ($semantics as $ledger) {
            if (isset($merged[$ledger->id])) {
                // 両方ヒット → reason を 'both' に昇格
                $merged[$ledger->id]['reason'] = 'both';
            } else {
                $merged[$ledger->id] = [
                    'ledger' => $ledger,
                    'reason' => 'semantic',
                    'score' => null,
                    'matched_keys' => [],
                ];
            }
        }

        return array_values($merged);
    }

    /**
     * トグル状態に基づき結果をフィルタリングする
     *
     * reason='both' はどちらかのトグルがオンなら表示
     *
     * @param  array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: string[]}>  $merged
     * @return array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: string[]}>
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
     * @param  array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: string[]}>  $pageItems
     * @return Collection<int|string, Collection<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: string[]}>>
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

        // 台帳定義ごとの表示カラム（display_level=1 のみ）
        $filteredColumnDefinesPerDefine = $defines->map(function (LedgerDefine $define) {
            return collect($define->column_define)
                ->filter(fn ($col) => ($col->display_level ?? 3) <= 1)
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
            'filteredColumnDefinesPerDefine' => $filteredColumnDefinesPerDefine,
            'permissionsPerDefine' => $permissionsPerDefine,
            'paginator' => $paginator,
            'filteredCount' => count($filtered),
            'currentTenantId' => $currentTenantId,
        ]);
    }
}

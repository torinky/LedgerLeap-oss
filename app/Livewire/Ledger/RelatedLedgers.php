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
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;

#[Lazy]
class RelatedLedgers extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public int $ledgerId;

    /** 識別番号検索の結果 */
    public array $identifierResults = [];

    /** 意味検索の結果 */
    public array $semanticResults = [];

    /** 識別番号検索の件数 */
    public int $identifierCount = 0;

    /** 意味検索の件数 */
    public int $semanticCount = 0;

    /** 重複排除した合計件数 */
    public int $totalCount = 0;

    /** RAGサービスが利用可能か */
    public bool $ragAvailable = false;

    /** エラーメッセージ（内部用） */
    private string $lastError = '';

    public function mount(int $ledgerId): void
    {
        $this->ledgerId = $ledgerId;
    }

    /**
     * Lazy ロード後に呼ばれる初期化処理（placeholder から実コンテンツに切り替わる際に実行）
     */
    public function placeholder()
    {
        return view('livewire.ledger.related-ledgers-placeholder');
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
                filters: [
                    'user' => $user,
                ]
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
    // レンダリング
    // ─────────────────────────────────────────────

    public function render()
    {
        $ledger = Ledger::with(['define'])->findOrFail($this->ledgerId);

        // 識別番号検索
        $identifierKeys = $this->extractAutoNumberValues($ledger);
        $identifierCollection = $this->searchByIdentifiers($identifierKeys);

        // 意味検索
        $semanticCollection = $this->searchBySemantic($ledger);

        // 重複排除: 識別番号検索・意味検索の両方に含まれるIDを除外しない（両セクションに表示する）
        // 合計件数は Union で重複排除
        $allIds = $identifierCollection->pluck('id')
            ->merge($semanticCollection->pluck('id'))
            ->unique();

        $this->identifierCount = $identifierCollection->count();
        $this->semanticCount = $semanticCollection->count();
        $this->totalCount = $allIds->count();

        // 親コンポーネントにバッジ件数を通知
        $this->dispatch('relatedCountUpdated', count: $this->totalCount);

        return view('livewire.ledger.related-ledgers', [
            'ledger' => $ledger,
            'identifierKeys' => $identifierKeys,
            'identifierResults' => $identifierCollection,
            'semanticResults' => $semanticCollection,
        ]);
    }
}

<?php

namespace App\Services\Ledger;

use App\Helpers\SearchHelper;
use App\Models\SearchKeyword;
use App\Models\SearchQuery;
use App\Models\SearchQueryWord;
use App\Models\User;
use App\Services\SynonymService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchKeywordService
{
    /**
     * 単語登録モード。
     *
     * 'a' = 品詞='名詞' のみ (Igo 生 token 境界、analyzeAsWordTokens mode 'a')
     * 'b' = 入力キーワードそのまま (空白分割、analyzeAsWordTokens mode 'b')
     */
    private string $wordRegistrationMode = 'a';

    /**
     * 検索クエリとその形態素解析結果を 3 テーブルに記録します。
     *
     * @param  string  $query  検索クエリ全文
     * @param  array<int, array{surface: string, pos: string, pos_sub: string, is_proper_noun: bool}>  $keywords  形態素解析結果
     */
    public function recordKeywords(string $query, array $keywords, string $tenantId, ?User $user = null): void
    {
        // 全角スペース → 半角スペースに正規化 (クエリ間の整合性のため)
        $query = SearchHelper::normalizeQuery($query);

        if ($query === '' || $keywords === []) {
            return;
        }

        $userId = $user?->id;
        $now = now();

        DB::transaction(function () use ($query, $keywords, $tenantId, $userId, $now) {
            // 1. search_queries に UPSERT
            $searchQuery = SearchQuery::where('tenant_id', $tenantId)
                ->where('query_text', $query)
                ->first();

            if ($searchQuery) {
                $searchQuery->increment('search_count');
                $searchQuery->update(['last_searched_at' => $now]);
            } else {
                $searchQuery = SearchQuery::create([
                    'tenant_id' => $tenantId,
                    'query_text' => $query,
                    'search_count' => 1,
                    'user_count' => $userId ? 1 : 0,
                    'last_searched_at' => $now,
                ]);
            }

            // 2. search_keywords に各キーワードを UPSERT
            $keywordSurfaces = [];
            foreach ($keywords as $kw) {
                $surface = $kw['surface'];
                if ($surface === '') {
                    continue;
                }

                $keywordSurfaces[] = $surface;

                $existing = SearchKeyword::where('tenant_id', $tenantId)
                    ->where('keyword', $surface)
                    ->first();

                if ($existing) {
                    $existing->increment('search_count');
                    $existing->update(['last_searched_at' => $now]);
                } else {
                    SearchKeyword::create([
                        'tenant_id' => $tenantId,
                        'keyword' => $surface,
                        'lemma' => $surface,
                        'pos' => $kw['pos'] ?? '',
                        'pos_sub' => $kw['pos_sub'] ?? '',
                        'is_proper_noun' => $kw['is_proper_noun'] ?? false,
                        'search_count' => 1,
                        'user_count' => $userId ? 1 : 0,
                        'last_searched_at' => $now,
                    ]);
                }
            }

            // 3. search_query_words に単語境界トークンを登録 (Phase B)
            // analyzeAsWordTokens で品詞境界の個別単語を抽出し、逆引きインデックスに登録する。
            // これにより "作業" 1 語入力でも "作業 部品 修理" のクエリがサジェストされる。
            // 連結形 (analyze() の出力) は search_keywords にのみ保存し、
            // search_query_words には個別単語のみを登録する。
            $wordTokens = SynonymService::analyzeAsWordTokens($query, $this->wordRegistrationMode);
            $existingWordIds = SearchQueryWord::where('query_id', $searchQuery->id)
                ->pluck('word')
                ->toArray();

            $newWordTokens = array_diff($wordTokens, $existingWordIds);

            if ($newWordTokens !== []) {
                $inserts = array_map(fn (string $word) => [
                    'query_id' => $searchQuery->id,
                    'word' => $word,
                ], array_values($newWordTokens));

                SearchQueryWord::insert($inserts);
            }
        });

        Cache::tags(['search_keywords'])->flush();
    }

    /**
     * テナント内の人気キーワードを検索回数順に取得します。
     *
     * @return array<int, array{keyword: string, search_count: int, is_proper_noun: bool}>
     */
    public function getPopularKeywords(string $tenantId, int $limit = 10): array
    {
        $cacheKey = "search_popular_keywords:{$tenantId}:{$limit}";

        if (app()->environment('testing')) {
            return $this->fetchPopularKeywords($tenantId, $limit);
        }

        return Cache::tags(['search_keywords'])->remember($cacheKey, 3600, function () use ($tenantId, $limit) {
            return $this->fetchPopularKeywords($tenantId, $limit);
        });
    }

    private function fetchPopularKeywords(string $tenantId, int $limit): array
    {
        return SearchKeyword::where('tenant_id', $tenantId)
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get()
            ->map(fn (SearchKeyword $kw) => [
                'keyword' => $kw->keyword,
                'search_count' => $kw->search_count,
                'is_proper_noun' => $kw->is_proper_noun,
            ])
            ->toArray();
    }

    /**
     * 入力中クエリに対して search_query_words → search_queries を逆引きし、
     * マッチ率スコア順にクエリ全文を提案します。
     *
     * スコア = (match_word_count / total_word_count) × log2(search_count + 1)
     * - マッチ率: 入力単語がクエリ全体の何割をカバーするか (0.0 - 1.0)
     * - 頻度重み: log2(search_count + 1) でよく検索されるクエリを緩やかに優先
     *
     * キャッシュキー: search_query_suggestions:{tenantId}:{word_hash}
     * テスト環境では app()->environment('testing') でバイパスする。
     *
     * @param  string  $partial  入力中のクエリ (1 文字以上)
     * @param  string  $tenantId  テナント ID
     * @param  int  $limit  上位件数
     * @return array<int, array{query_text: string, search_count: int, match_rate: float, score: float}>
     */
    public function suggestQueries(string $partial, string $tenantId, int $limit = 10): array
    {
        $words = $this->tokenizeForLookup($partial);
        if ($words === [] || $tenantId === '') {
            return [];
        }

        $cacheKey = 'search_query_suggestions:'.$tenantId.':'.hash('xxh3', implode("\u{1F}", $words)).':'.$limit;

        if (app()->environment('testing')) {
            return $this->fetchQuerySuggestions($words, $tenantId, $limit);
        }

        return Cache::tags(['search_keywords'])->remember($cacheKey, 300, function () use ($words, $tenantId, $limit) {
            return $this->fetchQuerySuggestions($words, $tenantId, $limit);
        });
    }

    /**
     * 入力テキストを search_query_words の word 列と突合できる単語リストに変換します。
     *
     * recordKeywords() と同じ SynonymService::analyzeAsWordTokens() を使うことで、
     * 検索時に保存された単語とサジェスト時の単語が同じ粒度で比較される。
     *
     * @return array<int, string>
     */
    private function tokenizeForLookup(string $partial): array
    {
        return SynonymService::analyzeAsWordTokens($partial, $this->wordRegistrationMode);
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, array{query_text: string, search_count: int, match_rate: float, score: float}>
     */
    private function fetchQuerySuggestions(array $words, string $tenantId, int $limit): array
    {
        // 空単語除去 + Mroonga BOOLEAN MODE 特殊文字エスケープ
        $sanitized = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }
            // Mroonga boolean mode operators: + - * ( ) " ~ < > @
            $sanitized[] = preg_replace('/[+\-*()"~<>@]/u', '\\\\$0', $w);
        }
        if ($sanitized === []) {
            return [];
        }
        $matchQuery = implode(' ', $sanitized);

        $rows = DB::select(
            <<<SQL
            SELECT
                sq.id AS query_id,
                sq.query_text AS query_text,
                sq.search_count AS search_count,
                COUNT(sqw.id) AS match_count
            FROM search_queries sq
            INNER JOIN search_query_words sqw
                ON sqw.query_id = sq.id
            WHERE sq.tenant_id = ?
              AND MATCH(sqw.word) AGAINST(? IN BOOLEAN MODE)
            GROUP BY sq.id, sq.query_text, sq.search_count
            ORDER BY match_count DESC, sq.search_count DESC, sq.last_searched_at DESC
            LIMIT ?
            SQL,
            [$tenantId, $matchQuery, $limit * 4]
        );

        if ($rows === []) {
            return [];
        }

        $queryIds = array_map(static fn ($r) => (int) $r->query_id, $rows);
        $totalsByQuery = $this->totalWordCountByQueryIds($queryIds);

        $scored = [];
        foreach ($rows as $row) {
            $queryId = (int) $row->query_id;
            $total = $totalsByQuery[$queryId] ?? 1;
            $match = (int) $row->match_count;
            $count = (int) $row->search_count;
            $matchRate = $match / max(1, $total);
            $score = $matchRate * log($count + 1, 2);

            $scored[] = [
                'query_text' => (string) $row->query_text,
                'search_count' => $count,
                'match_rate' => round($matchRate, 4),
                'score' => $score,
            ];
        }

        usort($scored, function (array $a, array $b) {
            return $b['score'] <=> $a['score']
                ?: $b['search_count'] <=> $a['search_count'];
        });

        return array_values(array_slice($scored, 0, $limit));
    }

    /**
     * @param  array<int, int>  $queryIds
     * @return array<int, int>
     */
    private function totalWordCountByQueryIds(array $queryIds): array
    {
        if ($queryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($queryIds), '?'));
        $rows = DB::select(
            "SELECT query_id, COUNT(*) AS total FROM search_query_words WHERE query_id IN ({$placeholders}) GROUP BY query_id",
            $queryIds
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->query_id] = (int) $row->total;
        }

        return $map;
    }
}

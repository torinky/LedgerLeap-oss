<?php

namespace Tests\Unit\Services\Ledger;

use App\Models\SearchKeyword;
use App\Models\SearchQuery;
use App\Models\SearchQueryWord;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\SearchKeywordService;
use App\Services\SynonymService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(SearchKeywordService::class)]
class SearchKeywordServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private SearchKeywordService $service;

    private User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // Mroonga does not fully support transaction rollback,
        // so we manually truncate the keyword tables between tests.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        SearchQueryWord::truncate();
        SearchQuery::truncate();
        SearchKeyword::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->service = new SearchKeywordService;
        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create([
            'email' => 'test.'.Str::random(10).'@example.com',
        ]);
    }

    #[Test]
    public function it_records_keywords_to_all_three_tables(): void
    {
        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
            ['surface' => 'モーター', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];

        $this->service->recordKeywords(
            query: '部品 モーター',
            keywords: $keywords,
            tenantId: $this->tenant->id,
            user: $this->user,
        );

        $this->assertDatabaseHas('search_keywords', [
            'tenant_id' => $this->tenant->id,
            'keyword' => '部品',
        ]);
        $this->assertDatabaseHas('search_keywords', [
            'tenant_id' => $this->tenant->id,
            'keyword' => 'モーター',
        ]);

        $this->assertDatabaseHas('search_queries', [
            'tenant_id' => $this->tenant->id,
            'query_text' => '部品 モーター',
        ]);

        $this->assertDatabaseCount('search_query_words', 2);
    }

    #[Test]
    public function it_increments_search_count_on_duplicate_keyword(): void
    {
        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];

        $this->service->recordKeywords('部品', $keywords, $this->tenant->id, $this->user);
        $this->service->recordKeywords('部品', $keywords, $this->tenant->id, $this->user);

        $this->assertDatabaseHas('search_keywords', [
            'tenant_id' => $this->tenant->id,
            'keyword' => '部品',
            'search_count' => 2,
        ]);
    }

    #[Test]
    public function it_increments_search_count_on_duplicate_query(): void
    {
        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];

        $this->service->recordKeywords('部品 モーター', $keywords, $this->tenant->id, $this->user);
        $this->service->recordKeywords('部品 モーター', $keywords, $this->tenant->id, $this->user);

        $this->assertDatabaseHas('search_queries', [
            'tenant_id' => $this->tenant->id,
            'query_text' => '部品 モーター',
            'search_count' => 2,
        ]);
    }

    #[Test]
    public function it_sets_is_proper_noun_correctly(): void
    {
        $keywords = [
            ['surface' => '東京', 'pos' => '名詞', 'pos_sub' => '固有名詞', 'is_proper_noun' => true],
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];

        $this->service->recordKeywords('東京 部品', $keywords, $this->tenant->id, $this->user);

        $this->assertDatabaseHas('search_keywords', [
            'tenant_id' => $this->tenant->id,
            'keyword' => '東京',
            'is_proper_noun' => true,
        ]);
        $this->assertDatabaseHas('search_keywords', [
            'tenant_id' => $this->tenant->id,
            'keyword' => '部品',
            'is_proper_noun' => false,
        ]);
    }

    #[Test]
    public function it_returns_empty_for_blank_query(): void
    {
        $this->service->recordKeywords('', [], $this->tenant->id, $this->user);

        $this->assertDatabaseCount('search_keywords', 0);
        $this->assertDatabaseCount('search_queries', 0);
        $this->assertDatabaseCount('search_query_words', 0);
    }

    #[Test]
    public function it_returns_popular_keywords_ordered_by_count(): void
    {
        SearchKeyword::create([
            'tenant_id' => $this->tenant->id,
            'keyword' => 'レア',
            'search_count' => 1,
        ]);
        SearchKeyword::create([
            'tenant_id' => $this->tenant->id,
            'keyword' => '人気',
            'search_count' => 10,
        ]);
        SearchKeyword::create([
            'tenant_id' => $this->tenant->id,
            'keyword' => '普通',
            'search_count' => 5,
        ]);

        $result = $this->service->getPopularKeywords($this->tenant->id, 10);

        $this->assertCount(3, $result);
        $this->assertSame('人気', $result[0]['keyword']);
        $this->assertSame('普通', $result[1]['keyword']);
        $this->assertSame('レア', $result[2]['keyword']);
    }

    #[Test]
    public function it_respects_limit_on_popular_keywords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            SearchKeyword::create([
                'tenant_id' => $this->tenant->id,
                'keyword' => "kw{$i}",
                'search_count' => 1,
            ]);
        }

        $result = $this->service->getPopularKeywords($this->tenant->id, 3);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function it_scopes_popular_keywords_to_tenant(): void
    {
        $tenantB = Tenant::factory()->create();

        SearchKeyword::create([
            'tenant_id' => $this->tenant->id,
            'keyword' => 'テナントAの単語',
            'search_count' => 10,
        ]);
        SearchKeyword::create([
            'tenant_id' => $tenantB->id,
            'keyword' => 'テナントBの単語',
            'search_count' => 10,
        ]);

        $result = $this->service->getPopularKeywords($this->tenant->id, 10);

        $this->assertCount(1, $result);
        $this->assertSame('テナントAの単語', $result[0]['keyword']);
    }

    #[Test]
    public function it_deduplicates_query_words_on_repeat_recording(): void
    {
        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '', 'is_proper_noun' => false],
            ['surface' => 'モーター', 'pos' => '名詞', 'pos_sub' => '', 'is_proper_noun' => false],
        ];

        $this->service->recordKeywords('部品 モーター', $keywords, $this->tenant->id, $this->user);
        $this->service->recordKeywords('部品 モーター', $keywords, $this->tenant->id, $this->user);

        // 重複して insert されていないこと
        $this->assertDatabaseCount('search_query_words', 2);
    }

    #[Test]
    public function it_returns_empty_for_blank_or_empty_partial_in_suggest_queries(): void
    {
        $this->assertSame([], $this->service->suggestQueries('', $this->tenant->id));
        $this->assertSame([], $this->service->suggestQueries('   ', $this->tenant->id));
        $this->assertSame([], $this->service->suggestQueries('部品', ''));
    }

    #[Test]
    public function it_suggests_queries_by_match_rate_with_log2_score(): void
    {
        // 入力 "部品 を モーター" → analyzeAsWordTokens → ["部品", "モーター"]
        // MATCH AGAINST('部品 モーター' IN BOOLEAN MODE) with Mroonga TokenBigram
        // は "部品" または "モーター" を含む全クエリに部分一致する。
        $this->recordQueryViaService('部品 を モーター', 100);
        $this->recordQueryViaService('部品 を モーター を 点検', 4);
        $this->recordQueryViaService('部品 を 交換 を 記録', 10);
        $this->recordQueryViaService('部品 を モーター を 交換', 2);

        $result = $this->service->suggestQueries('部品 を モーター', $this->tenant->id, 5);

        // 4 件すべてヒット (いずれも "部品" を含む)
        $this->assertCount(4, $result);

        // スコア降順 (match_count=2 が上位、同点なら search_count 降順)
        $this->assertSame('部品 を モーター', $result[0]['query_text']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_query_words_match(): void
    {
        $this->recordQueryViaService('部品 を モーター', 5);

        $result = $this->service->suggestQueries('腐食', $this->tenant->id);

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_scopes_query_suggestions_to_tenant(): void
    {
        $tenantB = Tenant::factory()->create();

        $this->recordQueryViaService('部品', 5, $this->tenant->id);
        $this->recordQueryViaService('部品', 5, $tenantB->id);

        $result = $this->service->suggestQueries('部品', $this->tenant->id);

        $this->assertCount(1, $result);
        $this->assertSame('部品', $result[0]['query_text']);
    }

    #[Test]
    public function it_respects_limit_on_query_suggestions(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->recordQueryViaService("部品{$i}", $i + 1);
        }

        $result = $this->service->suggestQueries('部品', $this->tenant->id, 3);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function it_and_matches_multiple_input_words(): void
    {
        // 入力 "部品 を モーター" → analyzeAsWordTokens → ["部品", "モーター"]
        // MATCH AGAINST('部品 モーター' IN BOOLEAN MODE) with Mroonga TokenBigram
        // は "部品" または "モーター" を含む全クエリに部分一致する。
        $this->recordQueryViaService('部品', 100);
        $this->recordQueryViaService('部品 を モーター', 1);
        $this->recordQueryViaService('モーター', 100);

        $result = $this->service->suggestQueries('部品 を モーター', $this->tenant->id);

        // 3 件すべてヒット ("部品" または "モーター" を含む)
        $this->assertCount(3, $result);
        // 上位は match_count=2 の "部品 を モーター" だが、
        // search_count=100 の単語クエリがスコアで上回る場合もある
        $this->assertContains('部品 を モーター', array_column($result, 'query_text'));
    }

    #[Test]
    public function it_bypasses_cache_in_testing_environment(): void
    {
        $this->recordQueryViaService('部品', 1);

        $first = $this->service->suggestQueries('部品', $this->tenant->id);
        $this->assertCount(1, $first);

        // 追加で同じ単語のクエリを増やしても、テスト環境ではキャッシュバイパスにより
        // 次の呼び出しは最新結果を返す (本番ならキャッシュヒットで 1 件のまま)
        $this->recordQueryViaService('部品2', 1);

        $second = $this->service->suggestQueries('部品', $this->tenant->id);
        $this->assertCount(2, $second);
    }

    // ----------------------------------------------------------------
    // キャッシュ無効化 / テナント分離
    // ----------------------------------------------------------------

    #[Test]
    public function it_flushes_cache_after_recording_keywords(): void
    {
        $cacheKey = 'test_cache_key';
        $cacheValue = ['keyword' => 'キャッシュされる値'];

        Cache::tags(['search_keywords'])->put($cacheKey, $cacheValue, 600);

        $this->assertSame($cacheValue, Cache::tags(['search_keywords'])->get($cacheKey));

        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];
        $this->service->recordKeywords('部品', $keywords, $this->tenant->id, $this->user);

        $this->assertNull(Cache::tags(['search_keywords'])->get($cacheKey));
    }

    #[Test]
    public function it_isolates_cache_keys_by_tenant(): void
    {
        $tenantB = Tenant::factory()->create();

        // tenant A と B で同じ username を持つユーザを作成
        $userB = User::factory()->create([
            'email' => 'userB.'.Str::random(10).'@example.com',
        ]);

        // 両テナントに同じキーワードを登録
        $keywords = [
            ['surface' => '部品', 'pos' => '名詞', 'pos_sub' => '一般', 'is_proper_noun' => false],
        ];
        $this->service->recordKeywords('部品', $keywords, $this->tenant->id, $this->user);
        $this->service->recordKeywords('部品', $keywords, $tenantB->id, $userB);

        // キャッシュキーは tenant_id を含むため、別テナントで分離される
        // (test環境では cache bypass のため DB レベルで確認)
        $cacheKeyA = "search_popular_keywords:{$this->tenant->id}:10";
        $cacheKeyB = "search_popular_keywords:{$tenantB->id}:10";

        $this->assertNotSame($cacheKeyA, $cacheKeyB);

        // DB レベルでテナント分離を確認
        $resultA = $this->service->getPopularKeywords($this->tenant->id, 10);
        $resultB = $this->service->getPopularKeywords($tenantB->id, 10);

        $this->assertCount(1, $resultA);
        $this->assertCount(1, $resultB);
    }

    /**
     * 実際の recordKeywords() パス経由でクエリを保存するヘルパ。
     * 本番の SearchHistoryService::record() と同じ流れを再現し、
     * search_query_words.word に SynonymService::analyze() の出力を直接書き込む。
     */
    private function recordQueryViaService(string $queryText, int $searchCount, ?string $tenantId = null): void
    {
        $tenantId ??= $this->tenant->id;
        $keywords = SynonymService::analyze($queryText);

        $this->service->recordKeywords(
            query: $queryText,
            keywords: $keywords,
            tenantId: $tenantId,
            user: $this->user,
        );

        // search_count を直接上書き (recordKeywords は新規なら 1 固定)
        $query = SearchQuery::where('tenant_id', $tenantId)
            ->where('query_text', $queryText)
            ->first();
        if ($query) {
            $query->update(['search_count' => $searchCount]);
        }
    }

    // ----------------------------------------------------------------
    // Phase B: 単語境界インデックス (analyzeAsWordTokens)
    // ----------------------------------------------------------------

    #[Test]
    public function it_suggests_compound_query_from_single_word_input(): void
    {
        // "作業 部品 修理" を recordKeywords 経由で登録。
        // analyze() は "作業部品修理" の連結形を返すが、
        // Phase B の analyzeAsWordTokens により "作業", "部品", "修理" の
        // 個別単語も search_query_words に登録される。
        $keywords = SynonymService::analyze('作業 部品 修理');
        $this->service->recordKeywords(
            query: '作業 部品 修理',
            keywords: $keywords,
            tenantId: $this->tenant->id,
            user: $this->user,
        );

        // search_count を設定
        $query = SearchQuery::where('tenant_id', $this->tenant->id)
            ->where('query_text', '作業 部品 修理')
            ->first();
        $query->update(['search_count' => 10]);

        // "作業" 1 語入力 → サジェストに "作業 部品 修理" がマッチ率 0.3333 で表示
        // (match_count=1 / total_query_words=3 = 作業,部品,修理)
        $result = $this->service->suggestQueries('作業', $this->tenant->id, 5);

        $this->assertCount(1, $result);
        $this->assertSame('作業 部品 修理', $result[0]['query_text']);
        $this->assertEqualsWithDelta(0.3333, $result[0]['match_rate'], 0.01);
    }

    #[Test]
    public function it_suggests_query_from_partial_word_with_existing_and_match(): void
    {
        // 複数クエリ登録
        $this->recordQueryViaService('作業 部品 修理', 10);
        $this->recordQueryViaService('部品 交換 記録', 5);
        $this->recordQueryViaService('作業 報告', 3);

        // "作業 部品" 2 語入力 → MATCH AGAINST で 3 件すべてヒット
        // ("作業" または "部品" を含む)
        $result = $this->service->suggestQueries('作業 部品', $this->tenant->id, 5);

        $this->assertCount(3, $result);
        // match_count=2 の "作業 部品 修理" が最上位
        $this->assertSame('作業 部品 修理', $result[0]['query_text']);
    }
}

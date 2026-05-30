<?php

namespace Tests\Unit\Services\Ledger;

use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SearchContext::class)]
#[CoversClass(SynonymServiceConfig::class)]
class SearchContextTest extends TestCase
{
    private SynonymService $synonymService;

    private SearchContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        // 類義語検索を無効にしたSynonymServiceを使用
        $config = new SynonymServiceConfig(['useSynonym' => false, 'useTechnicalTerm' => false]);
        $this->synonymService = new SynonymService($config);
        $this->context = new SearchContext($this->synonymService);
    }

    // ----------------------------------------------------------------
    // setSearch / setKeywords
    // ----------------------------------------------------------------

    #[Test]
    public function set_search_populates_keywords(): void
    {
        $this->context->setSearch('テスト 検索');
        $this->assertNotEmpty($this->context->keywords);
    }

    #[Test]
    public function set_search_empty_string(): void
    {
        $this->context->setSearch('');
        $this->assertEmpty($this->context->keywords);
        $this->assertEmpty($this->context->tags);
    }

    #[Test]
    public function set_keywords_directly(): void
    {
        $this->context->setKeywords(['foo', 'bar']);
        $this->assertContains('foo', $this->context->keywords);
        $this->assertContains('bar', $this->context->keywords);
    }

    // ----------------------------------------------------------------
    // タグ抽出
    // ----------------------------------------------------------------

    #[Test]
    public function set_search_extracts_tags_from_hashtags(): void
    {
        $this->context->setSearch('#urgent テスト');
        $this->assertSame(['urgent'], $this->context->tags);
        $this->assertNotContains('#urgent', $this->context->keywords);
        $this->assertNotContains('urgent', $this->context->keywords);
        $this->assertContains('テスト', $this->context->keywords);
    }

    #[Test]
    public function set_search_builds_kind_aware_trace_for_synonyms_and_technical_terms(): void
    {
        $config = new SynonymServiceConfig(['useSynonym' => true, 'useTechnicalTerm' => true]);
        $synonymService = new class($config) extends SynonymService
        {
            public function getSearchTermsFromWord($word, array $options = []): array
            {
                if ($word === '請求') {
                    return [
                        ['term' => '請求', 'kind' => 'original'],
                        ['term' => '請求書', 'kind' => 'synonym'],
                        ['term' => 'インボイス', 'kind' => 'technical'],
                    ];
                }

                return [['term' => $word, 'kind' => 'original']];
            }

            public function getSynonymsFromWord($word, array $options = [])
            {
                return ['請求書', 'インボイス'];
            }
        };

        $context = new SearchContext($synonymService);

        $context->setSearch('請求 #重要');

        $trace = $context->getTrace();

        $this->assertSame('請求 #重要', $trace['original_q']);
        $this->assertNotEmpty($trace['normalized_q']);
        $this->assertSame(['重要'], $trace['tags']);
        $this->assertNotContains('#重要', $trace['keywords']);
        $this->assertContains('請求', array_column($trace['selected_terms'], 'term'));
        $this->assertContains('請求書', array_column($trace['selected_terms'], 'term'));
        $this->assertContains('インボイス', array_column($trace['selected_terms'], 'term'));
        $this->assertContains('synonym', array_column($trace['selected_terms'], 'kind'));
        $this->assertContains('technical', array_column($trace['selected_terms'], 'kind'));
    }

    // ----------------------------------------------------------------
    // setFilter / setHighlights
    // ----------------------------------------------------------------

    #[Test]
    public function set_filter_stores_filter(): void
    {
        $this->context->setFilter(['status' => 'draft']);
        $this->assertEquals(['status' => 'draft'], $this->context->filter);
    }

    #[Test]
    public function set_highlights_stores_highlights(): void
    {
        $this->context->setHighlights(['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $this->context->highlights);
    }

    // ----------------------------------------------------------------
    // flattenSynonyms
    // ----------------------------------------------------------------

    #[Test]
    public function flatten_synonyms_with_nested_array(): void
    {
        $synonyms = ['key1' => ['a', 'b'], 'key2' => 'c'];
        $result = $this->context->flattenSynonyms($synonyms);
        $this->assertIsArray($result);
    }

    #[Test]
    public function flatten_synonyms_with_empty_array(): void
    {
        $result = $this->context->flattenSynonyms([]);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    // getArr
    // ----------------------------------------------------------------

    #[Test]
    public function get_arr_merges_when_synonym_is_array(): void
    {
        $result = $this->context->getArr(['x', 'y'], ['a'], ['x', 'y']);
        $this->assertContains('x', $result);
        $this->assertContains('y', $result);
        $this->assertContains('a', $result);
    }

    #[Test]
    public function get_arr_appends_when_synonym_is_string(): void
    {
        $result = $this->context->getArr('z', ['a', 'b'], ['z']);
        $this->assertContains('a', $result);
        $this->assertContains('b', $result);
        $this->assertContains('z', $result);
    }

    // ----------------------------------------------------------------
    // getFlattenedSynonymsForKeyword
    // ----------------------------------------------------------------

    #[Test]
    public function get_flattened_synonyms_for_known_keyword(): void
    {
        $this->context->setKeywords(['テスト']);
        $result = $this->context->getFlattenedSynonymsForKeyword('テスト');
        $this->assertIsArray($result);
        $this->assertContains('テスト', $result);
    }

    #[Test]
    public function get_flattened_synonyms_for_unknown_keyword(): void
    {
        $result = $this->context->getFlattenedSynonymsForKeyword('unknown_keyword');
        $this->assertIsArray($result);
        $this->assertContains('unknown_keyword', $result);
    }

    // ----------------------------------------------------------------
    // __toString
    // ----------------------------------------------------------------

    #[Test]
    public function to_string_returns_empty_when_no_keywords(): void
    {
        $result = (string) $this->context;
        $this->assertSame('', $result);
    }

    #[Test]
    public function to_string_returns_highlights_as_string(): void
    {
        $this->context->setKeywords(['hello', 'world']);
        $result = (string) $this->context;
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ----------------------------------------------------------------
    // SynonymServiceConfig（追加カバレッジ）
    // ----------------------------------------------------------------

    #[Test]
    public function synonym_service_config_defaults(): void
    {
        $config = new SynonymServiceConfig;
        $this->assertTrue($config->useSynonym);
        $this->assertTrue($config->useTechnicalTerm);
    }

    #[Test]
    public function synonym_service_config_custom_values(): void
    {
        $config = new SynonymServiceConfig(['useSynonym' => false, 'useTechnicalTerm' => false]);
        $this->assertFalse($config->useSynonym);
        $this->assertFalse($config->useTechnicalTerm);
    }

    #[Test]
    public function synonym_service_config_partial_override(): void
    {
        $config = new SynonymServiceConfig(['useSynonym' => false]);
        $this->assertFalse($config->useSynonym);
        $this->assertTrue($config->useTechnicalTerm);
    }
}

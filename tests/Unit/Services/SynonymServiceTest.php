<?php

namespace Tests\Unit\Services;

use App\Services\Config\SynonymServiceConfig;
use App\Services\SynonymService;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SynonymService::class)]
class SynonymServiceTest extends TestCase
{
    private SynonymService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // DB・類義語検索を使わない設定でインスタンス生成
        $config = new SynonymServiceConfig(['useSynonym' => false, 'useTechnicalTerm' => false]);
        $this->service = new SynonymService($config);
    }

    // ----------------------------------------------------------------
    // levenshteinUtf8 (static)
    // ----------------------------------------------------------------

    public function test_levenshtein_utf8_identical_strings(): void
    {
        $this->assertEquals(0, SynonymService::levenshteinUtf8('abc', 'abc'));
    }

    public function test_levenshtein_utf8_single_insertion(): void
    {
        $this->assertEquals(1, SynonymService::levenshteinUtf8('ab', 'abc'));
    }

    public function test_levenshtein_utf8_single_deletion(): void
    {
        $this->assertEquals(1, SynonymService::levenshteinUtf8('abc', 'ab'));
    }

    public function test_levenshtein_utf8_single_replacement(): void
    {
        $this->assertEquals(1, SynonymService::levenshteinUtf8('abc', 'axc'));
    }

    public function test_levenshtein_utf8_empty_strings(): void
    {
        $this->assertEquals(0, SynonymService::levenshteinUtf8('', ''));
    }

    public function test_levenshtein_utf8_one_empty(): void
    {
        $this->assertEquals(3, SynonymService::levenshteinUtf8('', 'abc'));
        $this->assertEquals(3, SynonymService::levenshteinUtf8('abc', ''));
    }

    public function test_levenshtein_utf8_multibyte(): void
    {
        // 「東京」→「東」 は 1削除
        $this->assertEquals(1, SynonymService::levenshteinUtf8('東京', '東'));
    }

    // ----------------------------------------------------------------
    // levenshteinNormalizedUtf8 (static)
    // ----------------------------------------------------------------

    public function test_levenshtein_normalized_utf8_identical_returns_one(): void
    {
        $this->assertEquals(1.0, SynonymService::levenshteinNormalizedUtf8('abc', 'abc'));
    }

    public function test_levenshtein_normalized_utf8_both_empty_returns_zero(): void
    {
        $this->assertEquals(0, SynonymService::levenshteinNormalizedUtf8('', ''));
    }

    public function test_levenshtein_normalized_utf8_first_empty(): void
    {
        // '' vs 'abc' → l2/size = 3/3 = 1.0（全く異なる）
        $this->assertEquals(1.0, SynonymService::levenshteinNormalizedUtf8('', 'abc'));
    }

    public function test_levenshtein_normalized_utf8_second_empty(): void
    {
        $this->assertEquals(1.0, SynonymService::levenshteinNormalizedUtf8('abc', ''));
    }

    public function test_levenshtein_normalized_utf8_similar_strings(): void
    {
        $result = SynonymService::levenshteinNormalizedUtf8('abc', 'axc');
        // 1 replacement / max(3,3)=3 → 1.0 - 1/3 ≈ 0.667
        $this->assertGreaterThan(0.5, $result);
        $this->assertLessThan(1.0, $result);
    }

    // ----------------------------------------------------------------
    // getSynonymsFromWord — useSynonym=false, useTechnicalTerm=false
    // ----------------------------------------------------------------

    public function test_get_synonyms_from_word_returns_empty_when_both_disabled(): void
    {
        // useSynonym=false, useTechnicalTerm=false → 元の単語を除いた空配列
        $result = $this->service->getSynonymsFromWord('テスト');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_synonyms_from_word_options_override_config(): void
    {
        // オプションで useSynonym=false を明示 → 空
        $result = $this->service->getSynonymsFromWord('テスト', ['useSynonym' => false, 'useTechnicalTerm' => false]);
        $this->assertEmpty($result);
    }

    public function test_get_synonyms_from_word_excludes_original_word(): void
    {
        // 返り値に元の単語自身は含まれない
        $result = $this->service->getSynonymsFromWord('テスト');
        $this->assertNotContains('テスト', $result);
    }

    // ----------------------------------------------------------------
    // wakati (static) — Igo\Tagger 辞書ファイルが必要
    // ----------------------------------------------------------------

    public function test_wakati_returns_array(): void
    {
        $result = SynonymService::wakati('東京大学');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_wakati_splits_japanese_sentence(): void
    {
        $result = SynonymService::wakati('東京は大きい都市です');
        // 形態素に分割されていること
        $this->assertGreaterThan(1, count($result));
    }

    public function test_wakati_empty_string_returns_empty_array(): void
    {
        $result = SynonymService::wakati('');
        $this->assertIsArray($result);
    }

    public function test_wakati_concatenates_consecutive_nouns(): void
    {
        // 「東京都」は名詞として連結される
        $result = SynonymService::wakati('東京都知事');
        // 名詞の連結結果が含まれていること
        $this->assertNotEmpty($result);
        $this->assertTrue(
            collect($result)->contains(fn ($w) => mb_strlen($w) > 1)
        );
    }
}

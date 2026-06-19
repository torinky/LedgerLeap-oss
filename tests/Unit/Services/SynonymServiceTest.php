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

    // ----------------------------------------------------------------
    // extractAlphanumericTokens (static)
    // ----------------------------------------------------------------

    public function test_extract_alphanumeric_extracts_part_number(): void
    {
        $result = SynonymService::extractAlphanumericTokens('AAA-VVVV-1234B');

        $this->assertContains('AAA-VVVV-1234B', $result);
    }

    public function test_extract_alphanumeric_extracts_multiple_tokens(): void
    {
        $result = SynonymService::extractAlphanumericTokens('部品 ABC-123 と XYZ-456');

        $this->assertContains('ABC-123', $result);
        $this->assertContains('XYZ-456', $result);
    }

    public function test_extract_alphanumeric_handles_no_match(): void
    {
        $result = SynonymService::extractAlphanumericTokens('これはテストです');

        $this->assertEmpty($result);
    }

    public function test_extract_alphanumeric_extracts_single_letter(): void
    {
        $result = SynonymService::extractAlphanumericTokens('A');

        $this->assertContains('A', $result);
    }

    // ----------------------------------------------------------------
    // analyze (static) — Igo\Tagger 辞書ファイルが必要
    // ----------------------------------------------------------------

    public function test_analyze_returns_array_of_token_info(): void
    {
        $result = SynonymService::analyze('東京 部品');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        foreach ($result as $token) {
            $this->assertArrayHasKey('surface', $token);
            $this->assertArrayHasKey('pos', $token);
            $this->assertArrayHasKey('pos_sub', $token);
            $this->assertArrayHasKey('is_proper_noun', $token);
        }
    }

    public function test_analyze_extracts_alphanumeric_tokens(): void
    {
        $result = SynonymService::analyze('部品 ABC-123');

        $alphanumericTokens = array_filter($result, fn ($t) => $t['pos'] === '記号');
        $this->assertNotEmpty($alphanumericTokens);
        $this->assertTrue(
            collect($alphanumericTokens)->contains(fn ($t) => $t['surface'] === 'ABC-123')
        );
    }

    public function test_analyze_detects_proper_noun(): void
    {
        $result = SynonymService::analyze('東京');

        $properNouns = array_filter($result, fn ($t) => $t['is_proper_noun'] === true);
        // 固有名詞が少なくとも1つ検出されること
        $this->assertNotEmpty($properNouns);
    }

    public function test_analyze_empty_string_returns_empty_array(): void
    {
        $result = SynonymService::analyze('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    // analyzeAsWordTokens (static) — Phase B 単語境界インデックス
    // ----------------------------------------------------------------

    public function test_analyze_as_word_tokens_mode_a_nouns_only(): void
    {
        // 連続名詞を個別トークンに分割し、助詞は除外する
        $result = SynonymService::analyzeAsWordTokens('部品 を 修理', 'a');

        $this->assertIsArray($result);
        $this->assertContains('部品', $result);
        $this->assertContains('修理', $result);
        // 助詞 "を" は名詞ではないので含まれない
        $this->assertNotContains('を', $result);
    }

    public function test_analyze_as_word_tokens_mode_a_consecutive_nouns_split(): void
    {
        // 連続する名詞 "作業" "部品" "修理" が結合されず個別に返る
        $result = SynonymService::analyzeAsWordTokens('作業 部品 修理', 'a');

        $this->assertContains('作業', $result);
        $this->assertContains('部品', $result);
        $this->assertContains('修理', $result);
        // analyze() なら "作業部品修理" に結合されるが、analyzeAsWordTokens は個別
        $this->assertNotContains('作業部品修理', $result);
    }

    public function test_analyze_as_word_tokens_mode_b_simple_split(): void
    {
        // 空白区切りで単純分割、助詞も含む
        $result = SynonymService::analyzeAsWordTokens('部品 を 修理', 'b');

        $this->assertIsArray($result);
        $this->assertSame(['部品', 'を', '修理'], $result);
    }

    public function test_analyze_as_word_tokens_mode_b_includes_particles(): void
    {
        $result = SynonymService::analyzeAsWordTokens('部品 を 交換', 'b');

        // mode 'b' は助詞も含む
        $this->assertContains('を', $result);
    }

    public function test_analyze_as_word_tokens_empty_string(): void
    {
        $this->assertSame([], SynonymService::analyzeAsWordTokens('', 'a'));
        $this->assertSame([], SynonymService::analyzeAsWordTokens('', 'b'));
    }

    public function test_analyze_as_word_tokens_default_mode_is_a(): void
    {
        $result = SynonymService::analyzeAsWordTokens('部品 在庫');

        // デフォルト mode='a' で助詞なし、名詞のみ
        $this->assertContains('部品', $result);
        $this->assertContains('在庫', $result);
    }

    public function test_analyze_as_word_tokens_with_alphanumeric(): void
    {
        $result = SynonymService::analyzeAsWordTokens('部品 ABC-123 点検', 'a');

        $this->assertContains('部品', $result);
        $this->assertContains('ABC-123', $result);
        $this->assertContains('点検', $result);
    }
}

<?php

namespace Tests\Unit\Services\Embedding;

use App\Services\Embedding\KeywordEnhancedTextGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KeywordEnhancedTextGeneratorTest extends TestCase
{
    private KeywordEnhancedTextGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new KeywordEnhancedTextGenerator;
    }

    #[Test]
    public function it_extracts_keywords_from_simple_text()
    {
        // Arrange
        $text = '株式会社ABC商事の見積書です。株式会社ABC商事は東京都にあります。';

        // Act
        $keywords = $this->generator->extractKeywordsOnly($text, [
            'min_frequency' => 2,
        ]);

        // Assert
        // 英字を含む会社名は分離される
        $this->assertArrayHasKey('株式会社', $keywords);
        $this->assertArrayHasKey('ABC', $keywords);
        $this->assertArrayHasKey('商事', $keywords);
    }

    #[Test]
    public function it_extracts_alphanumeric_identifiers_separately()
    {
        // Arrange
        // 英数字識別子は単独で抽出される
        $text = '製品番号ABC-12345の在庫を確認してください。製品番号ABC-12345は人気商品です。ABC-12345が重要です。';

        // Act
        $keywords = $this->generator->extractKeywordsOnly($text, [
            'min_frequency' => 2,
        ]);

        // Assert
        // 「ABC-12345」が単独で抽出される
        $this->assertArrayHasKey('ABC-12345', $keywords);
        $this->assertGreaterThanOrEqual(2, $keywords['ABC-12345']);

        // 「製品番号」も別途抽出される
        $this->assertArrayHasKey('製品番号', $keywords);
    }

    #[Test]
    public function it_filters_by_min_frequency()
    {
        // Arrange
        $text = '株式会社ABC商事の見積書です。東京都にあります。';

        // Act
        $keywords = $this->generator->extractKeywordsOnly($text, [
            'min_frequency' => 2,
        ]);

        // Assert
        // 「東京都」は1回しか出現しないので含まれない
        $this->assertArrayNotHasKey('東京都', $keywords);
    }

    #[Test]
    public function it_generates_enhanced_text_with_keywords()
    {
        // Arrange
        $text = '株式会社ABC商事の見積書です。株式会社ABC商事は東京都にあります。';

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'max_keywords' => 5,
            'min_frequency' => 2,
        ]);

        // Assert
        $this->assertStringContainsString('【重要キーワード】', $enhanced);
        $this->assertStringContainsString('株式会社ABC商事', $enhanced);
        $this->assertStringContainsString('---', $enhanced);
        $this->assertStringContainsString($text, $enhanced);
    }

    #[Test]
    public function it_returns_original_text_when_no_keywords_found()
    {
        // Arrange
        $text = 'これはテストです。';

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'min_frequency' => 10, // 高い閾値で何もヒットしない
        ]);

        // Assert
        $this->assertEquals($text, $enhanced);
    }

    #[Test]
    public function it_handles_empty_text()
    {
        // Arrange
        $text = '';

        // Act
        $enhanced = $this->generator->generateEnhancedText($text);

        // Assert
        $this->assertEquals('', $enhanced);
    }

    #[Test]
    public function it_limits_keywords_by_max_keywords()
    {
        // Arrange
        $text = str_repeat('株式会社ABC商事 ', 10).str_repeat('東京都 ', 8).str_repeat('見積書 ', 6);

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'max_keywords' => 2,
            'min_frequency' => 1,
        ]);

        // Assert
        $this->assertStringContainsString('【重要キーワード】', $enhanced);
        // 上位2件のみが含まれる
        $keywordSection = explode('---', $enhanced)[0];
        $keywordCount = count(explode(' ', trim(str_replace('【重要キーワード】', '', $keywordSection))));
        $this->assertLessThanOrEqual(2, $keywordCount);
    }

    #[Test]
    public function it_extracts_compound_nouns()
    {
        // Arrange
        $text = '東京都中央区日本橋の営業部です。東京都中央区日本橋の営業部は有名です。';

        // Act
        $keywords = $this->generator->extractKeywordsOnly($text, [
            'min_frequency' => 2,
        ]);

        // Assert
        // 複合名詞が正しく抽出されること（英字が混ざらない場合）
        $this->assertArrayHasKey('東京都中央区日本橋', $keywords);
        $this->assertArrayHasKey('営業部', $keywords);
    }

    #[Test]
    public function it_handles_real_world_ocr_text()
    {
        // Arrange
        $text = <<<'TEXT'
株式会社ABC商事
御見積書

お客様各位

拝啓 時下ますますご清祥のこととお慶び申し上げます。
平素は格別のご高配を賜り、厚く御礼申し上げます。

さて、この度は株式会社ABC商事製品にご関心をお寄せいただき、誠にありがとうございます。
下記の通り御見積申し上げます。

製品番号: ABC-12345
製品名: 高性能センサー
数量: 100個
単価: 5,000円
合計: 500,000円

お見積もり有効期限: 2025年12月31日まで

株式会社ABC商事
営業部
TEXT;

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'max_keywords' => 10,
            'min_frequency' => 1, // 実際のOCRテキストでは出現頻度が低い可能性がある
        ]);

        // Assert
        $this->assertStringContainsString('【重要キーワード】', $enhanced);
        $this->assertStringContainsString('株式会社ABC商事', $enhanced);
        $this->assertStringContainsString('---', $enhanced);
        $this->assertStringContainsString($text, $enhanced);

        // キーワードセクションに重要な固有名詞が含まれていることを確認
        $keywordSection = explode('---', $enhanced)[0];
        // 英字が分離されるため、個別にチェック
        $this->assertStringContainsString('株式会社', $keywordSection);
        $this->assertStringContainsString('ABC', $keywordSection);
        $this->assertStringContainsString('商事', $keywordSection);
    }
}

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
        $keywords = $this->generator->extract($text, [
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
        $keywords = $this->generator->extract($text, [
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
        $keywords = $this->generator->extract($text, [
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
        $this->assertStringNotContainsString('【重要キーワード】', $enhanced);
        $this->assertStringContainsString('---', $enhanced);
        $this->assertStringContainsString($text, $enhanced);

        // キーワードセクションに分割されたキーワードが含まれていることを確認
        $keywordSection = explode('---', $enhanced)[0];
        $this->assertStringContainsString('株式会社', $keywordSection);
        $this->assertStringContainsString('ABC', $keywordSection);
        $this->assertStringContainsString('商事', $keywordSection);
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
        $this->assertStringNotContainsString('【重要キーワード】', $enhanced);
        // 上位2件のみが含まれる
        $keywordSection = explode('---', $enhanced)[0];
        // キーワードはスペースで区切られていると仮定
        $keywords = array_filter(explode(' ', trim(str_replace(['【固有名詞】', '【重要語】'], '', $keywordSection))));
        $this->assertLessThanOrEqual(2, count($keywords));
    }

    #[Test]
    public function it_extracts_compound_nouns()
    {
        // Arrange
        $text = '東京都中央区日本橋の営業部です。東京都中央区日本橋の営業部は有名です。';

        // Act
        $keywords = $this->generator->extract($text, [
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
        $this->assertStringNotContainsString('【重要キーワード】', $enhanced);
        $this->assertStringContainsString('---', $enhanced);
        $this->assertStringContainsString($text, $enhanced);

        // キーワードセクションに重要な固有名詞が含まれていることを確認
        $keywordSection = explode('---', $enhanced)[0];
        // 英字が分離されるため、個別にチェック
        $this->assertStringContainsString('株式会社', $keywordSection);
        $this->assertStringContainsString('ABC', $keywordSection);
        $this->assertStringContainsString('商事', $keywordSection);
    }

    #[Test]
    public function it_separates_proper_nouns_and_common_nouns()
    {
        // Arrange
        $text = '株式会社ABC商事の田中部長が製品ABC-12345を確認しました。株式会社ABC商事は重要な取引先です。';

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'min_frequency' => 1,
            'max_keywords' => 20,
        ]);

        // Assert
        $this->assertStringContainsString('【固有名詞】', $enhanced);
        $this->assertStringContainsString('【重要語】', $enhanced);
        $this->assertStringContainsString('ABC-12345', $enhanced); // 英数字識別子は固有名詞扱い
        $this->assertStringContainsString($text, $enhanced); // 元のテキストも含む
    }

    #[Test]
    public function it_excludes_stopwords()
    {
        // Arrange
        $text = 'これはテストです。これはテストです。これはテストです。';

        // Act
        $enhanced = $this->generator->generateEnhancedText($text, [
            'min_frequency' => 2,
            'stopwords' => ['これ', 'です'],
        ]);

        // Assert
        // ストップワードは除外される
        $this->assertStringNotContainsString('【固有名詞】 これ', $enhanced);
        $this->assertStringNotContainsString('【重要語】 これ', $enhanced);

        // 「テスト」は含まれる
        $this->assertStringContainsString('テスト', $enhanced);
    }

    #[Test]
    public function it_uses_default_stopwords_from_config()
    {
        // Arrange
        config(['rag.keyword_enhancement.default_stopwords' => ['こと', 'もの']]);
        $text = 'このことは重要なことです。このことは大切なことです。';

        // Act
        $keywords = $this->generator->extract($text, [
            'min_frequency' => 1, // '重要'と'大切'をキーワードとして残すため頻度を1に
        ]);

        // Assert
        // 設定ファイルのストップワードが適用され、キーワードから除外される
        $this->assertArrayNotHasKey('こと', $keywords);
        $this->assertArrayHasKey('重要', $keywords);
        $this->assertArrayHasKey('大切', $keywords);
    }

    #[Test]
    public function it_handles_tenant_specific_stopwords()
    {
        // Arrange
        $text = '株式会社サンプル商事の見積書です。株式会社サンプル商事は東京にあります。';

        // Act
        $keywords = $this->generator->extract($text, [
            'min_frequency' => 1, // '見積書'と'東京'をキーワードとして残すため頻度を1に
            'stopwords' => ['株式会社サンプル商事'], // テナント固有の除外語
        ]);

        // Assert
        // 自社名はキーワードから除外される
        $this->assertArrayNotHasKey('株式会社サンプル商事', $keywords);

        // 他のキーワードは含まれる
        $this->assertArrayHasKey('見積書', $keywords);
        $this->assertArrayHasKey('東京', $keywords);
    }
}

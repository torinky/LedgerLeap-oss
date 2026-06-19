<?php

namespace Tests\Unit\Helpers;

use App\Helpers\SearchHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchHelperTest extends TestCase
{
    #[Test]
    public function it_returns_empty_for_null_or_empty_input(): void
    {
        $this->assertSame('', SearchHelper::normalizeQuery(null));
        $this->assertSame('', SearchHelper::normalizeQuery(''));
    }

    #[Test]
    public function it_converts_fullwidth_digits_to_halfwidth(): void
    {
        $this->assertSame('12345', SearchHelper::normalizeQuery('１２３４５'));
    }

    #[Test]
    public function it_converts_fullwidth_ascii_letters_to_halfwidth(): void
    {
        $this->assertSame('abcXYZ', SearchHelper::normalizeQuery('ａｂｃＸＹＺ'));
    }

    #[Test]
    public function it_converts_fullwidth_symbols_to_halfwidth(): void
    {
        $this->assertSame('A-B/C_D.E', SearchHelper::normalizeQuery('Ａ-Ｂ/Ｃ_Ｄ.Ｅ'));
    }

    #[Test]
    public function it_converts_fullwidth_space_to_halfwidth_space(): void
    {
        // 全角スペース (U+3000) を半角スペースへ
        $this->assertSame('hello world', SearchHelper::normalizeQuery('hello　world'));
    }

    #[Test]
    public function it_preserves_japanese_characters(): void
    {
        $this->assertSame('部品 を 交換', SearchHelper::normalizeQuery('部品 を 交換'));
        $this->assertSame('東京', SearchHelper::normalizeQuery('東京'));
    }

    #[Test]
    public function it_preserves_trailing_whitespace_for_word_separator(): void
    {
        // 単語区切りのための末尾スペースは保持される
        // (trim が必要な呼び出し側は `trimSearch()` を併用する)
        $this->assertSame('部品 ', SearchHelper::normalizeQuery('部品 '));
        $this->assertSame('部品  交換', SearchHelper::normalizeQuery('部品  交換'));
    }

    #[Test]
    public function it_preserves_leading_whitespace_for_typing_state(): void
    {
        // 先頭スペースもユーザの途中入力状態を壊さないよう保持
        $this->assertSame(' hello', SearchHelper::normalizeQuery(' hello'));
    }

    #[Test]
    public function trim_search_removes_leading_and_trailing_whitespace(): void
    {
        $this->assertSame('hello', SearchHelper::trimSearch('  hello  '));
        $this->assertSame('hello', SearchHelper::trimSearch("\u{3000}hello\u{3000}"));
        $this->assertSame('部品', SearchHelper::trimSearch('  部品  '));
        // 内部のスペースは保持される
        $this->assertSame('hello world', SearchHelper::trimSearch('  hello world  '));
    }

    #[Test]
    public function it_handles_mixed_full_and_halfwidth(): void
    {
        $this->assertSame('部品 123', SearchHelper::normalizeQuery('部品 １２３'));
        $this->assertSame('Hello123 部品', SearchHelper::normalizeQuery('Ｈｅｌｌｏ１２３ 部品'));
    }

    #[Test]
    public function it_returns_same_value_for_already_normalized_input(): void
    {
        $this->assertSame('hello world', SearchHelper::normalizeQuery('hello world'));
        $this->assertSame('ABC-123', SearchHelper::normalizeQuery('ABC-123'));
    }
}

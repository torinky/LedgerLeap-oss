<?php

namespace App\Helpers;

class SearchHelper
{
    /**
     * 検索クエリ文字列をサーバ側で正規化する (文字種のみ)。
     *
     *  - 全角英数字 (U+FF10-U+FF19, U+FF21-U+FF3A, U+FF41-U+FF5A)
     *  - 全角スペース (U+3000)
     *  - 全角記号 (ハイフン・スラッシュ・ドット・括弧 など)
     * を mb_convert_kana で半角へ統一する。
     *
     * ユーザ要望: 「数字、アルファベット、記号はサーバで強制半角変換していい」
     * → フラグ `as` (全角英数 → 半角英数, 全角スペース → 半角スペース) のみ使用。
     * `k` (カタカナ) や `h` (ひらがな) は変換対象外 — ひらがな `を` を
     * 半角カタカナ `ｦ` に破壊的変換しないため。
     *
     * 追加で:
     *  - 全角スペース (U+3000) → 半角スペース (U+0020)
     *  - 全角ハイフン (U+FF0D), 全角スラッシュ (U+FF0F), 全角ドット (U+FF0E) 等
     *    については 'r' フラグで全角 → 半角記号へ変換
     *
     * **重要**: この関数では trim() を行わない。
     * ユーザが単語区切りの意図で末尾にスペース (例: 「部品 」) を
     * 入力した場合に、それを保存してサジェスト/検索条件として
     * 保持する必要があるため。
     * 末尾/先頭スペースを捨てたい呼び出し側は `trimSearch()` を併用する。
     */
    public static function normalizeQuery(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = mb_convert_kana($value, 'asr', 'UTF-8');
        $normalized = str_replace("\u{3000}", ' ', $normalized);

        return $normalized;
    }

    /**
     * 検索クエリ文字列の両端の空白を取り除く。
     *
     * `normalizeQuery()` は文字種のみ正規化し、trim() しない設計のため、
     * 末尾/先頭のスペース削除は明示的にこのメソッドを呼ぶ。
     * 検索条件として永続化する場合など、空白が意味を持たない文脈で使用する。
     *
     * 削除対象: 半角スペース, タブ, 改行, 復帰, NULL, 垂直タブ, **全角スペース (U+3000)**
     * 後半の全角スペースが重要 — normalizeQuery() は U+3000 → ' ' に変換するため、
     * 既に半角スペースになった状態でも trim() で取り除ける必要がある。
     */
    public static function trimSearch(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\u{3000}");
    }

    /**
     * Mroonga の検索クエリから実質的なキーワードを抽出する
     *
     * @param  string|null  $query  検索クエリ
     * @return array 抽出されたキーワードの配列
     */
    public static function extractKeywords(?string $query): array
    {
        if (empty($query)) {
            return [];
        }

        // 1. Mroonga 特有の演算子と記号を除去
        // OR, AND, NOT, +, -, *, "(", ")", "*D+", "*D", "D+" など
        $clean = preg_replace('/\b(OR|AND|NOT)\b/i', ' ', $query);
        $clean = preg_replace('/[\+\-\*\(\)]/', ' ', $clean);
        $clean = preg_replace('/\*D\+?/', ' ', $clean);

        // 2. 空白で分割して重複を排除
        $words = preg_split('/[\s　]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);

        return array_unique($words);
    }

    /**
     * テキスト内にキーワードが含まれているか判定する（大文字小文字無視、全角半角正規化なしの簡易版）
     *
     * @param  string|null  $text  対象テキスト
     * @param  array  $keywords  キーワード配列
     * @return bool 含まれていれば true
     */
    public static function hasHit(?string $text, array $keywords): bool
    {
        if (empty($text) || empty($keywords)) {
            return false;
        }

        foreach ($keywords as $word) {
            if (mb_stripos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * テキスト内のキーワードを <mark> タグでハイライトする
     *
     * @param  string|null  $text  対象テキスト
     * @param  array  $keywords  キーワード配列
     * @param  string  $class  ハイライトタグに付与するクラス
     * @return string ハイライト済みのHTML
     */
    public static function highlight(?string $text, array $keywords, string $class = 'bg-yellow-200 text-black px-0.5 rounded', bool $shouldEscape = true): string
    {
        if (empty($text)) {
            return '';
        }

        if (empty($keywords)) {
            return $shouldEscape ? e($text) : $text;
        }

        $highlighted = $shouldEscape ? e($text) : $text;

        // 1. 長いキーワードから順にソート（"Apple" と "App" がある場合、"Apple" を優先するため）
        usort($keywords, function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        // 2. 特殊文字をエスケープして正規表現を作成
        $escapedKeywords = array_map(function ($word) {
            return preg_quote($word, '/');
        }, $keywords);

        // 3. 一度の preg_replace で置換（再帰的なハイライトを防ぐ）
        // 単一の正規表現 (word1|word2|...) を使うことで、一度置換された部分は次のマッチング対象にならない
        $pattern = '/('.implode('|', $escapedKeywords).')/iu';
        $highlighted = preg_replace($pattern, '<mark class="'.$class.'">$1</mark>', $highlighted);

        return $highlighted;
    }

    /**
     * 添付ファイルデータ（content_attachedの要素）にキーワードが含まれているか判定する
     *
     * @param  array|null  $fileData  ファイルデータ
     * @param  array  $keywords  キーワード配列
     * @return bool ヒットすれば true
     */
    public static function isFileDataHit(?array $fileData, array $keywords): bool
    {
        if (empty($fileData) || empty($keywords)) {
            return false;
        }

        // 1. ファイル名チェック
        if (self::hasHit($fileData['name'] ?? null, $keywords) || self::hasHit($fileData['filename'] ?? null, $keywords)) {
            return true;
        }

        // 2. テキスト内容チェック (様々なキーの可能性を考慮、モック用キーも含む)
        $textKeys = [
            'extracted_text',
            'text',
            'ocr_text',
            'content',
            'mock_vlm_text',
            'mock_ocr_text',
            'mock_tika_text',
            'mock_preview_text',
        ];

        foreach ($textKeys as $key) {
            if (isset($fileData[$key]) && is_string($fileData[$key])) {
                if (self::hasHit($fileData[$key], $keywords)) {
                    return true;
                }
            }
        }

        // 3. ネストされた meta['content'] チェック
        if (isset($fileData['meta']['content']) && is_string($fileData['meta']['content'])) {
            if (self::hasHit($fileData['meta']['content'], $keywords)) {
                return true;
            }
        }

        return false;
    }
}

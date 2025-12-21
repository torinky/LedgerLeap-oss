<?php

namespace App\Helpers;

class SearchHelper
{
    /**
     * Mroonga の検索クエリから実質的なキーワードを抽出する
     *
     * @param string|null $query 検索クエリ
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
     * @param string|null $text 対象テキスト
     * @param array $keywords キーワード配列
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
     * @param string|null $text 対象テキスト
     * @param array $keywords キーワード配列
     * @param string $class ハイライトタグに付与するクラス
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
        $pattern = '/(' . implode('|', $escapedKeywords) . ')/iu';
        $highlighted = preg_replace($pattern, '<mark class="' . $class . '">$1</mark>', $highlighted);

        return $highlighted;
    }

    /**
     * 添付ファイルデータ（content_attachedの要素）にキーワードが含まれているか判定する
     *
     * @param array|null $fileData ファイルデータ
     * @param array $keywords キーワード配列
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

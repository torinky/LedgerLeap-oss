<?php

namespace App\Services\Embedding;

use Igo\Tagger;

/**
 * OCRテキストから重要キーワードを抽出し、検索精度向上のために先頭に埋め込むサービス
 *
 * Phase 2.5: 固有名詞・記号の先頭埋め込み実装
 * - 形態素解析による固有名詞・記号の自動抽出
 * - 頻度分析による重要キーワードの特定
 * - Ruriモデルの大語彙特性を活用したベクトル化準備
 */
class KeywordEnhancedTextGenerator
{
    private Tagger $tagger;

    public function __construct()
    {
        $this->tagger = new Tagger;
    }

    /**
     * OCRテキストを拡張し、重要キーワードを先頭に埋め込む
     *
     * @param  string  $ocrText  元のOCRテキスト
     * @param  array  $options  オプション設定
     *                          - max_keywords: 最大キーワード数（デフォルト: 20）
     *                          - min_frequency: 最小出現回数（デフォルト: 2）
     *                          - target_types: 対象品詞（デフォルト: ['固有名詞', '名詞', '記号', '数']）
     * @return string 拡張後のテキスト
     */
    public function generateEnhancedText(string $ocrText, array $options = []): string
    {
        $maxKeywords = $options['max_keywords'] ?? 20;
        $minFrequency = $options['min_frequency'] ?? 2;
        $targetTypes = $options['target_types'] ?? ['固有名詞', '名詞', '記号', '数'];

        if (empty($ocrText)) {
            return '';
        }

        // 1. 形態素解析
        $morphemes = $this->tagger->parse($ocrText);

        // 2. 重要語抽出（頻度順）
        $keywords = $this->extractKeywords($morphemes, $targetTypes, $minFrequency);

        if (empty($keywords)) {
            return $ocrText;
        }

        // 3. 頻度順にソート
        arsort($keywords);

        // 4. 上位N件を取得
        $topKeywords = array_slice(array_keys($keywords), 0, $maxKeywords);

        // 5. テキスト構築
        return $this->buildEnhancedText($topKeywords, $ocrText);
    }

    private function extractKeywords(array $morphemes, array $targetTypes, int $minFrequency): array
    {
        $keywords = [];
        $compoundToken = '';
        $isAlphanumericSequence = false;

        // 区切り記号リスト（これらは複合語に含めない）
        $separatorSymbols = ['。', '、', '，', '．', '！', '？', '：', '；', '　', ' ', "\n", "\r", "\t"];

        foreach ($morphemes as $morpheme) {
            $pos = $morpheme->feature[0]; // 品詞
            $posDetail = $morpheme->feature[1] ?? '';
            $surface = $morpheme->surface;

            // 区切り記号の場合は複合トークンを終了
            if (in_array($surface, $separatorSymbols, true)) {
                if (mb_strlen($compoundToken) > 0) {
                    $keywords[$compoundToken] = ($keywords[$compoundToken] ?? 0) + 1;
                    $compoundToken = '';
                    $isAlphanumericSequence = false;
                }

                continue;
            }

            // 英数字・記号の連続（識別子パターン）を検出
            $isAlphanumeric = $this->isAlphanumericOrSymbol($surface);

            if (in_array($pos, ['名詞', '記号', '数'])) {
                // 英数字シーケンスの開始または継続
                if ($isAlphanumeric) {
                    if (! $isAlphanumericSequence && mb_strlen($compoundToken) > 0) {
                        // 一般名詞の後に英数字が来た場合、一般名詞は分離
                        $keywords[$compoundToken] = ($keywords[$compoundToken] ?? 0) + 1;
                        $compoundToken = '';
                    }
                    $compoundToken .= $surface;
                    $isAlphanumericSequence = true;
                } else {
                    // 一般的な日本語名詞
                    if ($isAlphanumericSequence) {
                        // 英数字シーケンスの後に日本語名詞が来た場合、英数字を分離
                        if (mb_strlen($compoundToken) > 0) {
                            $keywords[$compoundToken] = ($keywords[$compoundToken] ?? 0) + 1;
                            $compoundToken = '';
                        }
                        $isAlphanumericSequence = false;
                    }
                    $compoundToken .= $surface;
                }
            } else {
                // 名詞・記号・数以外の品詞が来たら複合トークンを終了
                if (mb_strlen($compoundToken) > 0) {
                    $keywords[$compoundToken] = ($keywords[$compoundToken] ?? 0) + 1;
                    $compoundToken = '';
                    $isAlphanumericSequence = false;
                }

                // その他の品詞も抽出対象に含まれる場合は追加（2文字以上）
                if (in_array($pos, $targetTypes) && mb_strlen($surface) > 1) {
                    $keywords[$surface] = ($keywords[$surface] ?? 0) + 1;
                }
            }
        }

        // 最後の複合トークンを追加
        if (mb_strlen($compoundToken) > 0) {
            $keywords[$compoundToken] = ($keywords[$compoundToken] ?? 0) + 1;
        }

        // 最小出現回数でフィルタリング
        return array_filter($keywords, fn ($freq) => $freq >= $minFrequency);
    }

    /**
     * 文字列が英数字または記号（日本語以外）かを判定
     */
    private function isAlphanumericOrSymbol(string $text): bool
    {
        // 英字、数字、ハイフン、アンダースコアなどの記号を含む
        return preg_match('/^[A-Za-z0-9\-_@#]+$/u', $text) === 1;
    }

    /**
     * キーワードと元のテキストから拡張テキストを構築
     *
     * @param  array  $keywords  重要キーワード配列
     * @param  string  $originalText  元のテキスト
     * @return string 拡張後のテキスト
     */
    private function buildEnhancedText(array $keywords, string $originalText): string
    {
        if (empty($keywords)) {
            return $originalText;
        }

        // キーワードセクションを作成（スペース区切り）
        $keywordSection = implode(' ', $keywords);

        // フォーマット: [重要キーワード] + セパレータ + [元のテキスト]
        return "【重要キーワード】 {$keywordSection}\n\n---\n\n{$originalText}";
    }

    /**
     * キーワードのみを抽出（テスト・デバッグ用）
     *
     * @param  string  $text  解析対象テキスト
     * @param  array  $options  オプション設定
     * @return array キーワード配列（頻度順）
     */
    public function extractKeywordsOnly(string $text, array $options = []): array
    {
        $minFrequency = $options['min_frequency'] ?? 2;
        $targetTypes = $options['target_types'] ?? ['固有名詞', '名詞', '記号', '数'];

        if (empty($text)) {
            return [];
        }

        $morphemes = $this->tagger->parse($text);
        $keywords = $this->extractKeywords($morphemes, $targetTypes, $minFrequency);

        arsort($keywords);

        return $keywords;
    }
}

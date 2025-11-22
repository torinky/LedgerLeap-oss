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

    public function generateEnhancedText(string $ocrText, array $options = []): string
    {
        $maxKeywords = $options['max_keywords'] ?? 20;
        $minFrequency = $options['min_frequency'] ?? 2;
        $targetTypes = $options['target_types'] ?? ['固有名詞', '名詞', '記号', '数'];
        $stopwords = $options['stopwords'] ?? $this->getDefaultStopwords();

        if (empty($ocrText)) {
            return '';
        }

        // 1. 形態素解析
        $morphemes = $this->tagger->parse($ocrText);

        // 2. 重要語抽出（品詞別）
        $extractedKeywords = $this->extractKeywords($morphemes, $targetTypes, $minFrequency, $stopwords);

        if (empty($extractedKeywords['proper_nouns']) && empty($extractedKeywords['common_nouns'])) {
            return $ocrText;
        }

        // 3. 品詞別にソートして上位を取得
        $properNouns = $this->getTopKeywords($extractedKeywords['proper_nouns'], $maxKeywords / 2);
        $commonNouns = $this->getTopKeywords($extractedKeywords['common_nouns'], $maxKeywords / 2);

        // 4. テキスト構築（品詞別にラベル）
        return $this->buildEnhancedText($properNouns, $commonNouns, $ocrText);
    }

    private function extractKeywords(array $morphemes, array $targetTypes, int $minFrequency, array $stopwords): array
    {
        $properNouns = [];  // 固有名詞
        $commonNouns = [];  // 一般名詞
        $compoundToken = '';
        $isAlphanumericSequence = false;
        $currentPosDetail = '';

        // 区切り記号リスト
        $separatorSymbols = ['。', '、', '，', '．', '！', '？', '：', '；', '　', ' ', "\n", "\r", "\t"];

        foreach ($morphemes as $morpheme) {
            $pos = $morpheme->feature[0]; // 品詞
            $posDetail = $morpheme->feature[1] ?? ''; // 品詞細分類
            $surface = $morpheme->surface;

            // 区切り記号の場合は複合トークンを終了
            if (in_array($surface, $separatorSymbols, true)) {
                if (mb_strlen($compoundToken) > 0) {
                    $this->addKeyword($compoundToken, $currentPosDetail, $properNouns, $commonNouns, $stopwords);
                    $compoundToken = '';
                    $isAlphanumericSequence = false;
                    $currentPosDetail = '';
                }

                continue;
            }

            // 英数字・記号の連続を検出
            $isAlphanumeric = $this->isAlphanumericOrSymbol($surface);

            if (in_array($pos, ['名詞', '記号', '数'])) {
                // 品詞細分類を記録（固有名詞判定用）
                if ($pos === '名詞' && empty($currentPosDetail)) {
                    $currentPosDetail = $posDetail;
                }

                if ($isAlphanumeric) {
                    if (! $isAlphanumericSequence && mb_strlen($compoundToken) > 0) {
                        $this->addKeyword($compoundToken, $currentPosDetail, $properNouns, $commonNouns, $stopwords);
                        $compoundToken = '';
                        $currentPosDetail = '';
                    }
                    $compoundToken .= $surface;
                    $isAlphanumericSequence = true;
                } else {
                    if ($isAlphanumericSequence) {
                        if (mb_strlen($compoundToken) > 0) {
                            $this->addKeyword($compoundToken, 'alphanumeric', $properNouns, $commonNouns, $stopwords);
                            $compoundToken = '';
                            $currentPosDetail = '';
                        }
                        $isAlphanumericSequence = false;
                    }
                    $compoundToken .= $surface;
                    if (empty($currentPosDetail)) {
                        $currentPosDetail = $posDetail;
                    }
                }
            } else {
                if (mb_strlen($compoundToken) > 0) {
                    $this->addKeyword($compoundToken, $currentPosDetail, $properNouns, $commonNouns, $stopwords);
                    $compoundToken = '';
                    $isAlphanumericSequence = false;
                    $currentPosDetail = '';
                }

                if (in_array($pos, $targetTypes) && mb_strlen($surface) > 1) {
                    $this->addKeyword($surface, $posDetail, $properNouns, $commonNouns, $stopwords);
                }
            }
        }

        if (mb_strlen($compoundToken) > 0) {
            $this->addKeyword($compoundToken, $currentPosDetail, $properNouns, $commonNouns, $stopwords);
        }

        // 最小出現回数でフィルタリング
        $properNouns = array_filter($properNouns, fn ($freq) => $freq >= $minFrequency);
        $commonNouns = array_filter($commonNouns, fn ($freq) => $freq >= $minFrequency);

        return [
            'proper_nouns' => $properNouns,
            'common_nouns' => $commonNouns,
        ];
    }

    /**
     * キーワードを品詞別に分類して追加
     */
    private function addKeyword(string $keyword, string $posDetail, array &$properNouns, array &$commonNouns, array $stopwords): void
    {
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return;
        }

        // ストップワードチェック
        if (in_array($keyword, $stopwords, true)) {
            return;
        }

        // 固有名詞または英数字識別子
        if ($posDetail === '固有名詞' || $posDetail === 'alphanumeric') {
            $properNouns[$keyword] = ($properNouns[$keyword] ?? 0) + 1;
        } else {
            // 一般名詞
            $commonNouns[$keyword] = ($commonNouns[$keyword] ?? 0) + 1;
        }
    }

    /**
     * 頻度順にソートして上位を取得
     */
    private function getTopKeywords(array $keywords, int $limit): array
    {
        arsort($keywords);

        return array_slice(array_keys($keywords), 0, (int) $limit);
    }

    /**
     * デフォルトストップワード（テナント設定から取得予定）
     */
    private function getDefaultStopwords(): array
    {
        // TODO: テナント設定から取得
        // config('rag.stopwords.tenant_id') など
        return config('rag.keyword_enhancement.default_stopwords', []);
    }

    /**
     * 文字列が英数字または記号（日本語以外）かを判定
     */
    private function isAlphanumericOrSymbol(string $text): bool
    {
        // 英字、数字、ハイフン、アンダースコアなどの記号を含む
        return preg_match('/^[A-Za-z0-9\-_@#]+$/u', $text) === 1;
    }

    private function buildEnhancedText(array $properNouns, array $commonNouns, string $originalText): string
    {
        $sections = [];

        // 固有名詞セクション
        if (! empty($properNouns)) {
            $sections[] = '【固有名詞】 '.implode(' ', $properNouns);
        }

        // 一般名詞セクション
        if (! empty($commonNouns)) {
            $sections[] = '【重要語】 '.implode(' ', $commonNouns);
        }

        if (empty($sections)) {
            return $originalText;
        }

        // フォーマット: [固有名詞] [重要語] + セパレータ + [元のテキスト]
        return implode("\n", $sections)."\n\n---\n\n".$originalText;
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
        $stopwords = $options['stopwords'] ?? $this->getDefaultStopwords();

        if (empty($text)) {
            return [];
        }

        $morphemes = $this->tagger->parse($text);
        $result = $this->extractKeywords($morphemes, $targetTypes, $minFrequency, $stopwords);

        // 固有名詞と一般名詞を統合して返す
        $allKeywords = array_merge($result['proper_nouns'], $result['common_nouns']);
        arsort($allKeywords);

        return $allKeywords;
    }
}

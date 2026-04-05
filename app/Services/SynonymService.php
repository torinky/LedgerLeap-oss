<?php

namespace App\Services;

use App\Models\Synonym\TechnicalTermGroup;
use App\Models\Synonym\Word;
use App\Services\Config\SynonymServiceConfig;
use Igo\Tagger;
use Illuminate\Database\Eloquent\Collection;

/**
 * 類義語関連の操作を提供するサービスクラス
 */
class SynonymService
{
    private SynonymServiceConfig $config;

    /**
     * クラスの新しいインスタンスを構築します。
     *
     * @param  SynonymServiceConfig|null  $config  類義語サービスの構成オブジェクト。指定されていない場合は、SynonymServiceConfigの新しいインスタンスが作成されます。
     */
    public function __construct(?SynonymServiceConfig $config = null)
    {
        $this->config = $config ?? new SynonymServiceConfig;
    }

    /**
     * 指定された単語の形態を取得します。
     *
     * @param  string  $lemma  単語の基本形
     * @return Collection
     */
    public static function getWords($lemma)
    {
        return Word::where('lemma', $lemma)->get();
    }

    /**
     * 指定された単語の類義語を取得します。
     *
     * @param  string  $word  類義語を取得する単語
     * @param  array  $options  オプションの配列
     *                          - 'useSynonym': (bool) 類義語を使用するかどうか。SynonymServiceConfigの値がデフォルト値として使用されます。
     *                          - 'useTechnicalTerm': (bool) 技術用語を使用するかどうか。SynonymServiceConfigの値がデフォルト値として使用されます。
     * @return array 指定された単語の類義語の配列
     */
    public function getSynonymsFromWord($word, array $options = [])
    {
        return array_values(array_map(
            static fn (array $candidate) => $candidate['term'],
            array_filter(
                $this->getSearchTermsFromWord($word, $options),
                static fn (array $candidate) => $candidate['term'] !== $word
            )
        ));
    }

    /**
     * 指定された単語の検索候補を kind 付きで取得します。
     *
     * @param  string  $word  検索候補を取得する単語
     * @param  array  $options  オプションの配列
     *                          - 'useSynonym': (bool) 類義語を使用するかどうか。SynonymServiceConfigの値がデフォルト値として使用されます。
     *                          - 'useTechnicalTerm': (bool) 技術用語を使用するかどうか。SynonymServiceConfigの値がデフォルト値として使用されます。
     * @return array<int, array{term:string, kind:string}>
     */
    public function getSearchTermsFromWord($word, array $options = []): array
    {
        $useSynonym = $options['useSynonym'] ?? $this->config->useSynonym;
        $useTechnicalTerm = $options['useTechnicalTerm'] ?? $this->config->useTechnicalTerm;

        $candidates = [];

        $addCandidate = function (mixed $term, string $kind) use (&$candidates): void {
            $term = trim((string) $term);
            if ($term === '') {
                return;
            }

            if (! isset($candidates[$term])) {
                $candidates[$term] = [
                    'term' => $term,
                    'kind' => $kind,
                ];
            }
        };

        $addCandidate($word, 'original');

        $seedTerms = [$word];

        if ($useTechnicalTerm) {
            $technicalTermGroups = TechnicalTermGroup::whereJsonContains('synonyms', $word)->get();
            foreach ($technicalTermGroups as $group) {
                foreach ((array) $group->synonyms as $technicalTerm) {
                    $addCandidate($technicalTerm, 'technical');
                    $seedTerms[] = $technicalTerm;
                }
            }
        }

        $seedTerms = array_values(array_unique(array_filter(array_map(
            static fn ($term) => trim((string) $term),
            $seedTerms
        ), static fn (string $term) => $term !== '')));

        if ($useSynonym) {
            foreach ($seedTerms as $synonymSeed) {
                $words = self::getWords($synonymSeed);
                if ($words->isEmpty()) {
                    continue;
                }

                foreach ($words as $targetWord) {
                    foreach ($targetWord->synonyms()->pluck('lemma')->toArray() as $synonym) {
                        $addCandidate($synonym, 'synonym');
                    }
                }
            }
        }

        return array_values($candidates);
    }

    /**
     * https://php-archive.net/php/compound-nouns/
     *
     * @return array
     */
    public static function wakati($inputText)
    {
        $igo = new Tagger([
            'dict_dir' => base_path('vendor/logue/igo-php/ipadic'),
        ]);
        //        $str = "これは形態素解析の実験結果です。";
        $result = $igo->parse($inputText);

        $noun = '';
        $words = [];
        foreach ($result as $value) {
            if ($value->feature[0] === '名詞') {
                $noun .= $value->surface;
            } else {
                if (mb_strlen($noun)) {
                    $words[] = $noun;
                }
                $noun = '';
                $words[] = $value->surface;
            }
        }
        if (mb_strlen($noun)) {
            $words[] = $noun;
        }

        //        dd($words);
        return $words;
    }

    /**
     * 文章の類似度をコサイン類似度を用いて求めます
     * https://tech.excite.co.jp/entry/2021/11/29/175826
     *
     * @param  string  $text1  文章1つ目
     * @param  string  $text2  文章2つ目
     */
    public function cosineSimilarity(string $text1, string $text2): float
    {
        // 文章を形態素に分解

        $igo = new Tagger([
            'dict_dir' => base_path('vendor/logue/igo-php/ipadic'),
        ]);
        $text1Corpus = $igo->wakati($text1);
        $text2Corpus = $igo->wakati($text2);

        //        $text1Corpus = getWakachiList($text1);
        //        $text2Corpus = getWakachiList($text2);

        // 2つの文章の形態素を抽出
        $allCorpus = array_unique(array_merge($text1Corpus, $text2Corpus));

        // コサイン類似度の計算に必要な分子分母の変数
        $c = 0;
        $m1 = 0;
        $m2 = 0;

        foreach ($allCorpus as $word) {
            // 文章1に対象の形態素があるかどうか（あれば1、なければ0）
            $n1 = (in_array($word, $text1Corpus)) ? 1 : 0;
            // 文章2に対象の形態素があるかどうか（あれば1、なければ0）
            $n2 = (in_array($word, $text2Corpus)) ? 1 : 0;

            // コサイン類似度に利用する分子分母の数値を計算
            $c += ($n1 * $n2);
            $m1 += $n1 * $n1;
            $m2 += $n2 * $n2;
        }

        // コサイン類似度の計算
        if ($m1 === 0 || $m2 === 0) {
            return 0;
        }

        return $c / (sqrt($m1) * sqrt($m2));
    }

    /**
     * https://qiita.com/mpyw/items/2b636827730e06c71e3d
     *
     * @return float|int
     */
    public static function levenshteinNormalizedUtf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)
    {
        $l1 = mb_strlen($s1, 'UTF-8');
        $l2 = mb_strlen($s2, 'UTF-8');
        $size = max($l1, $l2);
        if (! $size) {
            return 0;
        }
        if (! $s1) {
            return $l2 / $size;
        }
        if (! $s2) {
            return $l1 / $size;
        }

        return 1.0 - self::levenshteinUtf8($s1, $s2, $cost_ins, $cost_rep, $cost_del) / $size;
    }

    public static function levenshteinUtf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)
    {
        $s1 = preg_split('//u', $s1, -1, PREG_SPLIT_NO_EMPTY);
        $s2 = preg_split('//u', $s2, -1, PREG_SPLIT_NO_EMPTY);
        $l1 = count($s1);
        $l2 = count($s2);
        if (! $l1) {
            return $l2 * $cost_ins;
        }
        if (! $l2) {
            return $l1 * $cost_del;
        }
        $p1 = array_fill(0, $l2 + 1, 0);
        $p2 = array_fill(0, $l2 + 1, 0);
        for ($i2 = 0; $i2 <= $l2; $i2++) {
            $p1[$i2] = $i2 * $cost_ins;
        }
        foreach ($s1 as $i1Value) {
            $p2[0] = $p1[0] + $cost_ins;
            for ($i2 = 0; $i2 < $l2; $i2++) {
                $c0 = $p1[$i2] + ($i1Value === $s2[$i2] ? 0 : $cost_rep);
                $c1 = $p1[$i2 + 1] + $cost_del;
                if ($c1 < $c0) {
                    $c0 = $c1;
                }
                $c2 = $p2[$i2] + $cost_ins;
                if ($c2 < $c0) {
                    $c0 = $c2;
                }
                $p2[$i2 + 1] = $c0;
            }
            $tmp = $p1;
            $p1 = $p2;
            $p2 = $tmp;
        }

        return $p1[$l2];
    }
}

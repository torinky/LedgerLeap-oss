<?php

namespace App\Services;

use App\Models\Synonym\Sense;
use App\Models\Synonym\Synset;
use App\Models\Synonym\Word;
use Igo\Tagger;
use Illuminate\Database\Eloquent\Collection;

/**
 * 類義語関連の操作を提供するサービスクラス
 */
class SynonymService
{
    /**
     * 指定された単語の形態を取得します。
     *
     * @param string $lemma 単語の基本形
     * @return Collection
     */
    public static function getWords($lemma)
    {
        return Word::where('lemma', $lemma)->get();
    }

    public static function getSynonymsFromWord($word)
    {
        $words = self::getWords($word);
        $synonyms = [];
        if (count($words)) {
            foreach ($words as $targetWord) {
                $synonyms = array_merge($synonyms, self::getSynonyms($targetWord->wordid));
            }
        }

        return $synonyms;
    }

    /**
     * 指定された単語IDと言語に関連する類義語を取得します。
     *
     * @param int $wordid 単語ID
     * @param string $lang 言語
     * @return array
     */
    public static function getSynonyms($wordid, $lang = 'jpn')
    {
        $senses = self::getSenses($wordid);
        $synonyms = [];
        foreach ($senses as $sense) {
            $synset = self::getSynset($sense->synset);
            $words = self::getWordsFromSynset($synset->synset, $lang);
            $synonyms[$synset->name] = $words->pluck('lemma')->toArray();
        }

        return $synonyms;
    }

    /**
     * 指定された単語IDに関連する意味情報を取得します。
     *
     * @param int $wordid 単語ID
     * @return Collection
     */
    public static function getSenses($wordid)
    {
        return Sense::where('wordid', $wordid)->get();
    }

    /**
     * 指定されたシンセットに関連する情報を取得します。
     *
     * @param string $synset シンセット
     * @return Synset|null
     */
    public static function getSynset($synset)
    {
        return Synset::where('synset', $synset)->first();
    }

    /**
     * 指定されたシンセットと言語に関連する単語形態を取得します。
     *
     * @param string $synset シンセット
     * @param string $lang 言語
     * @return Collection
     */
    public static function getWordsFromSynset($synset, $lang = 'jpn')
    {
        return Word::whereHas('Senses', function ($query) use ($synset, $lang) {
            $query->where('synset', $synset)
                ->whereHas('word', function ($query) use ($lang) {
                    $query->where('lang', $lang);
                });
        })->get();
    }

    /**
     * https://php-archive.net/php/compound-nouns/
     *
     * @return array
     */
    public static function wakati($inputText)
    {
        $igo = new Tagger();
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
     * @param string $text1 文章1つ目
     * @param string $text2 文章2つ目
     */
    public function cosineSimilarity(string $text1, string $text2): float
    {
        // 文章を形態素に分解

        $igo = new Tagger();
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
        if (!$size) {
            return 0;
        }
        if (!$s1) {
            return $l2 / $size;
        }
        if (!$s2) {
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
        if (!$l1) {
            return $l2 * $cost_ins;
        }
        if (!$l2) {
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

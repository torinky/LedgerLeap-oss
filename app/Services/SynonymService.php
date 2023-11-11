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
    public function getWords($lemma)
    {
        return Word::where('lemma', $lemma)->get();
    }

    /**
     * 指定された単語IDと言語に関連する類義語を取得します。
     *
     * @param int $wordid 単語ID
     * @param string $lang 言語
     * @return array
     */
    public function getSynonyms($wordid, $lang = "jpn")
    {
        $senses = $this->getSenses($wordid);
        $synonyms = [];
        foreach ($senses as $sense) {
            $synset = $this->getSynset($sense->synset);
            $words = $this->getWordsFromSynset($synset->synset, $lang);
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
    public function getSenses($wordid)
    {
        return Sense::where('wordid', $wordid)->get();
    }

    /**
     * 指定されたシンセットに関連する情報を取得します。
     *
     * @param string $synset シンセット
     * @return Synset|null
     */
    public function getSynset($synset)
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
    public function getWordsFromSynset($synset, $lang)
    {
        return Word::whereHas('Senses', function ($query) use ($synset, $lang) {
            $query->where('synset', $synset)
                ->whereHas('word', function ($query) use ($lang) {
                    $query->where('lang', $lang);
                });
        })->get();
    }

    /**
     *
     * https://php-archive.net/php/compound-nouns/
     *
     * @param $inputText
     * @return array
     */
    public function wakati($inputText)
    {
        $igo = new Tagger();
//        $str = "これは形態素解析の実験結果です。";
        $result = $igo->parse($inputText);

        $noun = "";
        $words = array();
        foreach ($result as $value) {
            if ($value->feature[0] === "名詞") {
                $noun .= $value->surface;
            } else {
                if (mb_strlen($noun)) $words[] = $noun;
                $noun = "";
                $words[] = $value->surface;
            }
        }
        if (mb_strlen($noun)) $words[] = $noun;
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
            $n1 = (array_search($word, $text1Corpus) !== false) ? 1 : 0;
            // 文章2に対象の形態素があるかどうか（あれば1、なければ0）
            $n2 = (array_search($word, $text2Corpus) !== false) ? 1 : 0;

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
     *
     * https://qiita.com/mpyw/items/2b636827730e06c71e3d
     *
     * @param $s1
     * @param $s2
     * @param $cost_ins
     * @param $cost_rep
     * @param $cost_del
     * @return float|int
     */
    public function levenshtein_normalized_utf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)
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
        return 1.0 - levenshtein_utf8($s1, $s2, $cost_ins, $cost_rep, $cost_del) / $size;
    }

    public function levenshtein_utf8($s1, $s2, $cost_ins = 1, $cost_rep = 1, $cost_del = 1)
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
        for ($i2 = 0; $i2 <= $l2; ++$i2) {
            $p1[$i2] = $i2 * $cost_ins;
        }
        for ($i1 = 0; $i1 < $l1; ++$i1) {
            $p2[0] = $p1[0] + $cost_ins;
            for ($i2 = 0; $i2 < $l2; ++$i2) {
                $c0 = $p1[$i2] + ($s1[$i1] === $s2[$i2] ? 0 : $cost_rep);
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

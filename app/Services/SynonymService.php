<?php

namespace App\Services;

use App\Models\Synonym\Sense;
use App\Models\Synonym\Synset;
use App\Models\Synonym\Word;
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
}

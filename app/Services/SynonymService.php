<?php

namespace App\Services;

use App\Models\WordForm;
use App\Models\WordSense;
use App\Models\WordSynset;
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
        return WordForm::where('lemma', $lemma)->get();
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
        return WordSense::where('wordid', $wordid)->get();
    }

    /**
     * 指定されたシンセットに関連する情報を取得します。
     *
     * @param string $synset シンセット
     * @return WordSynset|null
     */
    public function getSynset($synset)
    {
        return WordSynset::where('synset', $synset)->first();
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
        return WordForm::whereHas('wordSenses', function ($query) use ($synset, $lang) {
            $query->where('synset', $synset)
                ->whereHas('wordForm', function ($query) use ($lang) {
                    $query->where('lang', $lang);
                });
        })->get();
    }
}

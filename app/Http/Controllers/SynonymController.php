<?php

namespace App\Http\Controllers;

use App\Services\SynonymService;
use Igo\Tagger;

class SynonymController extends Controller
{
    protected $synonymService;

    public function __construct(SynonymService $synonymService)
    {
        $this->synonymService = $synonymService;
    }

    public function search($inputWord)
    {
        $igo = new Tagger();
        $words = $igo->wakati($inputWord);
//        dd($igo->parse($inputWord));
//        dd($words);

        $synonyms = [];
        foreach ($words as $word) {
            $synonyms = array_merge($synonyms, $this->getSynonyms($word));

        }
//        dd($synonyms);
//        return $synonyms;
        // ビューで表示
        return view('synonyms.show', [
            'word' => $word,
            'synonyms' => $synonyms
        ]);
    }

    /**
     * @param $word
     * @return array
     */
    public function getSynonyms($word): array
    {
        $synonyms = [];
        $words = $this->synonymService->getWords($word);
        if (!empty($words)) {
            foreach ($words as $w) {
                $s = $this->synonymService->getSynonyms($w->wordid);
                $synonyms = array_merge($synonyms, $s);
            }
        }
        return $synonyms;
    }
}


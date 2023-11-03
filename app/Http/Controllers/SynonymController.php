<?php

namespace App\Http\Controllers;

use App\Services\SynonymService;

class SynonymController extends Controller
{
    protected $synonymService;

    public function __construct(SynonymService $synonymService)
    {
        $this->synonymService = $synonymService;
    }

    public function search($word)
    {
        $synonyms = [];
        $words = $this->synonymService->getWords($word);
        if (!empty($words)) {
            foreach ($words as $w) {
                $s = $this->synonymService->getSynonyms($w->wordid);
                $synonyms = array_merge($synonyms, $s);
            }
        }
//        dd($synonyms);
        return $synonyms;
    }
}


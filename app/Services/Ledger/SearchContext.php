<?php

namespace App\Services\Ledger;

use App\Services\SynonymService;
use Illuminate\Support\Str;

class SearchContext
{
    public $keywords = [];
    public $synonyms = [];
    public $highlights = [];
    public $filter = [];
    public $tags = [];
    private $search;
    private SynonymService $synonymService;

    public function __construct($search = '', $filter = [])
    {
        $this->setSearch($search);
        $this->setFilter($filter);
        $this->synonymService = new SynonymService();
    }

    public function setSearch($search)
    {
        $this->search = $search;
        $this->updateContext();
    }

    private function updateContext()
    {
        [$this->keywords, $this->tags] = $this->extractKeywordsAndTags($this->search);
        $this->updateSynonymsAndHighlights();
    }

    private function extractKeywordsAndTags($search)
    {

        $text = mb_convert_kana($search, 'asKV', 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $words = explode(' ', $text);
        $words = array_filter($words, 'strlen');

        if (empty($words)) {
            return [[], []];
        }

        $tags = [];
        $keywords = [];

        if (empty($this->synonymService)) {

            $this->synonymService = new SynonymService();
        }
        foreach ($words as $word) {
            if (Str::startsWith($word, '#')) {
                $tags[] = substr($word, 1);
            } else {
                //                dd($word);
                $keywords = array_merge($keywords, $this->synonymService->wakati($word));
            }
        }

        return [$keywords, $tags];

    }

    private function updateSynonymsAndHighlights()
    {
        $this->synonyms = $this->findSynonyms($this->keywords);
        $this->highlights = $this->generateHighlights($this->keywords, $this->synonyms, $this->filter);
    }

    private function findSynonyms($keywords)
    {
        $synonyms = [];

        foreach ($keywords as $keyword) {
            $result = $this->synonymService->getSynonymsFromWord($keyword);
            $flattenResult = [];
            if (!empty($result)) {
                foreach ($result as $idWord => $synonym) {
                    $flattenResult[] = $idWord;
                    $flattenResult = array_merge($flattenResult, $synonym);
                }
            }
            $synonyms[$keyword] = array_merge($synonyms[$keyword] ?? [], $flattenResult);
        }

        return $synonyms;
    }

    private function generateHighlights($keywords, $synonyms, $filter)
    {
        $highlights = array_merge($keywords, $this->flattenSynonyms($synonyms), $filter);
        if (empty($highlights)) {
            return [];
        }

        return array_unique($highlights);
    }

    public function flattenSynonyms($synonyms)
    {
        $result = [];
        foreach ($synonyms as $key => $synonym) {
            if (is_array($synonym)) {
                $result = array_merge($result, $synonyms[$key]);
            } else {
                $result[] = $synonym;
            }
        }
        $result = array_unique($result);

        return $result;
    }

    public function setFilter(array $filter)
    {
        $this->filter = $filter;
    }

    public function setKeywords(array $keywords)
    {
        $this->keywords = $keywords;
        $this->updateSynonymsAndHighlights();
    }

    public function setHighlights(array $highlights)
    {
        $this->highlights = $highlights;
    }

    /**
     * SQLクエリ用の出力
     *
     * @return string
     */
    public function __toString()
    {
        if (empty($this->keywords)) {
            return '';
        }
        $result = $this->generateHighlights($this->keywords, $this->flattenSynonyms($this->synonyms), $this->filter);

        return implode(' ', $result);

    }
}

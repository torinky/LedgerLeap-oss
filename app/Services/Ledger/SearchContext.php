<?php

namespace App\Services\Ledger;

use App\Services\SynonymService;
use Illuminate\Support\Str;

class SearchContext
{
    /**
     * 検索文字列
     *
     * @var string
     */
    private $search;

    /**
     * 検索キーワード
     *
     * @var array
     */
    public $keywords = [];

    /**
     * 検索キーワードの類義語
     *
     * @var array
     */
    public $synonyms = [];

    /**
     * ハイライト用の語句
     *
     * @var array
     */
    public $highlights = [];

    /**
     * フィルター条件
     *
     * @var array
     */
    public $filter = [];

    /**
     * タグ
     *
     * @var array
     */
    public $tags = [];

    private SynonymService $synonymService;

    /**
     * コンストラクタ
     *
     * @param  string  $search  検索文字列
     * @param  array  $filter  フィルター条件
     */
    public function __construct(SynonymService $synonymService)
    {
        $this->synonymService = $synonymService;
    }

    /**
     * 検索文字列を設定する
     *
     * @param  string  $search  検索文字列
     */
    public function setSearch($search)
    {
        $this->search = $search;
        $this->updateContext();
    }

    /**
     * 検索キーワードを設定する
     *
     * @param  array  $keywords  検索キーワード
     */
    public function setKeywords(array $keywords)
    {
        $this->keywords = $keywords;
        $this->updateSynonymsAndHighlights();
    }

    /**
     * ハイライト用の語句を設定する
     *
     * @param  array  $highlights  ハイライト用の語句
     */
    public function setHighlights(array $highlights)
    {
        $this->highlights = $highlights;
    }

    /**
     * フィルター条件を設定する
     *
     * @param  array  $filter  フィルター条件
     */
    public function setFilter(array $filter)
    {
        $this->filter = $filter;
    }

    /**
     * 検索文字列から検索キーワード、タグ、類義語、ハイライト用の語句を更新する
     */
    private function updateContext()
    {
        [$this->keywords, $this->tags] = $this->extractKeywordsAndTags($this->search);
        $this->updateSynonymsAndHighlights();
    }

    /**
     * 類義語とハイライト用の語句を更新する
     */
    private function updateSynonymsAndHighlights()
    {
        $this->synonyms = $this->findSynonyms($this->keywords);
        $this->highlights = $this->generateHighlights($this->keywords, $this->synonyms, $this->filter);
    }

    /**
     * 検索文字列から検索キーワードとタグを抽出する
     *
     * @param  string  $search  検索文字列
     * @return array 検索キーワードとタグの配列
     */
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
        $keywords = $words;

        collect($words)->each(function ($word) use ($tags, $keywords) {
            if (Str::startsWith($word, '#')) {
                $tags[] = substr($word, 1);
            } else {
                $keywords = array_merge($keywords, SynonymService::wakati($word));
            }
        });

        return [$keywords, $tags];
    }

    /**
     * 検索キーワードから類義語を取得する
     *
     * @param  array  $keywords  検索キーワード
     * @return array 類義語の配列
     */
    private function findSynonyms($keywords)
    {
        $synonyms = [];

        foreach ($keywords as $keyword) {
            $synonyms[$keyword] = $this->synonymService->getSynonymsFromWord($keyword);
        }

        return $synonyms;
    }

    /**
     * 検索キーワード、類義語、フィルター条件からハイライト用の語句を生成する
     *
     * @param  array  $keywords  検索キーワード
     * @param  array  $synonyms  類義語
     * @param  array  $filter  フィルター条件
     * @return array ハイライト用の語句の配列
     */
    private function generateHighlights($keywords, $synonyms, $filter)
    {
        $highlights = array_merge($keywords, $this->flattenSynonyms($synonyms), $filter);
        if (empty($highlights)) {
            return [];
        }

        return array_unique($highlights);
    }

    /**
     * 類義語の配列を平坦化する
     *
     * @param  array  $synonyms  類義語の配列
     * @return array 平坦化された類義語の配列
     */
    public function flattenSynonyms($synonyms)
    {
        $result = [];
        foreach ($synonyms as $key => $synonym) {
            $result = $this->getArr($synonym, $result, $synonyms[$key]);
        }
        $result = array_unique($result);

        return $result;
    }

    /**
     * SQLクエリ用の出力を生成する
     *
     * @return string SQLクエリ用の出力
     */
    public function __toString()
    {
        if (empty($this->keywords)) {
            return '';
        }
        $result = $this->generateHighlights($this->keywords, $this->synonyms, $this->filter);

        return implode(' ', $result);
    }

    /**
     * 配列または値を結合または追加する
     *
     * @param  mixed  $synonym  結合または追加する値
     * @param  array  $result  結合または追加する配列
     * @param  array  $synonyms  $synonymが配列の場合に結合する配列
     * @return array 結合または追加された配列
     */
    public function getArr(mixed $synonym, mixed $result, $synonyms): mixed
    {
        if (is_array($synonym)) {
            $result = array_merge($result, $synonyms);
        } else {
            $result[] = $synonym;
        }

        return $result;
    }

    /**
     * キーワードに対応する平坦化された類義語の配列を取得する
     */
    public function getFlattenedSynonymsForKeyword(string $keyword): array
    {
        $synonyms = $this->synonyms[$keyword] ?? [];

        return array_merge([$keyword], $this->flattenSynonyms($synonyms));
    }
}

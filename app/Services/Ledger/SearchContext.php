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
     * kind 付きの検索候補
     *
     * @var array<int, array{term:string, kind:string}>
     */
    public array $selectedTerms = [];

    /**
     * 検索 trace
     *
     * @var array<string, mixed>
     */
    public array $trace = [];

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

    /**
     * 正規化済みの検索文字列
     */
    private string $normalizedSearch = '';

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
        $this->trace = $this->buildTrace();
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
        $this->normalizedSearch = $this->normalizeSearch($this->search);
        [$this->keywords, $this->tags] = $this->extractKeywordsAndTags($this->search);
        $this->updateSynonymsAndHighlights();
        $this->trace = $this->buildTrace();
    }

    /**
     * 類義語とハイライト用の語句を更新する
     */
    private function updateSynonymsAndHighlights()
    {
        $this->synonyms = $this->findSynonyms($this->keywords);
        $this->selectedTerms = $this->findSelectedTerms($this->keywords);
        $this->highlights = $this->generateHighlights($this->keywords, $this->synonyms, $this->filter);
    }

    /**
     * 検索文字列を正規化する
     */
    private function normalizeSearch(string $search): string
    {
        $text = mb_convert_kana($search, 'asKV', 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }

    /**
     * 検索文字列から検索キーワードとタグを抽出する
     *
     * @param  string  $search  検索文字列
     * @return array 検索キーワードとタグの配列
     */
    private function extractKeywordsAndTags($search)
    {
        $text = $this->normalizeSearch((string) $search);
        $words = array_values(array_filter(explode(' ', $text), 'strlen'));

        if (empty($words)) {
            return [[], []];
        }

        $tags = [];
        $keywords = [];

        foreach ($words as $word) {
            if (Str::startsWith($word, '#')) {
                $tags[] = substr($word, 1);
            } else {
                $keywords[] = $word;
                $keywords = array_merge($keywords, SynonymService::wakati($word));
            }
        }

        $keywords = array_values(array_unique(array_filter(array_map(
            static fn ($keyword) => trim((string) $keyword),
            $keywords
        ), static fn (string $keyword) => $keyword !== '')));
        $tags = array_values(array_unique(array_filter(array_map(
            static fn ($tag) => trim((string) $tag),
            $tags
        ), static fn (string $tag) => $tag !== '')));

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
     * 検索候補を kind 付きで取得する
     *
     * @return array<int, array{term:string, kind:string}>
     */
    private function findSelectedTerms(array $keywords): array
    {
        $selectedTerms = [];

        foreach ($keywords as $keyword) {
            $terms = $this->synonymService->getSearchTermsFromWord($keyword);

            foreach ($terms as $term) {
                $value = trim((string) ($term['term'] ?? ''));
                if ($value === '') {
                    continue;
                }

                if (! isset($selectedTerms[$value])) {
                    $selectedTerms[$value] = [
                        'term' => $value,
                        'kind' => (string) ($term['kind'] ?? 'synonym'),
                    ];
                }
            }
        }

        return array_values($selectedTerms);
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
        if (empty($this->selectedTerms)) {
            return '';
        }
        $result = array_merge(
            array_map(static fn (array $term) => $term['term'], $this->selectedTerms),
            $this->filter
        );
        $result = array_values(array_unique(array_filter(array_map(
            static fn ($term) => trim((string) $term),
            $result
        ), static fn (string $term) => $term !== '')));

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

    /**
     * 検索 trace を返す
     *
     * @return array<string, mixed>
     */
    public function getTrace(): array
    {
        if ($this->trace !== []) {
            return $this->trace;
        }

        return $this->buildTrace();
    }

    /**
     * 検索 trace を組み立てる
     *
     * @return array<string, mixed>
     */
    private function buildTrace(): array
    {
        return [
            'original_q' => $this->search ?? '',
            'normalized_q' => $this->normalizedSearch,
            'keywords' => $this->keywords,
            'tags' => $this->tags,
            'selected_terms' => $this->selectedTerms,
            'excluded_terms' => [],
        ];
    }
}

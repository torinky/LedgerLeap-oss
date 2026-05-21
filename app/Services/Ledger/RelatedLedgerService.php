<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\AutoNumberPatternService;
use App\Services\Config\SynonymServiceConfig;
use App\Services\RagSearchService;
use App\Services\SynonymService;
use Illuminate\Support\Collection;

class RelatedLedgerService
{
    public function __construct(
        private AutoNumberPatternService $autoNumberPatternService,
        private WritableFolderRepository $writableFolderRepository,
        private RagSearchService $ragSearchService,
    ) {}

    /**
     * @return array<string, array{source: string, column: string}>
     */
    public function extractAutoNumberValues(Ledger $ledger): array
    {
        $ledger->loadMissing('define');
        $define = $ledger->define;
        if (! $define) {
            return [];
        }

        $result = [];

        foreach ($define->column_define as $column) {
            if ($column->type !== 'auto_number') {
                continue;
            }

            $value = $ledger->content[$column->id] ?? null;
            if (! empty($value)) {
                $result[(string) $value] = [
                    'source' => 'auto_number',
                    'column' => $column->name,
                ];
            }
        }

        $textColumnTypes = ['text', 'textarea', 'memo'];
        $patterns = $this->autoNumberPatternService->getPatterns();

        if ($patterns->isEmpty()) {
            return $result;
        }

        foreach ($define->column_define as $column) {
            if (! in_array($column->type, $textColumnTypes, true)) {
                continue;
            }

            $text = $ledger->content[$column->id] ?? null;
            if (empty($text) || ! is_string($text)) {
                continue;
            }

            foreach ($patterns as $entry) {
                if (! preg_match_all($entry['pattern'], $text, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $matched) {
                    $matched = trim($matched);
                    if ($matched === '' || isset($result[$matched])) {
                        continue;
                    }

                    $result[$matched] = [
                        'source' => 'text_column',
                        'column' => $column->name,
                    ];
                }
            }
        }

        return $result;
    }

    public function buildSemanticQuery(Ledger $ledger): string
    {
        $ledger->loadMissing('define');
        $define = $ledger->define;
        if (! $define) {
            return '';
        }

        $parts = [];
        foreach ($define->column_define as $column) {
            if ($column->type === 'files') {
                continue;
            }

            $value = $ledger->content[$column->id] ?? null;
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', array_filter($value));
            }

            $text = strip_tags((string) $value);
            if ($text !== '') {
                $parts[] = mb_substr(trim($text), 0, 200);
            }
        }

        return mb_substr(implode(' ', $parts), 0, 500);
    }

    /**
     * @param  array<string, array{source: string, column: string}>  $identifierKeys
     * @return Collection<int, array{ledger: Ledger, matched_keys: array<int, array<string, string>>}>
     */
    public function searchByIdentifiers(array $identifierKeys, ?User $user, int $sourceLedgerId): Collection
    {
        if (empty($identifierKeys) || ! $user) {
            return collect();
        }

        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);
        if (empty($readableFolderIds)) {
            return collect();
        }

        $allowedDefineIds = LedgerDefine::whereIn('folder_id', $readableFolderIds)
            ->pluck('id')
            ->toArray();

        if (empty($allowedDefineIds)) {
            return collect();
        }

        $keyToIds = [];
        foreach ($identifierKeys as $key => $sourceInfo) {
            $synonymServiceConfig = new SynonymServiceConfig(['useSynonym' => false, 'useTechnicalTerm' => false]);
            $synonymService = new SynonymService($synonymServiceConfig);
            $searchContext = new SearchContext($synonymService);
            $searchContext->setSearch($key);

            $ids = Ledger::whereIn('ledger_define_id', $allowedDefineIds)
                ->searchContext($searchContext)
                ->where('id', '!=', $sourceLedgerId)
                ->pluck('id')
                ->toArray();

            foreach ($ids as $id) {
                $keyToIds[$key][] = $id;
            }
        }

        $uniqueIds = collect($keyToIds)->flatten()->unique()->values()->toArray();
        if (empty($uniqueIds)) {
            return collect();
        }

        $ledgers = Ledger::whereIn('id', $uniqueIds)
            ->with(['define', 'define.folder'])
            ->get()
            ->keyBy('id');

        $idToKeys = [];
        foreach ($keyToIds as $key => $ids) {
            $sourceInfo = $identifierKeys[$key];
            foreach ($ids as $id) {
                $idToKeys[$id][] = [
                    'value' => $key,
                    'source' => $sourceInfo['source'],
                    'column' => $sourceInfo['column'],
                ];
            }
        }

        return collect($uniqueIds)->map(function (int $id) use ($ledgers, $idToKeys) {
            $ledger = $ledgers->get($id);
            if (! $ledger) {
                return null;
            }

            return [
                'ledger' => $ledger,
                'matched_keys' => collect($idToKeys[$id] ?? [])->unique('value')->values()->all(),
            ];
        })->filter()->values();
    }

    /**
     * @return array{results: Collection<int, array{ledger: Ledger, score: float}>, rag_available: bool, error: string}
     */
    public function searchBySemantic(Ledger $ledger, ?User $user, int $sourceLedgerId, int $limit = 20): array
    {
        if (! config('rag.enabled', false)) {
            return [
                'results' => collect(),
                'rag_available' => false,
                'error' => '',
            ];
        }

        if (! $user) {
            return [
                'results' => collect(),
                'rag_available' => false,
                'error' => '',
            ];
        }

        $query = $this->buildSemanticQuery($ledger);
        if ($query === '') {
            return [
                'results' => collect(),
                'rag_available' => false,
                'error' => '',
            ];
        }

        try {
            $ragResults = $this->ragSearchService->searchLedgers(
                query: $query,
                limit: $limit,
                filters: ['user' => $user],
                embeddingType: 'passage'
            );

            if (empty($ragResults)) {
                return [
                    'results' => collect(),
                    'rag_available' => true,
                    'error' => '',
                ];
            }

            $scoreMap = [];
            foreach ($ragResults as $result) {
                $id = (int) $result['ledger_id'];
                if ($id === $sourceLedgerId) {
                    continue;
                }
                $scoreMap[$id] = $result['max_score'];
            }

            if (empty($scoreMap)) {
                return [
                    'results' => collect(),
                    'rag_available' => true,
                    'error' => '',
                ];
            }

            $ledgerIds = array_keys($scoreMap);
            $ledgers = Ledger::whereIn('id', $ledgerIds)
                ->with(['define', 'define.folder'])
                ->get()
                ->keyBy('id');

            $results = collect($ledgerIds)->map(function (int $id) use ($ledgers, $scoreMap) {
                $relatedLedger = $ledgers->get($id);
                if (! $relatedLedger) {
                    return null;
                }

                return [
                    'ledger' => $relatedLedger,
                    'score' => $scoreMap[$id],
                ];
            })->filter()->values();

            return [
                'results' => $results,
                'rag_available' => true,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'results' => collect(),
                'rag_available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  Collection<int, array{ledger: Ledger, matched_keys: array<int, array<string, string>>}>  $identifiers
     * @param  Collection<int, array{ledger: Ledger, score: float}>  $semantics
     * @return array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>
     */
    public function mergeResults(Collection $identifiers, Collection $semantics): array
    {
        $merged = [];

        foreach ($identifiers as $item) {
            $ledger = $item['ledger'];
            $merged[$ledger->id] = [
                'ledger' => $ledger,
                'reason' => 'identifier',
                'score' => null,
                'matched_keys' => $item['matched_keys'],
            ];
        }

        foreach ($semantics as $item) {
            $ledger = $item['ledger'];
            $score = $item['score'];

            if (isset($merged[$ledger->id])) {
                $merged[$ledger->id]['reason'] = 'both';
                $merged[$ledger->id]['score'] = $score;

                continue;
            }

            $merged[$ledger->id] = [
                'ledger' => $ledger,
                'reason' => 'semantic',
                'score' => $score,
                'matched_keys' => [],
            ];
        }

        $result = array_values($merged);
        usort($result, function (array $a, array $b) {
            $scoreA = $a['score'] ?? -1.0;
            $scoreB = $b['score'] ?? -1.0;

            return $scoreB <=> $scoreA;
        });

        return $result;
    }

    /**
     * @return array{
     *     identifier_keys: array<string, array{source: string, column: string}>,
     *     identifier_results: Collection<int, array{ledger: Ledger, matched_keys: array<int, array<string, string>>}>,
     *     semantic_results: Collection<int, array{ledger: Ledger, score: float}>,
     *     merged: array<int, array{ledger: Ledger, reason: string, score: float|null, matched_keys: array<int, array<string, string>>}>,
     *     identifier_count: int,
     *     semantic_count: int,
     *     total_count: int,
     *     returned_count: int,
     *     has_auto_number: bool,
     *     rag_available: bool,
     *     last_error: string
     * }
     */
    public function resolve(
        Ledger $ledger,
        ?User $user,
        bool $includeIdentifier = true,
        bool $includeSemantic = true,
        ?int $limit = null,
    ): array {
        $identifierKeys = $includeIdentifier ? $this->extractAutoNumberValues($ledger) : [];
        $identifierResults = $includeIdentifier
            ? $this->searchByIdentifiers($identifierKeys, $user, $ledger->id)
            : collect();

        $semanticSearch = $includeSemantic
            ? $this->searchBySemantic($ledger, $user, $ledger->id, $limit ?? 20)
            : ['results' => collect(), 'rag_available' => config('rag.enabled', false), 'error' => ''];

        $merged = $this->mergeResults($identifierResults, $semanticSearch['results']);
        $totalCount = count($merged);
        $returned = $limit ? array_slice($merged, 0, $limit) : $merged;

        return [
            'identifier_keys' => $identifierKeys,
            'identifier_results' => $identifierResults,
            'semantic_results' => $semanticSearch['results'],
            'merged' => $returned,
            'identifier_count' => $identifierResults->count(),
            'semantic_count' => $semanticSearch['results']->count(),
            'total_count' => $totalCount,
            'returned_count' => count($returned),
            'has_auto_number' => ! empty($identifierKeys),
            'rag_available' => $semanticSearch['rag_available'],
            'last_error' => $semanticSearch['error'],
        ];
    }
}

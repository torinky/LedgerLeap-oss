<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RagSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService,
        private WritableFolderRepository $writableFolderRepository
    ) {}

    /**
     * Search ledgers with RAG (for API and general use)
     *
     * @param  string  $query  Search query text
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Additional filters (folder_id, ledger_define_id, ledger_ids, user)
     * @return array Array of ledger scores
     */
    public function search(
        string $query,
        User $user,
        array $ledgerDefineIds = [],
        array $filters = [],
        int $perPage = 100
    ): LengthAwarePaginator {
        // 1. Get readable folder IDs for permission filtering
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);

        if (empty($readableFolderIds)) {
            return new PaginatorImpl([], 0, $perPage);
        }

        // 2. Build filter array
        $searchFilters = array_merge($filters, [
            'user' => $user,
            'readable_folder_ids' => $readableFolderIds,
        ]);

        if (! empty($ledgerDefineIds)) {
            $searchFilters['ledger_define_ids'] = $ledgerDefineIds;
        }

        // 3. Perform search (get more than perPage for accurate pagination)
        $searchResults = $this->searchLedgers($query, $perPage * 10, $searchFilters);

        if (empty($searchResults)) {
            return new PaginatorImpl([], 0, $perPage);
        }

        // 4. Load ledger models
        $ledgerIds = array_column($searchResults, 'ledger_id');
        $ledgers = Ledger::whereIn('id', $ledgerIds)
            ->with(['define', 'creator', 'modifier'])
            ->get()
            ->keyBy('id');

        // 5. Sort ledgers according to search results order AND attach scores
        $scoreMap = collect($searchResults)->pluck('max_score', 'ledger_id');
        $sortedLedgers = collect($searchResults)->map(function ($result) use ($ledgers, $scoreMap) {
            $ledger = $ledgers->get($result['ledger_id']);
            if ($ledger) {
                // Attach semantic score and related metadata as dynamic attributes
                $ledger->semantic_score = $result['max_score'];
                $ledger->best_chunk_text = $result['best_chunk_text'] ?? null;
                $ledger->chunk_count = $result['chunk_count'] ?? 1;
            }
            return $ledger;
        })->filter();

        // 6. Paginate
        $currentPage = Paginator::resolveCurrentPage();
        $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new PaginatorImpl(
            $currentPageItems,
            $sortedLedgers->count(),
            $perPage,
            $currentPage
        );
    }

    /**
     * Search ledgers with RAG (basic search without pagination)
     *
     * @param  string  $query  Search query text
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Additional filters (folder_id, ledger_define_id, ledger_ids, user)
     * @return array Array of ledger scores
     */
    public function searchLedgers(string $query, int $limit = 20, array $filters = []): array
    {
        $logChannel = config('rag.log_channel', 'stack');
        Log::channel($logChannel)->info('RAG search initiated', [
            'query' => $query,
            'limit' => $limit,
            'filters' => $filters,
        ]);

        // 1. Generate embedding for the query
        $queryEmbedding = $this->embeddingService->embed($query, 'query');

        // 2. Apply permission-based filtering if user is provided
        if (isset($filters['user'])) {
            $filters = $this->applyPermissionFilters($filters);
            unset($filters['user']); // Remove user object from filters before passing to Mroonga
        }

        // 3. Perform search using Mroonga native functions
        $chunkScores = $this->searchWithMroonga($queryEmbedding, $filters, $query);

        // 4. Aggregate scores by ledger (using max score strategy)
        $ledgerScores = [];
        foreach ($chunkScores as $chunkScore) {
            $ledgerId = $chunkScore['ledger_id'];
            // Mroongaのスコアはコサイン「距離」なので、1から引いて「類似度」に変換
            $similarity = 1 - $chunkScore['score'];

            if (! isset($ledgerScores[$ledgerId])) {
                $ledgerScores[$ledgerId] = [
                    'ledger_id' => $ledgerId,
                    'max_score' => $similarity,
                    'best_chunk_text' => $chunkScore['chunk_text'],
                    'chunk_count' => 1,
                ];
            } else {
                if ($similarity > $ledgerScores[$ledgerId]['max_score']) {
                    $ledgerScores[$ledgerId]['max_score'] = $similarity;
                    $ledgerScores[$ledgerId]['best_chunk_text'] = $chunkScore['chunk_text'];
                }
                $ledgerScores[$ledgerId]['chunk_count']++;
            }
        }

        // 5. Sort by score descending and limit results
        usort($ledgerScores, fn ($a, $b) => $b['max_score'] <=> $a['max_score']);
        $results = array_slice($ledgerScores, 0, $limit);

        Log::channel($logChannel)->info('RAG search completed', [
            'results_count' => count($results),
            'top_score' => $results[0]['max_score'] ?? 0,
        ]);

        return $results;
    }

    /**
     * Search ledgers for API responses (with full model data)
     *
     * @param  \App\Models\User  $user  User for permission filtering
     * @param  array  $params  Search parameters (query, limit, filters)
     * @return array Array of ledger models with metadata
     */
    public function searchForApi(User $user, array $params): array
    {
        $query = $params['query'] ?? '';
        $limit = $params['limit'] ?? 20;
        $filters = $params['filters'] ?? [];

        // Add user to filters for permission checking
        $filters['user'] = $user;

        // Perform search
        $searchResults = $this->searchLedgers($query, $limit, $filters);

        if (empty($searchResults)) {
            return [];
        }

        // Load ledger models with relationships
        $ledgerIds = array_column($searchResults, 'ledger_id');
        $ledgers = Ledger::whereIn('id', $ledgerIds)
            ->with(['define', 'creator', 'modifier'])
            ->get()
            ->keyBy('id');

        // Map results to include both ledger data and search metadata
        $results = [];
        foreach ($searchResults as $result) {
            $ledger = $ledgers->get($result['ledger_id']);
            if ($ledger) {
                $results[] = [
                    'ledger' => $ledger,
                    'similarity_score' => $result['max_score'],
                    'best_chunk_text' => $result['best_chunk_text'],
                    'chunk_count' => $result['chunk_count'],
                ];
            }
        }

        return $results;
    }

    private function searchWithMroonga(array $queryEmbedding, array $filters, string $keyword, int $chunkLimit = 100): array
    {
        $query_vector_str = '['.implode(',', $queryEmbedding).']';
        $distance_expression = "distance_cosine(embedding, {$query_vector_str})";

        // Step 1: Get vector search results from Mroonga (IDs and scores only)
        $groonga_filter_parts = [];
        $similarity_threshold = config('rag.search.similarity_threshold', 0.7);

/*        if (! empty($keyword)) {
            // キーワード検索とベクトル検索のハイブリッド
            // 1. キーワードにマッチするレコード
            $escaped_keyword = str_replace('"', '\"', $keyword);
            $keyword_filter = sprintf('chunk_text @~ "%s"', $escaped_keyword);
            // 2. ベクトルが類似しているレコード
            $vector_filter = sprintf('score < %f', $similarity_threshold);
            // 上記のOR条件
            $groonga_filter_parts[] = "({$keyword_filter} || {$vector_filter})";
        } else {
            // キーワードがない場合はベクトル検索のみ
            $groonga_filter_parts[] = sprintf('score < %f', $similarity_threshold);
        }*/

        $groonga_filter_parts[] = sprintf('score < %f', $similarity_threshold);

        $groonga_filter = implode(' && ', $groonga_filter_parts);
        $filter_clause = ! empty($groonga_filter) ? "--filter '{$groonga_filter}'" : '';

        // The stage is changed to 'initial' to allow filtering by the calculated score.
        // Added --sortby score to sort by distance in ascending order.
        $mroonga_command = sprintf(
            "select ledger_chunks %s --columns[score].stage initial --columns[score].flags COLUMN_SCALAR --columns[score].types Float32 --columns[score].value '%s' --output_columns _id,score --sortby score --limit %d",
            $filter_clause,
            $distance_expression,
            $chunkLimit
        );

        Log::channel(config('rag.log_channel', 'stack'))->debug('Executing Mroonga Search', [
            'mroonga_command' => $mroonga_command,
        ]);

        try {
            // Execute Mroonga command to get chunk IDs and scores
            $result = DB::select('SELECT mroonga_command(?) AS res', [$mroonga_command]);
            if (empty($result)) {
                return [];
            }

            $groonga_response = json_decode($result[0]->res, true);
            $parsed = $this->parseGroongaResponse($groonga_response);

            if (empty($parsed)) {
                return [];
            }

            // Extract chunk IDs and their scores
            $chunkIds = array_column($parsed, '_id');
            $scoreMap = [];
            foreach ($parsed as $row) {
                $scoreMap[$row['_id']] = $row['score'];
            }

            if (empty($chunkIds)) {
                return [];
            }

            // Step 2: Apply SQL filters on the actual ledger_chunks table
            $sql = 'SELECT id, ledger_id, chunk_text FROM ledger_chunks WHERE id IN ('.implode(',', array_map('intval', $chunkIds)).')';

            $sqlFilters = [];
            $bindings = [];

            if (isset($filters['folder_id'])) {
                $sqlFilters[] = 'folder_id = ?';
                $bindings[] = (int) $filters['folder_id'];
            }

            if (isset($filters['readable_folder_ids']) && ! empty($filters['readable_folder_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['readable_folder_ids']), '?'));
                $sqlFilters[] = "folder_id IN ({$placeholders})";
                $bindings = array_merge($bindings, array_map('intval', $filters['readable_folder_ids']));
            }

            if (isset($filters['ledger_define_id'])) {
                $sqlFilters[] = 'ledger_define_id = ?';
                $bindings[] = (int) $filters['ledger_define_id'];
            }

            if (isset($filters['ledger_define_ids']) && ! empty($filters['ledger_define_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['ledger_define_ids']), '?'));
                $sqlFilters[] = "ledger_define_id IN ({$placeholders})";
                $bindings = array_merge($bindings, array_map('intval', $filters['ledger_define_ids']));
            }

            if (isset($filters['ledger_ids']) && ! empty($filters['ledger_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['ledger_ids']), '?'));
                $sqlFilters[] = "ledger_id IN ({$placeholders})";
                $bindings = array_merge($bindings, array_map('intval', $filters['ledger_ids']));
            }

            if (! empty($sqlFilters)) {
                $sql .= ' AND '.implode(' AND ', $sqlFilters);
            }

            $chunks = DB::select($sql, $bindings);

            // Combine with scores
            $results = [];
            foreach ($chunks as $chunk) {
                $results[] = [
                    'ledger_id' => $chunk->ledger_id,
                    'chunk_text' => $chunk->chunk_text,
                    'score' => $scoreMap[$chunk->id] ?? 1.0,
                ];
            }

            Log::channel(config('rag.log_channel', 'stack'))->debug('Mroonga search completed', [
                'total_chunks' => count($parsed),
                'filtered_chunks' => count($results),
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::channel(config('rag.log_channel', 'stack'))->error('Mroonga search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    private function parseGroongaResponse(?array $response): array
    {
        if (is_null($response) || ! isset($response[0][0][0]) || $response[0][0][0] === 0) {
            return [];
        }

        $columns = array_map(fn ($col) => $col[0], $response[0][1]);
        $rows = array_slice($response[0], 2);

        $results = [];
        foreach ($rows as $row) {
            if (count($columns) === count($row)) {
                $results[] = array_combine($columns, $row);
            }
        }

        return $results;
    }

    /**
     * Apply permission-based filtering to search filters
     */
    private function applyPermissionFilters(array $filters): array
    {
        $user = $filters['user'];

        // Get readable folder IDs
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user);

        if (empty($readableFolderIds)) {
            // User has no readable folders, return empty filter
            $filters['ledger_ids'] = [-1]; // Impossible ID to match nothing

            return $filters;
        }

        // Add readable folders to filter
        $filters['readable_folder_ids'] = $readableFolderIds;

        return $filters;
    }

    public function searchLedgersWithModels(string $query, int $limit = 20, array $filters = [])
    {
        $searchResults = $this->searchLedgers($query, $limit, $filters);

        if (empty($searchResults)) {
            return collect([]);
        }

        $ledgerIds = array_column($searchResults, 'ledger_id');

        $ledgers = Ledger::with(['define', 'creator', 'modifier'])
            ->whereIn('id', $ledgerIds)
            ->get()
            ->keyBy('id');

        $results = collect($searchResults)->map(function ($result) use ($ledgers) {
            $ledger = $ledgers->get($result['ledger_id']);
            if ($ledger) {
                $ledger->setAttribute('similarity_score', $result['max_score']);
                $ledger->setAttribute('best_chunk_text', $result['best_chunk_text']);
                $ledger->setAttribute('chunk_count', $result['chunk_count']);
            }

            return $ledger;
        })->filter();

        return $results;
    }
}

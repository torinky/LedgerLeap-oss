<?php

namespace App\Services;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RagSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    public function searchLedgers(string $query, int $limit = 20, array $filters = []): array
    {
        $logChannel = config('rag.log_channel', 'stack');
        Log::channel($logChannel)->info('RAG search initiated', [
            'query' => $query,
            'limit' => $limit,
            'filters' => $filters,
        ]);

        // 1. Generate embedding for the query
        $queryEmbedding = $this->embeddingService->embed($query);

        // 2. Perform search using Mroonga native functions
        $chunkScores = $this->searchWithMroonga($queryEmbedding, $filters, $query);

        // 3. Aggregate scores by ledger (using max score strategy)
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

        // 4. Sort by score descending and limit results
        usort($ledgerScores, fn ($a, $b) => $b['max_score'] <=> $a['max_score']);
        $results = array_slice($ledgerScores, 0, $limit);

        Log::channel($logChannel)->info('RAG search completed', [
            'results_count' => count($results),
            'top_score' => $results[0]['max_score'] ?? 0,
        ]);

        return $results;
    }

    private function searchWithMroonga(array $queryEmbedding, array $filters, string $keyword, int $chunkLimit = 100): array
    {
        $query_vector_str = '['.implode(',', $queryEmbedding).']';
        $distance_expression = "distance_cosine(embedding, {$query_vector_str})";

        // Build filter conditions
        $filter_parts = [];
        if (! empty($keyword)) {
            // Use @ for full-text search, not @@, and escape quotes properly
            $escaped_keyword = str_replace('"', '\\"', $keyword);
            $filter_parts[] = sprintf('chunk_text @ "%s"', $escaped_keyword);
        }
        // 類似度が極端に低いもの（距離が遠いもの）を足切り
        $filter_parts[] = sprintf('%s < 0.7', $distance_expression);

        if (isset($filters['folder_id'])) {
            $filter_parts[] = 'folder_id == '.(int) $filters['folder_id'];
        }
        if (isset($filters['ledger_define_id'])) {
            $filter_parts[] = 'ledger_define_id == '.(int) $filters['ledger_define_id'];
        }
        if (isset($filters['ledger_ids'])) {
            $escaped_ids = implode(', ', array_map('intval', $filters['ledger_ids']));
            $filter_parts[] = "ledger_id IN ({$escaped_ids})";
        }

        $filter_condition = implode(' && ', $filter_parts);

        // Build the mroonga command - note: --columns must come AFTER --filter
        $mroonga_command = sprintf(
            "select ledger_chunks --filter '%s' --columns[score].stage filtered --columns[score].flags COLUMN_SCALAR --columns[score].types Float32 --columns[score].value '%s' --output_columns ledger_id,chunk_text,score --limit %d",
            $filter_condition,
            $distance_expression,
            $chunkLimit
        );

        Log::channel(config('rag.log_channel', 'stack'))->debug('Executing Mroonga Search', [
            'mroonga_command' => $mroonga_command,
        ]);

        try {
            // Use a bound parameter to let the driver handle escaping
            $result = DB::select('SELECT mroonga_command(?) AS res', [$mroonga_command]);
            if (empty($result)) {
                return [];
            }
            $groonga_response = json_decode($result[0]->res, true);

            return $this->parseGroongaResponse($groonga_response);
        } catch (\Exception $e) {
            Log::channel(config('rag.log_channel', 'stack'))->error('Mroonga search failed', [
                'error' => $e->getMessage(),
                'mroonga_command' => $mroonga_command,
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

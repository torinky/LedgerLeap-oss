<?php

namespace App\Services;

use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RAG (Retrieval-Augmented Generation) Search Service
 *
 * Provides semantic search functionality using vector embeddings stored in ledger_chunks.
 */
class RagSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Search ledgers by semantic similarity to the query text.
     *
     * @param  string  $query  The search query text
     * @param  int  $limit  Maximum number of results to return
     * @param  array  $filters  Additional filters (folder_id, ledger_define_id, etc.)
     * @return array Array of ledger IDs with their similarity scores
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
        $queryEmbedding = $this->embeddingService->embed($query);

        // 2. Retrieve all chunks (with filters if applicable)
        $chunksQuery = DB::table('ledger_chunks')
            ->select('id', 'ledger_id', 'chunk_text', 'embedding', 'chunk_source');

        // Apply filters
        if (isset($filters['folder_id'])) {
            $chunksQuery->where('folder_id', $filters['folder_id']);
        }
        if (isset($filters['ledger_define_id'])) {
            $chunksQuery->where('ledger_define_id', $filters['ledger_define_id']);
        }
        if (isset($filters['ledger_ids'])) {
            $chunksQuery->whereIn('ledger_id', $filters['ledger_ids']);
        }

        $chunks = $chunksQuery->get();

        if ($chunks->isEmpty()) {
            Log::channel($logChannel)->info('No chunks found for search', ['filters' => $filters]);

            return [];
        }

        Log::channel($logChannel)->info('Retrieved chunks for similarity calculation', [
            'chunk_count' => $chunks->count(),
        ]);

        // 3. Calculate similarity scores for each chunk
        $chunkScores = [];
        foreach ($chunks as $chunk) {
            $chunkEmbedding = $this->deserializeEmbedding($chunk->embedding);
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            $chunkScores[] = [
                'chunk_id' => $chunk->id,
                'ledger_id' => $chunk->ledger_id,
                'chunk_text' => $chunk->chunk_text,
                'chunk_source' => $chunk->chunk_source,
                'similarity' => $similarity,
            ];
        }

        // 4. Aggregate scores by ledger (using max score strategy for Phase 1)
        $ledgerScores = [];
        foreach ($chunkScores as $chunkScore) {
            $ledgerId = $chunkScore['ledger_id'];

            if (! isset($ledgerScores[$ledgerId])) {
                $ledgerScores[$ledgerId] = [
                    'ledger_id' => $ledgerId,
                    'max_score' => $chunkScore['similarity'],
                    'best_chunk_text' => $chunkScore['chunk_text'],
                    'best_chunk_source' => $chunkScore['chunk_source'],
                    'chunk_count' => 1,
                ];
            } else {
                // Update max score if this chunk has higher similarity
                if ($chunkScore['similarity'] > $ledgerScores[$ledgerId]['max_score']) {
                    $ledgerScores[$ledgerId]['max_score'] = $chunkScore['similarity'];
                    $ledgerScores[$ledgerId]['best_chunk_text'] = $chunkScore['chunk_text'];
                    $ledgerScores[$ledgerId]['best_chunk_source'] = $chunkScore['chunk_source'];
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
     * Calculate cosine similarity between two embedding vectors.
     *
     * @param  array  $vec1  First embedding vector
     * @param  array  $vec2  Second embedding vector
     * @return float Cosine similarity score (0.0 to 1.0)
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Deserialize binary embedding data into float array.
     *
     * @param  string  $binaryData  Binary string from database
     * @return array Float array representing the embedding (0-indexed)
     */
    private function deserializeEmbedding(string $binaryData): array
    {
        // Unpack binary string to float array (using 'f' for single-precision float)
        // unpack() returns 1-indexed array, so we convert to 0-indexed
        return array_values(unpack('f*', $binaryData));
    }

    /**
     * Get similar ledgers with full model instances.
     *
     * @param  string  $query  The search query text
     * @param  int  $limit  Maximum number of results
     * @param  array  $filters  Additional filters
     * @return \Illuminate\Support\Collection Collection of Ledger models with similarity scores
     */
    public function searchLedgersWithModels(string $query, int $limit = 20, array $filters = [])
    {
        $searchResults = $this->searchLedgers($query, $limit, $filters);

        if (empty($searchResults)) {
            return collect([]);
        }

        $ledgerIds = array_column($searchResults, 'ledger_id');

        // Load ledgers with relationships
        $ledgers = Ledger::with(['define', 'creator', 'modifier'])
            ->whereIn('id', $ledgerIds)
            ->get()
            ->keyBy('id');

        // Attach similarity scores and maintain order
        $results = collect($searchResults)->map(function ($result) use ($ledgers) {
            $ledger = $ledgers->get($result['ledger_id']);
            if ($ledger) {
                // Add dynamic attributes to the model
                $ledger->setAttribute('similarity_score', $result['max_score']);
                $ledger->setAttribute('best_chunk_text', $result['best_chunk_text']);
                $ledger->setAttribute('best_chunk_source', $result['best_chunk_source']);
                $ledger->setAttribute('chunk_count', $result['chunk_count']);
            }

            return $ledger;
        })->filter(); // Remove nulls if ledger was deleted

        return $results;
    }
}

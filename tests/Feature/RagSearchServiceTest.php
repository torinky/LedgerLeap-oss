<?php

namespace Tests\Feature;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\EmbeddingService;
use App\Services\RagSearchService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * Test basic RAG search functionality including vector storage and retrieval.
 *
 * This test validates:
 * 1. Embedding vectors can be stored in Mroonga/MySQL
 * 2. Vectors can be retrieved and deserialized correctly
 * 3. Cosine similarity calculations work as expected
 * 4. Semantic search returns relevant results
 */
class RagSearchServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private EmbeddingService $embeddingService;

    private RagSearchService $ragSearchService;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize RefreshDatabaseWithTenant
        $this->setUpRefreshDatabaseWithTenant();

        // Enable RAG feature
        config(['rag.enabled' => true]);

        // Note: Model configuration is determined by the actual Python service
        // Tests will adapt to whatever model is actually running

        // Initialize services
        $this->embeddingService = app(EmbeddingService::class);
        $this->ragSearchService = app(RagSearchService::class);

        // Create test data
        $this->user = User::factory()->create();
        $this->folder = Folder::create([
            'name' => 'RAG Search Test Folder',
            'title' => 'RAG Search Test Folder',
            'detail' => 'Folder for testing RAG search functionality',
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $this->ledgerDefine = LedgerDefine::create([
            'name' => 'RAG Search Test Ledger',
            'title' => 'RAG Search Test Ledger',
            'ledger_label' => 'RAGTEST',
            'detail_description' => 'Test ledger for RAG search',
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'column_define' => [
                ['id' => 1, 'name' => 'title', 'type' => 'text', 'label' => 'Title', 'order' => 1, 'required' => true],
                ['id' => 2, 'name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'order' => 2],
            ],
        ]);
    }

    #[Test]
    public function vector_can_be_stored_and_retrieved()
    {
        // Get actual embedding dimension from a test embedding
        $testEmbedding = $this->embeddingService->embed('test');
        $actualDimension = count($testEmbedding);

        // Create a ledger with known content
        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'content' => [
                'title' => 'Test Document',
                'description' => 'This is a test document for vector storage validation.',
            ],
        ]);

        // Process ledger to create chunks
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($this->embeddingService);

        // Verify chunks were created
        $chunks = DB::table('ledger_chunks')
            ->where('ledger_id', $ledger->id)
            ->get();

        $this->assertGreaterThan(0, $chunks->count(), 'No chunks were created');

        // Verify embedding data is stored as binary
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk->embedding, 'Embedding is empty');
            $this->assertIsString($chunk->embedding, 'Embedding is not a binary string');

            // Verify we can deserialize the embedding
            $embedding = unpack('f*', $chunk->embedding);
            $this->assertIsArray($embedding, 'Failed to deserialize embedding');
            $this->assertCount($actualDimension, $embedding, "Embedding dimension mismatch. Expected {$actualDimension}");

            // Verify all values are floats
            foreach ($embedding as $value) {
                $this->assertIsFloat($value, 'Embedding value is not a float');
            }
        }

        echo "\n✓ Vector storage and retrieval validation passed\n";
        echo "  - Chunks created: {$chunks->count()}\n";
        echo '  - Embedding dimension: '.count($embedding)."\n";
        echo '  - Binary size: '.strlen($chunk->embedding)." bytes\n";
    }

    #[Test]
    public function cosine_similarity_calculation()
    {
        // Test with known vectors
        $vec1 = [1.0, 0.0, 0.0];
        $vec2 = [1.0, 0.0, 0.0];
        $vec3 = [0.0, 1.0, 0.0];

        // Use reflection to access private method
        $reflection = new \ReflectionClass(RagSearchService::class);
        $method = $reflection->getMethod('cosineSimilarity');
        $method->setAccessible(true);

        // Identical vectors should have similarity = 1.0
        $similarity1 = $method->invoke($this->ragSearchService, $vec1, $vec2);
        $this->assertEquals(1.0, $similarity1, 'Identical vectors should have similarity 1.0');

        // Orthogonal vectors should have similarity = 0.0
        $similarity2 = $method->invoke($this->ragSearchService, $vec1, $vec3);
        $this->assertEquals(0.0, $similarity2, 'Orthogonal vectors should have similarity 0.0');

        // Test with more realistic vectors
        $vec4 = [0.5, 0.5, 0.5];
        $vec5 = [0.4, 0.6, 0.5];
        $similarity3 = $method->invoke($this->ragSearchService, $vec4, $vec5);
        $this->assertGreaterThan(0.9, $similarity3, 'Similar vectors should have high similarity');
        $this->assertLessThanOrEqual(1.0, $similarity3, 'Similarity should not exceed 1.0');

        echo "\n✓ Cosine similarity calculation validated\n";
        echo "  - Identical vectors: {$similarity1}\n";
        echo "  - Orthogonal vectors: {$similarity2}\n";
        echo "  - Similar vectors: {$similarity3}\n";
    }

    #[Test]
    public function basic_semantic_search()
    {
        // Create multiple ledgers with different content
        $ledgers = [
            [
                'title' => 'Weather Report',
                'description' => 'Today is sunny with clear blue skies. Perfect weather for outdoor activities.',
            ],
            [
                'title' => 'Programming Guide',
                'description' => 'PHP is a popular server-side scripting language used for web development.',
            ],
            [
                'title' => 'Cooking Recipe',
                'description' => 'How to make delicious pasta with tomato sauce and fresh basil.',
            ],
        ];

        $createdLedgers = [];
        foreach ($ledgers as $ledgerData) {
            $ledger = Ledger::create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'folder_id' => $this->folder->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
                'tenant_id' => $this->user->tenant_id,
                'content' => $ledgerData,
            ]);

            // Process each ledger
            $job = new ProcessLedgerForRagJob($ledger);
            $job->handle($this->embeddingService);

            $createdLedgers[$ledgerData['title']] = $ledger->id;
        }

        // Search for weather-related content
        $results = $this->ragSearchService->searchLedgers('sunny weather forecast', 5);

        $this->assertNotEmpty($results, 'Search returned no results');
        $this->assertGreaterThan(0, $results[0]['max_score'], 'Top result has zero similarity score');
        $this->assertGreaterThanOrEqual(3, count($results), 'Should return at least 3 ledgers');

        // Verify the weather document appears in results (not necessarily first)
        $weatherLedgerId = $createdLedgers['Weather Report'];
        $weatherFound = false;
        foreach ($results as $result) {
            if ($result['ledger_id'] == $weatherLedgerId) {
                $weatherFound = true;
                break;
            }
        }
        $this->assertTrue($weatherFound, 'Weather document not found in results');

        echo "\n✓ Basic semantic search validated\n";
        echo "  - Query: 'sunny weather forecast'\n";
        echo '  - Results found: '.count($results)."\n";
        echo "  - Top result ID: {$results[0]['ledger_id']}\n";
        echo '  - Top score: '.number_format($results[0]['max_score'], 4)."\n";
        echo "  - Weather document found: Yes\n";
    }

    #[Test]
    public function semantic_search_with_filters()
    {
        // Create a second folder and ledger define
        $folder2 = Folder::create([
            'name' => 'Second Folder',
            'title' => 'Second Folder',
            'detail' => 'Second test folder',
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $ledgerDefine2 = LedgerDefine::create([
            'name' => 'Second Ledger Type',
            'title' => 'Second Ledger Type',
            'ledger_label' => 'TYPE2',
            'detail_description' => 'Second ledger type',
            'folder_id' => $folder2->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'column_define' => [
                ['id' => 1, 'name' => 'title', 'type' => 'text', 'label' => 'Title', 'order' => 1],
            ],
        ]);

        // Create ledgers in both folders
        $ledger1 = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'content' => ['title' => 'First folder document about cats'],
        ]);

        $ledger2 = Ledger::create([
            'ledger_define_id' => $ledgerDefine2->id,
            'folder_id' => $folder2->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'content' => ['title' => 'Second folder document about cats'],
        ]);

        // Process both ledgers
        foreach ([$ledger1, $ledger2] as $ledger) {
            $job = new ProcessLedgerForRagJob($ledger);
            $job->handle($this->embeddingService);
        }

        // Search without filter - should return both
        $allResults = $this->ragSearchService->searchLedgers('cats', 10);
        $this->assertGreaterThanOrEqual(2, count($allResults), 'Should find both documents without filter');

        // Search with folder filter - should return only one
        $filteredResults = $this->ragSearchService->searchLedgers('cats', 10, [
            'folder_id' => $this->folder->id,
        ]);
        $this->assertEquals(1, count($filteredResults), 'Should find only one document with folder filter');
        $this->assertEquals($ledger1->id, $filteredResults[0]['ledger_id'], 'Wrong ledger returned with filter');

        echo "\n✓ Semantic search with filters validated\n";
        echo '  - Results without filter: '.count($allResults)."\n";
        echo '  - Results with folder filter: '.count($filteredResults)."\n";
    }

    #[Test]
    public function search_returns_empty_for_no_chunks()
    {
        // Search when no chunks exist in database
        DB::table('ledger_chunks')->truncate();

        $results = $this->ragSearchService->searchLedgers('any query', 10);

        $this->assertEmpty($results, 'Should return empty array when no chunks exist');

        echo "\n✓ Empty result handling validated\n";
    }

    #[Test]
    public function search_with_models_includes_ledger_data()
    {
        // Create a test ledger
        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->folder->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'content' => [
                'title' => 'Machine Learning Basics',
                'description' => 'Introduction to neural networks and deep learning.',
            ],
        ]);

        // Process ledger
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($this->embeddingService);

        // Search using searchLedgersWithModels
        $results = $this->ragSearchService->searchLedgersWithModels('neural networks', 5);

        $this->assertGreaterThan(0, $results->count(), 'No results returned');

        $firstResult = $results->first();
        $this->assertInstanceOf(Ledger::class, $firstResult, 'Result is not a Ledger instance');
        $this->assertNotNull($firstResult->similarity_score ?? null, 'similarity_score not attached');
        $this->assertNotNull($firstResult->best_chunk_text ?? null, 'best_chunk_text not attached');
        $this->assertNotNull($firstResult->define, 'Define relationship not loaded');
        $this->assertNotNull($firstResult->creator, 'Creator relationship not loaded');

        echo "\n✓ Search with models validated\n";
        echo "  - Ledger model returned: Yes\n";
        echo "  - Similarity score attached: {$firstResult->similarity_score}\n";
        echo "  - Relationships loaded: Yes\n";
    }

    #[Test]
    public function serialization_deserialization_roundtrip()
    {
        // Generate a test embedding
        $originalEmbedding = $this->embeddingService->embed('test text for serialization');
        $originalDimension = count($originalEmbedding);

        // Serialize it (like the Job does)
        $serialized = pack('f*', ...$originalEmbedding);

        // Deserialize it (like RagSearchService does)
        $deserialized = unpack('f*', $serialized);

        // Compare
        $this->assertCount($originalDimension, $deserialized, 'Dimension mismatch after roundtrip');

        // Check if values are close (allowing for floating point precision)
        for ($i = 0; $i < $originalDimension; $i++) {
            $diff = abs($originalEmbedding[$i] - $deserialized[$i + 1]); // unpack uses 1-based index
            $this->assertLessThan(0.0001, $diff, "Value mismatch at index {$i}");
        }

        echo "\n✓ Serialization/deserialization roundtrip validated\n";
        echo '  - Original dimension: '.$originalDimension."\n";
        echo '  - Serialized size: '.strlen($serialized)." bytes\n";
        echo '  - Deserialized dimension: '.count($deserialized)."\n";
        echo "  - Values preserved: Yes\n";
    }
}

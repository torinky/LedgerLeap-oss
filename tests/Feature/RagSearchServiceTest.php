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

class RagSearchServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;
    private Folder $folder;
    private LedgerDefine $ledgerDefine;
    private RagSearchService $ragSearchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        config(['rag.enabled' => true]);

        $this->ragSearchService = app(RagSearchService::class);
        $this->user = User::factory()->create();
        $this->folder = Folder::factory()->create(['creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
    }

    #[Test]
    public function vector_is_stored_as_json_string()
    {
        $embedding = array_fill(0, 768, 0.123);
        $this->mock(EmbeddingService::class, function ($mock) use ($embedding) {
            $mock->shouldReceive('embed')->andReturn([$embedding]);
        });

        $ledger = $this->createAndProcessLedger(['title' => 'Test JSON Storage'], $this->ledgerDefine);

        $chunk = DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->first();

        $this->assertNotNull($chunk, 'Chunk was not created.');
        $this->assertJson($chunk->embedding, 'Embedding is not a valid JSON string.');
        $decoded = json_decode($chunk->embedding, true);
        $this->assertIsArray($decoded);
        $this->assertCount(768, $decoded, 'Embedding dimension is incorrect.');
        $this->assertEquals(0.123, $decoded[0]);
    }

    #[Test]
    public function it_performs_hybrid_search_with_mroonga()
    {
        // 1. Setup Mocks and Data
        $vectorCat = array_fill(0, 768, 0.1);
        $vectorDog = array_fill(0, 768, 0.9);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        // When embed is called with an array, it returns array of arrays
        $embeddingServiceMock->shouldReceive('embed')->with(["About Cats\n\nA document about cats"])->andReturn([$vectorCat]);
        $embeddingServiceMock->shouldReceive('embed')->with(["About Dogs\n\nA document about dogs"])->andReturn([$vectorDog]);
        // When embed is called with a single string for the search query, it returns a single array (NOT array of arrays)
        $embeddingServiceMock->shouldReceive('embed')->with('cats')->andReturn($vectorCat);

        // Create ledgers and process them to generate chunks
        $ledgerCat = $this->createAndProcessLedger(['title' => 'About Cats', 'description' => 'A document about cats'], $this->ledgerDefine);
        $ledgerDog = $this->createAndProcessLedger(['title' => 'About Dogs', 'description' => 'A document about dogs'], $this->ledgerDefine);

        // 2. Execute Search with keyword filter
        // Debug: Check what chunks exist
        $allChunks = DB::table('ledger_chunks')->get();
        $this->assertGreaterThan(0, $allChunks->count(), 'Chunks should exist in database. Found: ' . $allChunks->count());
        dump('Chunks:', $allChunks->pluck('chunk_text', 'id')->toArray());
        dump('Searching for:', 'cats');
        
        $results = $this->ragSearchService->searchLedgers('cats');

        // 3. Assertions
        $this->assertNotEmpty($results, 'Search should return results.');
        
        // The cat document should have a much better score than the dog document
        $catResult = collect($results)->firstWhere('ledger_id', $ledgerCat->id);
        $this->assertNotNull($catResult, 'Cat ledger should be in results');
        
        // Score is 1 - cosine_distance, so identical vectors (distance=0) should give score=1
        // Since vectors are identical, expect score close to 1
        $this->assertGreaterThan(0.99, $catResult['max_score'], 'Identical vectors should have very high similarity (score close to 1)');
    }
    
    #[Test]
    public function search_with_filters_correctly_narrows_results()
    {
        $vector1 = array_fill(0, 768, 0.1);
        $vector2 = array_fill(0, 768, 0.2);
        $this->mock(EmbeddingService::class, function ($mock) use ($vector1, $vector2) {
            $mock->shouldReceive('embed')->with(['doc in folder 1'])->andReturn([$vector1]);
            $mock->shouldReceive('embed')->with(['doc in folder 2'])->andReturn([$vector2]);
            // For the search query itself
            $mock->shouldReceive('embed')->with('doc')->andReturn($vector1);
        });

        $folder2 = Folder::factory()->create(['creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $ledgerDefine2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        $ledger1 = $this->createAndProcessLedger(['title' => 'doc in folder 1'], $this->ledgerDefine);
        $ledger2 = $this->createAndProcessLedger(['title' => 'doc in folder 2'], $ledgerDefine2);

        // Search with folder filter
        $results = $this->ragSearchService->searchLedgers('doc', 10, ['folder_id' => $folder2->id]);

        $this->assertCount(1, $results, 'Should find only one document with folder filter.');
        $this->assertEquals($ledger2->id, $results[0]['ledger_id'], 'Wrong ledger returned with filter.');
    }

    private function createAndProcessLedger(array $content, LedgerDefine $ledgerDefine): Ledger
    {
        $ledger = Ledger::factory()->minimal()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => $content,
        ]);

        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle(app(EmbeddingService::class));
        
        // Wait for Mroonga to index the full-text and vector data
        sleep(1);

        return $ledger;
    }
}

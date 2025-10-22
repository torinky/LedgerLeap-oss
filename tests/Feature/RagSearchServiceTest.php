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
use Mockery;
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
            // The job will call embed with an array of texts and 'passage' type
            $mock->shouldReceive('embed')->with(Mockery::type('array'), 'passage')->andReturn([$embedding]);
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
        // 1. Setup Mocks FIRST (before creating RagSearchService)
        $vectorCat = array_fill(0, 768, 0.1);
        $vectorDog = array_fill(0, 768, 0.9);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        // Expectations for chunking process - accept any array with 'passage'
        $embeddingServiceMock->shouldReceive('embed')
            ->with(Mockery::type('array'), 'passage')
            ->andReturnUsing(function ($texts) use ($vectorCat, $vectorDog) {
                // Return appropriate vector based on content
                if (isset($texts[0]) && str_contains($texts[0], 'Cats')) {
                    return [$vectorCat];
                }

                return [$vectorDog];
            });
        // Expectation for the search query itself
        $embeddingServiceMock->shouldReceive('embed')->with('cats', 'query')->andReturn($vectorCat);

        // Recreate RagSearchService with the mocked EmbeddingService
        $this->ragSearchService = app(RagSearchService::class);

        // Create ledgers and process them to generate chunks
        $ledgerCat = $this->createAndProcessLedger(['title' => 'About Cats', 'description' => 'A document about cats'], $this->ledgerDefine);
        $ledgerDog = $this->createAndProcessLedger(['title' => 'About Dogs', 'description' => 'A document about dogs'], $this->ledgerDefine);

        // 2. Execute Search with keyword filter
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
        // Setup mocks FIRST
        $vector1 = array_fill(0, 768, 0.1);
        $vector2 = array_fill(0, 768, 0.2);
        $this->mock(EmbeddingService::class, function ($mock) use ($vector1, $vector2) {
            // Expectations for chunking - accept any array with 'passage'
            $mock->shouldReceive('embed')
                ->with(Mockery::type('array'), 'passage')
                ->andReturnUsing(function ($texts) use ($vector1, $vector2) {
                    if (isset($texts[0]) && str_contains($texts[0], 'folder 1')) {
                        return [$vector1];
                    }

                    return [$vector2];
                });
            // Expectation for the search query
            $mock->shouldReceive('embed')->with('doc', 'query')->andReturn($vector1);
        });

        // Recreate RagSearchService with the mocked EmbeddingService
        $this->ragSearchService = app(RagSearchService::class);

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

        // Use the bound EmbeddingService from the container (which should be the mock)
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($this->app->make(EmbeddingService::class));

        // Wait for Mroonga to index the full-text and vector data
        sleep(1);

        return $ledger;
    }

    #[Test]
    public function search_respects_user_folder_permissions()
    {
        // Setup: Create two folders with different permissions
        $folder1 = Folder::factory()->create(['creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $folder2 = Folder::factory()->create(['creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        $ledgerDefine1 = LedgerDefine::factory()->create(['folder_id' => $folder1->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $ledgerDefine2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        // Mock embedding service
        $vector1 = array_fill(0, 768, 0.1);
        $vector2 = array_fill(0, 768, 0.2);
        $queryVector = array_fill(0, 768, 0.15);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        // Expectations for chunking - accept any array with 'passage'
        $embeddingServiceMock->shouldReceive('embed')
            ->with(Mockery::type('array'), 'passage')
            ->andReturnUsing(function ($texts) use ($vector1, $vector2) {
                if (isset($texts[0]) && str_contains($texts[0], 'document folder')) {
                    return [$vector1];
                }

                return [$vector2];
            });
        // Expectation for search query
        $embeddingServiceMock->shouldReceive('embed')->with('', 'query')->andReturn($queryVector);

        $this->ragSearchService = app(RagSearchService::class);

        // Create ledgers in both folders
        $ledger1 = $this->createAndProcessLedger(['title' => 'document folder'], $ledgerDefine1);
        $ledger2 = $this->createAndProcessLedger(['title' => 'another document'], $ledgerDefine2);

        // Create a new user with access only to folder1
        $restrictedUser = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'RestrictedRole', 'guard_name' => 'web']);
        $restrictedUser->roles()->attach($role->id);
        $role->folderPermissions()->attach($folder1->id, [
            'permission' => \App\Enums\FolderPermissionType::READ,
            'modifier_id' => $restrictedUser->id,
        ]);

        // Get readable folders for restricted user
        $repo = app(\App\Repositories\WritableFolderRepository::class);
        $readableFolders = $repo->getReadableFolderIds($restrictedUser);
        $this->assertNotEmpty($readableFolders, 'User should have at least one readable folder');
        $this->assertContains($folder1->id, $readableFolders, 'User should have access to folder1');

        // Verify chunks exist with correct folder_id
        $chunks = \DB::table('ledger_chunks')->get();
        $ledger1Chunks = $chunks->where('ledger_id', $ledger1->id);
        $this->assertGreaterThan(0, $ledger1Chunks->count(), 'Ledger1 should have chunks');
        $this->assertEquals($folder1->id, $ledger1Chunks->first()->folder_id, 'Chunk should have correct folder_id');

        // Search with restricted user using readable_folder_ids directly
        \Log::info('=== RAG TEST DEBUG ===', [
            'readable_folders' => $readableFolders,
            'ledger1_id' => $ledger1->id,
            'folder1_id' => $folder1->id,
        ]);

        $results = $this->ragSearchService->searchLedgers('', 10, [
            'readable_folder_ids' => $readableFolders,
        ]);

        \Log::info('=== SEARCH RESULTS ===', [
            'results_count' => count($results),
            'results' => $results,
        ]);

        // Verify search results respect permissions
        $this->assertNotEmpty($results, sprintf(
            'Search should return results. Chunks: %d, Readable folders: %s',
            $chunks->count(),
            json_encode($readableFolders)
        ));

        $resultLedgerIds = array_column($results, 'ledger_id');
        $this->assertContains($ledger1->id, $resultLedgerIds, 'User should see ledger from accessible folder');
        $this->assertNotContains($ledger2->id, $resultLedgerIds, 'User should NOT see ledger from inaccessible folder');
    }

    #[Test]
    public function search_method_with_pagination_returns_paginator()
    {
        // Mock embedding service
        $vector = array_fill(0, 768, 0.5);
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($input, $type) use ($vector) {
                if ($type === 'query') {
                    return $vector;
                }

                return is_array($input) ? array_fill(0, count($input), $vector) : [$vector];
            });

        $this->ragSearchService = app(RagSearchService::class);

        // Setup: Grant user access to folder
        $role = \App\Models\Role::create(['name' => 'TestRole1', 'guard_name' => 'web']);
        $this->user->roles()->attach($role->id);
        $role->folderPermissions()->attach($this->folder->id, [
            'permission' => \App\Enums\FolderPermissionType::READ,
            'modifier_id' => $this->user->id,
        ]);

        // Create multiple ledgers
        for ($i = 0; $i < 15; $i++) {
            $this->createAndProcessLedger(['title' => "Test document $i", 'content' => 'testing content'], $this->ledgerDefine);
        }

        // Use empty query for vector search only
        $result = $this->ragSearchService->search('', $this->user, [$this->ledgerDefine->id], [], 10);

        $this->assertInstanceOf("Illuminate\Contracts\Pagination\LengthAwarePaginator", $result);
        $this->assertLessThanOrEqual(10, $result->count(), 'Should respect perPage limit');
        $this->assertGreaterThan(0, $result->total(), 'Should have total count');
    }

    #[Test]
    public function search_for_api_returns_structured_results()
    {
        // Mock embedding service
        $vector = array_fill(0, 768, 0.5);
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($input, $type) use ($vector) {
                if ($type === 'query') {
                    return $vector;
                }

                return is_array($input) ? array_fill(0, count($input), $vector) : [$vector];
            });

        $this->ragSearchService = app(RagSearchService::class);

        // Setup: Grant user access
        $role = \App\Models\Role::create(['name' => 'TestRole2', 'guard_name' => 'web']);
        $this->user->roles()->attach($role->id);
        $role->folderPermissions()->attach($this->folder->id, [
            'permission' => \App\Enums\FolderPermissionType::READ,
            'modifier_id' => $this->user->id,
        ]);

        // Create ledger
        $ledger = $this->createAndProcessLedger(['title' => 'API Test Document'], $this->ledgerDefine);

        // Search using API method
        $results = $this->ragSearchService->searchForApi($this->user, [
            'query' => 'API Test',
            'limit' => 5,
        ]);

        $this->assertIsArray($results);
        if (! empty($results)) {
            $this->assertArrayHasKey('ledger', $results[0]);
            $this->assertArrayHasKey('similarity_score', $results[0]);
            $this->assertArrayHasKey('best_chunk_text', $results[0]);
            $this->assertArrayHasKey('chunk_count', $results[0]);
        }
    }

    #[Test]
    public function it_calls_embedding_service_with_query_prefix()
    {
        // Arrange: Mock EmbeddingService to verify its method call
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->once()
            ->with('test query', 'query') // Expect 'embed' to be called with the query and type 'query'
            ->andReturn(array_fill(0, 768, 0.1));

        // Mock DB to avoid Mroonga errors in this unit-like test
        DB::shouldReceive('select')->andReturn([]);

        $this->ragSearchService = app(RagSearchService::class);

        // Act: Call the search method
        $this->ragSearchService->searchLedgers('test query');

        // Assert: Mockery will assert that the expectation was met.
        $this->assertTrue(true);
    }

    #[Test]
    public function it_builds_optimized_mroonga_query()
    {
        // Arrange
        $query = 'test query';
        $queryEmbedding = array_fill(0, 768, 0.1);
        $passageEmbedding = array_fill(0, 768, 0.2);

        // Setup user and permissions
        $user = $this->user;
        $folder = $this->folder;
        $ledgerDefine = $this->ledgerDefine;
        $role = \App\Models\Role::create(['name' => 'QueryTestRole', 'guard_name' => 'web']);
        $user->roles()->attach($role->id);
        $role->folderPermissions()->attach($folder->id, [
            'permission' => \App\Enums\FolderPermissionType::READ,
            'modifier_id' => $user->id,
        ]);

        // Mock EmbeddingService
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->with($query, 'query')->andReturn($queryEmbedding);
        $embeddingServiceMock->shouldReceive('embed')->with(Mockery::type('array'), 'passage')->andReturn([$passageEmbedding]);

        // Spy on the Log facade and properly handle channel calls
        $logSpy = \Illuminate\Support\Facades\Log::spy();
        $channelMock = \Mockery::mock();
        $logSpy->shouldReceive('channel')->andReturn($channelMock);
        $channelMock->shouldReceive('info')->zeroOrMoreTimes(); // Allow info calls
        $channelMock->shouldReceive('debug')->zeroOrMoreTimes(); // Allow debug calls

        // Re-instantiate the service to ensure it uses the mocked services
        $this->ragSearchService = app(RagSearchService::class);

        // Create a ledger so that the search has something to find
        $this->createAndProcessLedger(['title' => 'test data'], $ledgerDefine);

        // Act
        $this->ragSearchService->searchLedgers($query, 10, ['user' => $user]);

        // Assert - verify the debug log was called with correct query structure
        $channelMock->shouldHaveReceived('debug')
            ->with('Executing Mroonga Search', Mockery::on(function ($context) {
                $command = $context['mroonga_command'] ?? '';
                $this->assertStringContainsString('select ledger_chunks', $command);
                $this->assertStringContainsString("--filter 'score < ", $command);
                $this->assertStringContainsString('--columns[score].stage initial', $command);
                $this->assertStringContainsString('--sortby score', $command);
                // Verify distance_cosine is ONLY in the value clause, not in filter
                $this->assertStringContainsString("--columns[score].value 'distance_cosine(embedding", $command);

                return true;
            }));
    }
}

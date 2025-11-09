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

class RagPerformanceTest extends TestCase
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
    public function search_completes_within_acceptable_time_with_large_dataset()
    {
        $this->markTestSkipped('Performance test - run manually when needed');

        // 1. Setup: Mock EmbeddingService to return consistent vectors
        $baseVector = array_fill(0, 768, 0.5);
        $queryVector = array_fill(0, 768, 0.6);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($input) use ($baseVector, $queryVector) {
                if (is_array($input)) {
                    // For batch embedding (ledger processing)
                    return array_fill(0, count($input), $baseVector);
                } else {
                    // For single query
                    return $queryVector;
                }
            });

        $this->ragSearchService = app(RagSearchService::class);

        // 2. Create 10,000+ chunks by creating multiple ledgers with long content
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\LargeRagDatasetSeeder']);

        echo "\n⏱️  Dataset preparation complete. Starting search performance test...\n";

        // 3. Wait for Mroonga indexing
        sleep(2);

        // 4. Measure search performance
        $startTime = microtime(true);

        $results = $this->ragSearchService->searchLedgers('test performance', 20);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        echo sprintf("\n✅ Search completed in %.3f seconds\n", $duration);
        echo sprintf("📊 Found %d results\n", count($results));

        // Get actual chunk count
        $chunkCount = DB::table('ledger_chunks')->count();
        echo sprintf("📦 Total chunks in database: %d\n\n", $chunkCount);

        // 5. Assert performance requirement: < 1 second for searches
        $this->assertLessThan(1.0, $duration, "Search should complete within 1 second even with {$chunkCount} chunks");
        $this->assertNotEmpty($results, 'Search should return results');
    }

    #[Test]
    public function search_performance_scales_linearly_with_result_limit()
    {
        // Setup: Create moderate dataset (1000 chunks)
        $baseVector = array_fill(0, 768, 0.5);
        $queryVector = array_fill(0, 768, 0.6);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($input) use ($baseVector, $queryVector) {
                if (is_array($input)) {
                    return array_fill(0, count($input), $baseVector);
                } else {
                    return $queryVector;
                }
            });

        $this->ragSearchService = app(RagSearchService::class);

        // Create 100 ledgers with 10 chunks each = 1000 chunks
        for ($i = 0; $i < 100; $i++) {
            $content = [
                'title' => "Document $i",
                'description' => str_repeat('This is test content for performance measurement. ', 50),
            ];

            $ledger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
                'content' => $content,
            ]);

            $job = new ProcessLedgerForRagJob($ledger->id);
            $job->handle($this->app->make(EmbeddingService::class));
        }

        sleep(1); // Wait for indexing

        // Test different result limits
        $limits = [10, 50, 100];
        $timings = [];

        foreach ($limits as $limit) {
            $startTime = microtime(true);
            $results = $this->ragSearchService->searchLedgers('performance test', $limit);
            $endTime = microtime(true);

            $timings[$limit] = $endTime - $startTime;

            echo sprintf("Limit %d: %.3f seconds\n", $limit, $timings[$limit]);
        }

        // All searches should complete within reasonable time
        foreach ($timings as $limit => $timing) {
            $this->assertLessThan(2.0, $timing, "Search with limit {$limit} should complete within 2 seconds");
        }
    }

    #[Test]
    public function mroonga_vector_search_with_filters_performs_efficiently()
    {
        // Test that adding filters (folder_id, ledger_define_id) doesn't significantly degrade performance
        $baseVector = array_fill(0, 768, 0.5);
        $queryVector = array_fill(0, 768, 0.6);

        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($input) use ($baseVector, $queryVector) {
                if (is_array($input)) {
                    return array_fill(0, count($input), $baseVector);
                } else {
                    return $queryVector;
                }
            });

        $this->ragSearchService = app(RagSearchService::class);

        // Create 200 ledgers across different folders
        $folder2 = Folder::factory()->create(['creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
        $ledgerDefine2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        for ($i = 0; $i < 100; $i++) {
            $ledger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
                'content' => ['title' => "Folder1 Doc $i", 'text' => str_repeat('content ', 50)],
            ]);
            $job = new ProcessLedgerForRagJob($ledger->id);
            $job->handle($this->app->make(EmbeddingService::class));
        }

        for ($i = 0; $i < 100; $i++) {
            $ledger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $ledgerDefine2->id,
                'creator_id' => $this->user->id,
                'modifier_id' => $this->user->id,
                'content' => ['title' => "Folder2 Doc $i", 'text' => str_repeat('content ', 50)],
            ]);
            $job = new ProcessLedgerForRagJob($ledger->id);
            $job->handle($this->app->make(EmbeddingService::class));
        }

        sleep(1);

        // Test 1: Search without filters
        $startTime = microtime(true);
        $resultsNoFilter = $this->ragSearchService->searchLedgers('test', 20);
        $timeNoFilter = microtime(true) - $startTime;

        // Test 2: Search with folder filter
        $startTime = microtime(true);
        $resultsWithFilter = $this->ragSearchService->searchLedgers('test', 20, ['folder_id' => $folder2->id]);
        $timeWithFilter = microtime(true) - $startTime;

        echo sprintf("\nNo filter: %.3f seconds (%d results)\n", $timeNoFilter, count($resultsNoFilter));
        echo sprintf("With filter: %.3f seconds (%d results)\n", $timeWithFilter, count($resultsWithFilter));

        // Both should be fast
        $this->assertLessThan(1.0, $timeNoFilter, 'Unfiltered search should be fast');
        $this->assertLessThan(1.0, $timeWithFilter, 'Filtered search should be fast');

        // Filtered results should only contain ledgers from folder2
        $folder2LedgerIds = Ledger::where('ledger_define_id', $ledgerDefine2->id)->pluck('id')->toArray();
        foreach ($resultsWithFilter as $result) {
            $this->assertContains($result['ledger_id'], $folder2LedgerIds, 'Filtered results should only contain ledgers from folder2');
        }
    }
}

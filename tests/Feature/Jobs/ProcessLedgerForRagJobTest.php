<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ProcessLedgerForRagJobTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        config(['rag.enabled' => true]);
    }

    #[Test]
    public function it_calls_embedding_service_with_passage_prefix()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => ['title' => 'Test Title', 'body' => 'This is the content.'],
        ]);

        // Mock EmbeddingService
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')
            ->once()
            ->with(Mockery::type('array'), 'passage') // Expects an array of texts and the type 'passage'
            ->andReturn([
                array_fill(0, 768, 0.1),
                array_fill(0, 768, 0.2),
            ]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $this->assertTrue(true); // Mockery handles the assertion
    }

    #[Test]
    public function it_chunks_ledger_content_and_saves_to_db()
    {
        // Arrange
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $user->id,
            'content' => ['title' => 'A very long title', 'body' => 'Some text content here.'],
            'content_attached' => 'Text from attached files.'
        ]);

        // Mock EmbeddingService to return dummy vectors
        $embeddingServiceMock = $this->mock(EmbeddingService::class);
        $embeddingServiceMock->shouldReceive('embed')->andReturn([
            array_fill(0, 768, 0.3),
            array_fill(0, 768, 0.4),
        ]);

        // Act
        $job = new ProcessLedgerForRagJob($ledger);
        $job->handle($embeddingServiceMock);

        // Assert
        $this->assertDatabaseHas('ledger_chunks', [
            'ledger_id' => $ledger->id,
            'chunk_source' => 'content',
        ]);
        $this->assertDatabaseHas('ledger_chunks', [
            'ledger_id' => $ledger->id,
            'chunk_source' => 'content_attached',
        ]);
    }
}

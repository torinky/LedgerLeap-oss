<?php

namespace Tests\Feature\Observers;

use App\Enums\WorkflowStatus;
use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\RefreshDatabaseWithTenant;
use Tests\TestCase;

class LedgerObserverTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid()]);
        tenancy()->initialize($this->tenant);
    }

    #[Test]
    public function it_dispatches_job_on_ledger_creation()
    {
        Queue::fake();

        $ledger = Ledger::factory()->create();

        Queue::assertPushed(ProcessLedgerForRagJob::class, function ($job) use ($ledger) {
            return $job->getLedger()->id === $ledger->id;
        });
    }

    #[Test]
    public function it_dispatches_job_on_content_update()
    {
        Queue::fake();

        $ledger = Ledger::factory()->create([
            'content' => ['body' => 'initial content']
        ]);

        Queue::assertPushed(ProcessLedgerForRagJob::class, 1); // For creation

        // Update content
        $ledger->update([
            'content' => ['body' => 'updated content']
        ]);

        Queue::assertPushed(ProcessLedgerForRagJob::class, 2); // For update
    }

    #[Test]
    public function it_dispatches_job_on_content_attached_update()
    {
        Queue::fake();

        $ledger = Ledger::factory()->create([
            'content_attached' => 'initial attached content'
        ]);

        Queue::assertPushed(ProcessLedgerForRagJob::class, 1); // For creation

        // Update content_attached
        $ledger->update([
            'content_attached' => 'updated attached content'
        ]);

        Queue::assertPushed(ProcessLedgerForRagJob::class, 2); // For update
    }

    #[Test]
    public function it_does_not_dispatch_job_on_unrelated_field_update()
    {
        Queue::fake();

        $ledger = Ledger::factory()->create();
        Queue::assertPushed(ProcessLedgerForRagJob::class, 1); // For creation

        // Update a field other than content or content_attached
        $ledger->update([
            'status' => WorkflowStatus::PENDING_INSPECTION->value
        ]);

        // The job should not be dispatched again
        Queue::assertPushed(ProcessLedgerForRagJob::class, 1);
    }

    #[Test]
    public function it_deletes_chunks_on_ledger_deletion()
    {
        // This test requires a real job dispatch to create chunks first.
        // We will simulate the chunk creation.
        $ledger = Ledger::factory()->create([
            'content' => ['body' => 'test content']
        ]);

        // Manually create a chunk to simulate the job's effect
        \Illuminate\Support\Facades\DB::table('ledger_chunks')->insert([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledger->ledger_define_id,
            'folder_id' => $ledger->define->folder_id,
            'chunk_index' => 0,
            'chunk_text' => 'test chunk',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('ledger_chunks', [
            'ledger_id' => $ledger->id
        ]);

        // Delete the ledger
        $ledger->delete();

        // Assert that the related chunks are also deleted
        $this->assertDatabaseMissing('ledger_chunks', [
            'ledger_id' => $ledger->id
        ]);
    }
}

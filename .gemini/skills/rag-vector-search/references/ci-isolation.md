# EmbeddingService CI Isolation Patterns

## Problem

`EmbeddingService` calls `http://embedding:8000` (Python container).
This container is not running in CI (GitHub Actions / local `sail test`).
Any test that triggers `ProcessLedgerForRagJob` will timeout or throw a
`ConnectionException` if the service is not mocked.

## Solution 1: RAG_ENABLED=false in phpunit.xml (recommended for most tests)

```xml
<!-- phpunit.xml -->
<php>
    <env name="RAG_ENABLED" value="false"/>
</php>
```

With `RAG_ENABLED=false`, `LedgerObserver` skips `ProcessLedgerForRagJob` dispatch,
and `RagChunkExistingLedgersCommand` returns early with an error message.

## Solution 2: Queue::fake() to prevent job execution

```php
// The job is dispatched but never executed — no HTTP call made
Queue::fake();

$ledger = Ledger::factory()->create([...]);
// ProcessLedgerForRagJob was dispatched but not run

Queue::assertPushed(ProcessLedgerForRagJob::class);
```

## Solution 3: Mock EmbeddingService when testing RAG logic directly

```php
use App\Services\EmbeddingService;

$this->mock(EmbeddingService::class, function ($mock) {
    $mock->shouldReceive('getEmbedding')
        ->andReturn(array_fill(0, 768, 0.0));  // 768-dim zero vector

    $mock->shouldReceive('isAvailable')
        ->andReturn(true);
});
```

## Choosing the right isolation strategy

| Test type | Strategy |
|---|---|
| Feature test (Ledger CRUD) | `RAG_ENABLED=false` in phpunit.xml |
| Unit test for search logic | Mock `EmbeddingService` |
| Integration test for RAG pipeline | `Queue::fake()` + assert dispatched |
| Manual smoke test | Real container required |

## Detecting if embedding service is up

```bash
# Health check
curl http://localhost:8000/health
# Expected: {"status": "ok"}

# Check active model
curl http://localhost:8000/model
```

## Reference files

- `app/Jobs/ProcessLedgerForRagJob.php`
- `app/Observers/LedgerObserver.php` — dispatches on created/updated
- `app/Services/EmbeddingService.php`
- `config/rag.php` — `rag.enabled`, `rag.model.active`


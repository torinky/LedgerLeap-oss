# Queue::fake() Patterns — test-external-dependency-isolation Reference

## Queue::fake() Basic Pattern

```php
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessLedgerForRagJob;

protected function setUp(): void
{
    parent::setUp();
    // $fakeQueue = true (default in TestCase) already calls Queue::fake()
    // No additional setup needed when creating Ledgers
}
```

If asserting dispatch, set `$fakeQueue = false` and call `Queue::fake()` manually:

```php
protected bool $fakeQueue = false;

protected function setUp(): void
{
    parent::setUp();
    Queue::fake();
}

public function test_rag_job_dispatched(): void
{
    config(['rag.enabled' => true]);
    Ledger::factory()->create();

    if (config('rag.enabled')) {
        Queue::assertPushed(ProcessLedgerForRagJob::class);
    }
}
```

---

## BusFake Intercepting dispatchSync()

### Problem

When `Queue::fake()` is active, `BusFake::dispatchSync()` silently skips job execution:

```php
// BusFake.php (simplified)
public function dispatchSync($command, $handler = null)
{
    if ($this->shouldFakeJob($command)) {
        $this->commandsSync[] = $command;  // recorded but NOT executed
        return;
    }
    return $this->dispatcher->dispatchSync($command, $handler);
}
```

Symptom: `SomeJob::dispatchSync($id)` → `handle()` never called → assertions fail silently.

### Fix A: call `->handle()` directly (preferred)

```php
(new SomeJob($id))->handle();
```

### Fix B: exclude specific job from fake

```php
Bus::fake()->except([SomeJob::class]);
SomeJob::dispatchSync($id);  // only this job executes
```

### Fix C: opt out entirely

```php
protected bool $fakeQueue = false;

protected function setUp(): void
{
    parent::setUp();
    // set up only what you need
}
```

---

## delay() with Queue::fake()

`Queue::fake()` intercepts delayed dispatches too — they are never executed:

```php
// LedgerDefineObserver — delay does NOT execute in Queue::fake() environment
SomeJob::dispatch($id)->delay(now()->addSeconds(5));

// In tests, call handle() directly:
(new SomeJob($id))->handle();
```

---

## 4-Layer Test Coverage Map

Shows what `Queue::fake()` omits and where each omitted piece is covered:

```
Queue::fake() omits              → Covered by
─────────────────────────────────────────────────────────
dispatch timing (when fired)     → LedgerObserverTest ($fakeQueue=false)
  ├ dispatched on create              Queue::assertPushed
  ├ dispatched on content update
  ├ NOT dispatched on unrelated field update
  └ chunks deleted on ledger delete

RAG job logic                    → ProcessLedgerForRagJobTest ($fakeQueue=false)
  ├ chunk generation                  ->handle() direct call
  ├ differential update
  └ VLM markdown fallback

Attached file job chain          → ProcessAttachedFileTest / VectorizeAttachedFileTest
  ├ thumbnail job dispatch            Bus::fake()
  ├ VLM/OCR parallel jobs
  └ Tika→OCR→VLM upgrade path

Actual Embedding calls           → RagSearchServiceTest / RagPerformanceTest
  └ real Embedding container          #[Group('external')] — local only
```

**Always verify**: "What does this fake omit, and is that omission covered elsewhere?"


<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: test-external-dependency-isolation
description: Isolates external services (Embedding, VLM, LDAP, OCR) in LedgerLeap tests to prevent 60s timeouts in CI. Use when writing tests involving Ledger, AttachedFile, or any code that dispatches jobs to external containers.
compatibility: LedgerLeap (Laravel 12 / Sail / Queue)
---

# test-external-dependency-isolation

## Root Cause

```
Ledger::factory()->create()
  → LedgerObserver::created()
    → config('rag.enabled') === true
      → ProcessLedgerForRagJob::dispatch()
        → QUEUE_CONNECTION=sync → EmbeddingService::embed()
          → http://embedding:8000  ← not available in CI → 60s timeout
```

## External Service Classification

| Service | Class | CI available | Isolation |
|---|---|---|---|
| Embedding | `EmbeddingService`, `ProcessLedgerForRagJob` | ❌ | `Queue::fake()` (default) |
| VLM | `VlmClientService`, `ProcessVlmJob` | ❌ | `Queue::fake()` or `#[Group('external')]` |
| LDAP | `LdapService` | ❌ | `#[Group('external')]` |
| OCR | `OcrService` | ❌ | `#[Group('external')]` |
| MySQL/Mroonga | DB | ✅ | no isolation needed |

## Test Group Classification

| Group | When to apply | CI behavior |
|---|---|---|
| `external` | Real container required (VLM/LDAP/OCR) | excluded from unit/feature jobs |
| `database-migrations` | Uses `DatabaseMigrations` trait | isolated `db-migrations` job |
| _(none)_ | External deps isolated via `Queue::fake()` | normal execution |

## Default Queue::fake() (since Issue #74)

`tests/TestCase.php` applies `Queue::fake()` by default:

```php
protected bool $fakeQueue = true;  // default — all tests are protected
```

Set `$fakeQueue = false` only when:
- Using `Bus::fake()` (conflicts with Queue::fake)
- Asserting dispatch via `Queue::assertPushed()`
- Calling `->handle()` directly to test job logic
- Using `#[Group('external')]` real-container tests

## dispatch() Rule

**Always use `dispatch()` — never call external services directly in Observers/Services.**

```php
// ✅ correct — Queue::fake() intercepts this
ProcessLedgerForRagJob::dispatch($ledger->id);

// ❌ wrong — Queue::fake() has no effect
(new ProcessLedgerForRagJob($ledger->id))->handle(...);
```

## RAG_ENABLED=false in phpunit.xml

`phpunit.xml` sets `RAG_ENABLED=false` to prevent Observer dispatch in tests.
Tests that need RAG must opt in:

```php
protected function setUp(): void
{
    parent::setUp();
    config(['rag.enabled' => true]);  // opt in for this class only
}
```

## Detailed Patterns

See [references/queue-fake-patterns.md](references/queue-fake-patterns.md) for:
- `BusFake` intercepting `dispatchSync()` and the `->handle()` workaround
- `delay()` with `Queue::fake()`
- 4-layer test coverage map showing what each fake omits and where it's covered

## Checklist

- [ ] `Ledger::factory()->create()` tests: `$fakeQueue = true` (default) is enough
- [ ] `Bus::fake()` or `Queue::assertPushed()` tests: set `$fakeQueue = false`
- [ ] Real-container tests: `#[Group('external')]`; `DatabaseMigrations` tests: `#[Group('database-migrations')]`
- [ ] Observers/Services use `dispatch()` not direct calls
- [ ] RAG tests: `config(['rag.enabled' => true])` in setUp
- [ ] `dispatchSync()` in `$fakeQueue=true` context: replace with `->handle()`

# ColumnHtmlService Cache Implementation Walkthrough

## Context

`ColumnHtmlService::show()` renders table cell HTML for various column types (`textarea`, `auto_number`, `text`, `url`, `number`). In large tables (100 rows × 5 columns), the same cell may be rendered dozens of times per request. Caching is essential.

## Problem: Static Array Pollution

The initial implementation used a `private static array $requestCache`. This caused:
- Test contamination across test cases
- Manual `ReflectionClass` cleanup in `tearDown()`
- No Laravel-managed lifecycle

## Solution: Laravel 13 `Cache::memo()`

`Cache::memo()` wraps the default cache store in a `MemoizedStore` that keeps resolved values in PHP memory for the current request.

### Key behaviors
- **First `get()`**: reads from underlying store (Redis/file), stores in memory
- **Subsequent `get()`**: returns from memory (~0.01ms)
- **`put()`**: writes to both memory and underlying store
- **`flush()`**: clears both memory and underlying store

## Implementation

### 1. Cache key design

```php
$cacheKey = "column_html:{$type}:{$tenantId}:{$record->id}:{$colId}";
```

- `type`: column type (`textarea`, `auto_number`, etc.)
- `tenantId`: prevents cross-tenant leaks
- `record->id` + `colId`: unique per cell

**Removed from key**: `updated_at->getTimestamp()` — this caused 0% hit rate because every save changed the key.

### 2. Early return in `show()`

```php
public function show(...)
{
    // Convert array input early
    if (is_array($columnDefineData)) {
        $columnDefineData = new ColumnDefine($columnDefineData);
    }

    // Skip mount() + rendering if cached
    if (! $asCreate && ! $highlight && $record && is_object($columnDefineData)) {
        $type = $columnDefineData->type ?? null;
        if (in_array($type, ['textarea', 'auto_number', 'text', 'url', 'number'])) {
            $cacheKey = "column_html:{$type}:...";
            $cached = Cache::memo()->get($cacheKey);
            if ($cached !== null) {
                $html = $type === 'textarea'
                    ? '<div class="prose ...">' . $cached . '</div>'
                    : $cached;
                return new HtmlString($html);
            }
        }
    }

    // Full render path
    $this->mount($columnDefineData, ...);
    // ...
}
```

### 3. Shared cache helper

```php
private function getCachedColumnHtml(string $type, ?Ledger $record, string $rawHtml, ?string $highlight): string
{
    if ($highlight || ! $record) {
        return $this->autoLinkService->convert($rawHtml, $this->columnDefineData, $record, $highlight);
    }

    $currentTenantId = $this->tenantId ?? tenant()?->id ?? $record?->define?->tenant_id ?? 'global';
    $colId = $this->getColumnDefineProperty('id');
    $cacheKey = "column_html:{$type}:{$currentTenantId}:{$record->id}:{$colId}";
    $ttl = config('ledgerleap.cache.column_html_ttl', 3600);

    $cached = Cache::memo()->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    $html = $this->autoLinkService->convert($rawHtml, $this->columnDefineData, $record, $highlight);
    Cache::memo()->put($cacheKey, $html, $ttl);

    return $html;
}
```

### 4. Invalidation on save

```php
// Ledger::booted()
static::saved(function ($ledger) {
    app(ColumnHtmlService::class)->clearCacheForLedger($ledger);
});
```

```php
public function clearCacheForLedger(Ledger $ledger): void
{
    // Flush MemoizedStore memory first
    Cache::memo()->flush();

    // Then delete Redis keys
    if (Cache::getStore() instanceof RedisStore) {
        $keys = Redis::keys("*column_html:*:{$tenantId}:{$ledger->id}:*");
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

## Test Strategy

```php
protected function tearDown(): void
{
    Cache::memo()->flush();
    parent::tearDown();
}

public function test_cache_hit_on_second_call(): void
{
    Cache::flush(); // Clear underlying store

    // First call → cache miss, renders HTML
    $html1 = $service->show($columnDefine, 'Test', ..., $ledger);

    // Second call → cache hit, skips rendering
    $html2 = $service->show($columnDefine, 'Test', ..., $ledger);

    // Render method should only be called once
    $markdownRenderer->shouldHaveReceived('toHtml')->once();
}
```

## Lessons Learned

1. **Never include `updated_at` in cache keys** for read-heavy data — it destroys hit rates
2. **Buffer `logPerformance` JSON I/O** — writing to disk 500× per request dominates latency
3. **`Cache::memo()` > `static $requestCache`** — managed lifecycle, test-safe, consistent API
4. **Early return before `mount()`** — initialization cost matters at scale
5. **Always `Cache::memo()->flush()` before direct Redis deletes** — prevents stale memory reads

# Cache::memo() Pattern

`Cache::memo()` creates a `MemoizedStore` wrapper that caches resolved values **in memory** for the current request/job. This eliminates repeated Redis round-trips when the same key is read multiple times.

## When to use

- The same cache key is read **>1 time per request** (e.g. table cell rendering)
- Redis latency (~5ms) is larger than the compute time being cached

## Pattern

```php
use Illuminate\Support\Facades\Cache;

// In Service method — reads from Redis on first call, memory on repeats
$cached = Cache::memo()->get($cacheKey);
if ($cached !== null) {
    return $cached;
}

$html = $this->expensiveRender($data);
Cache::memo()->put($cacheKey, $html, $ttl);

return $html;
```

## Why not `static $requestCache`?

- `static` arrays leak across tests and require manual `tearDown()` cleanup
- `Cache::memo()` is managed by Laravel, auto-cleared per request/job
- `flush()` invalidates both memory **and** underlying store consistently

## Test cleanup

```php
protected function tearDown(): void
{
    Cache::memo()->flush();
    parent::tearDown();
}
```

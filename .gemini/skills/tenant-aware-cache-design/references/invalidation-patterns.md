# Invalidation with Memoization

When invalidating Redis keys directly (e.g. pattern delete), `MemoizedStore` may still hold stale values in memory. Always flush the memo cache too.

## Pattern

```php
public function clearCacheForLedger(Ledger $ledger): void
{
    // Flush memoized memory FIRST so subsequent reads hit Redis
    Cache::memo()->flush();

    // Then delete underlying Redis keys
    if (Cache::getStore() instanceof RedisStore) {
        $keys = Redis::keys("*column_html:*:{$tenantId}:{$ledger->id}:*");
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

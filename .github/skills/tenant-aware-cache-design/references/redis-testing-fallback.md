# Redis Testing Fallback

`Cache::tags()` requires a tag-aware driver:
- ✅ **Redis** — full support
- ❌ **Array** — no tag support (testing fallback)
- ❌ **File** — no tag support
- ❌ **Database** — no tag support

Verify the driver before designing tag-based invalidation:
```bash
# .env
CACHE_DRIVER=redis
```

If Redis is unavailable in tests, wrap tag calls:
```php
if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
    Cache::tags(['confidentiality'])->flush();
} else {
    Cache::flush();
}
```

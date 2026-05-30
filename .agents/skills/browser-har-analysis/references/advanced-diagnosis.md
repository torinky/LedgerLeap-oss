# HAR Advanced Diagnosis Patterns

## `lazyLoaded` Field Interpretation

Livewire v3 HAR payload contains `components[0].snapshot.data.lazyLoaded`.

| Value | Meaning |
|---|---|
| `0` | `#[Lazy]` is active and real content is not yet loaded ✅ |
| `null` | `#[Lazy]` not recognized or already mounted ❌ |

Check `lazy%` in `har_lazy_analysis.py` output. If lower than expected, inspect `lazyLoaded` field individually.

## `$commit` NO UPDATES Diagnosis

When empty `$commit` requests occur regularly:
```
[ 15] 9072ms ['$commit'] (+10.3s) ← NO UPDATES
[ 17]  897ms ['$commit'] (+9.4s)  ← NO UPDATES
```

**Diagnosis flow**:
1. Search for `wire:poll` components: `grep -r 'wire:poll' resources/views/livewire/ledger/`
2. Trace Alpine.js `x-data` + `$nextTick` + `IntersectionObserver`
3. Check `#[Reactive]` property impact (child component may trigger dirty checks alone)
4. Check for Livewire DevTools interference

## `wire:key` Dynamic Change + `#[Lazy]` Forced Remount

To reuse a child component within the same page, change `wire:key` dynamically:

```php
// Parent (IndexManager)
public $recordsTableMountKey = 0;

public function changeCurrentFolder($folderId)
{
    $this->currentFolderId = $folderId;
    $this->recordsTableMountKey++;
    $this->dispatch('folderChanged', folderId: $folderId);
}
```

```blade
<livewire:ledger.records-table
    wire:key="ledger-records-table-mount-{{ $recordsTableMountKey }}"
    :keywords="$keywords" ... />
```

Effect: Livewire discards the existing component and remounts, reproducing the `#[Lazy]` placeholder → real content lifecycle.

## Alpine.js `x-data` + `IntersectionObserver` Interaction

If `render()` dispatches `ledger-sections-rendered` → Alpine.js `setupObserver()` → `IntersectionObserver` reconfiguration cycle occurs:
- `$nextTick` DOM query → Alpine.js internal reactive dependency changes → dirty flag may be set
- **Fix**: manage observer lifecycle explicitly with `init()` / `destroy()`, add change detection to `setupObserver()` (reconfigure only when DOM structure changes)

## Blade Component Render Spike Pattern

When `column_html_show_ms` shows sporadic spikes in `auto_number` or `text` types, suspect **Blade component rendering** after regex match.

**Diagnosis flow**:
1. Use `analyze_perf_log.py` to identify spike render_kind and ledger_id/col_id
2. Check actual column data in DB (text length, match count)
3. Check if `AutoLinkService::convert()` loops over `Blade::render()` calls
4. Benchmark processing time per match count

**Fix example**:
```php
// Before: per-match Blade::render() → linear growth
$iconHtml = Blade::render("<x-mary-icon ... />");

// After: request-level cache → constant cost
private static array $iconHtmlCache = [];
private function getCachedIconHtml(string $iconName): string
{
    if (! isset(self::$iconHtmlCache[$iconName])) {
        self::$iconHtmlCache[$iconName] = Blade::render("<x-mary-icon ... />");
    }
    return self::$iconHtmlCache[$iconName];
}
```
→ 100 matches: 130ms → 13ms (90% reduction)

Evidence: [docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md](../../../docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md)

## `Cache::remember()` Pitfall

When `Cache::remember()` closure is not saved correctly:
- Switch to `Cache::get()` + `Cache::put()`
- Use request-level in-memory cache to avoid Redis round-trips

```php
private static array $requestCache = [];

public function show(...)
{
    $cacheKey = "...";
    // Request cache (~0.01ms)
    if (isset(self::$requestCache[$cacheKey])) {
        return self::$requestCache[$cacheKey];
    }
    // Redis cache (~5ms)
    $cached = Cache::get($cacheKey);
    if ($cached !== null) {
        self::$requestCache[$cacheKey] = $cached;
        return $cached;
    }
    // Generate → save both
    $html = $this->generateHtml(...);
    Cache::put($cacheKey, $html, $ttl);
    self::$requestCache[$cacheKey] = $html;
    return $html;
}
```

Evidence: [Issue #200 comment](https://github.com/torinky/LedgerLeap/issues/200#issuecomment-4376836885)

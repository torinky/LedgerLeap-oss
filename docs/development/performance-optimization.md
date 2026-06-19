# Performance Optimization Guide

A developer reference for LedgerLeap performance optimization covering implemented techniques, measurement methods, and troubleshooting.

## Purpose

This guide helps LedgerLeap developers:
- Understand and apply existing optimization patterns across the stack
- Measure and monitor application performance
- Diagnose and resolve common performance issues

## Scope

**Covered:**
- Frontend optimization (Vite, Alpine.js, Livewire)
- Backend optimization (Eloquent, caching, async processing)
- Database optimization (Mroonga, indexes)
- Performance measurement and monitoring

**Not covered:**
- Production monitoring setup → `docs/operations/fileinspector-performance-monitoring.md`
- Infrastructure configuration → infrastructure documentation

---

## Frontend Optimization

### Vite Build Optimization

Always use `npm run build` in production. The development server's HMR (Hot Module Replacement) adds overhead that delays Alpine.js initialization and event listener registration, causing noticeable UI blocking and slow focus response.

| Environment | Command | Characteristics |
|-------------|---------|-----------------|
| Development | `npm run dev` | HMR enabled, slower response |
| Production  | `npm run build` | Optimized bundle, instant Alpine.js |

### Alpine.js Optimization

#### x-cloak

Prevents flash of uninitialized content before Alpine.js loads:

```blade
<div x-data="{ open: false }" x-cloak>
    <div x-show="open">
        <!-- Rendered after Alpine.js initializes -->
    </div>
</div>
```

```css
/* app.css */
[x-cloak] { 
    display: none !important; 
}
```

#### x-show vs x-if

| Directive | Behavior | Use Case |
|-----------|----------|----------|
| `x-show` | Toggles CSS `display` (DOM preserved) | Frequently toggled elements |
| `x-if` | Adds/removes from DOM | Rarely displayed elements |

**Recommendation:**
- Modals, drawers → `x-show` (frequent open/close)
- Conditional heavy content → `x-if` (reduces DOM size)

#### Event Listener Scoping

```javascript
// ❌ Avoid: global event listeners
window.addEventListener('click', handler);

// ✅ Prefer: scoped to component
Alpine.data('fileInspector', () => ({
    init() {
        this.$el.addEventListener('click', handler);
    }
}));
```

### Livewire Optimization

#### Input Debouncing

Control server request frequency:

```blade
<!-- ❌ Request on every keystroke -->
<input wire:model.live="search" />

<!-- ✅ 500ms debounce -->
<input wire:model.live.debounce.500ms="search" />

<!-- ✅ 1000ms debounce for search fields -->
<input wire:model.live.debounce.1000ms="search" />
```

#### Lazy Loading

Defer heavy component initialization:

```php
use Livewire\Attributes\Lazy;

#[Lazy]
class HeavyComponent extends Component
{
    public function placeholder()
    {
        return view('livewire.placeholders.skeleton');
    }
    
    public function render()
    {
        return view('livewire.heavy-component');
    }
}
```

#### Computed Properties

Cache results within a single request lifecycle:

```php
use Livewire\Attributes\Computed;

#[Computed]
public function expensiveData()
{
    return $this->performExpensiveCalculation();
}
```

```blade
<!-- Cached — no recalculation -->
{{ $this->expensiveData }}
{{ $this->expensiveData }}
```

#### wire:key

Prevent unintended DOM reuse:

```blade
@foreach($items as $item)
    <div wire:key="item-{{ $item->id }}">
        {{ $item->name }}
    </div>
@endforeach
```

Without `wire:key`, Livewire may reuse elements incorrectly, causing unexpected behavior.

---

## Backend Optimization

### Eloquent N+1 Prevention

#### Basic Eager Loading

```php
// ❌ N+1 problem
$ledgers = Ledger::all();
foreach ($ledgers as $ledger) {
    echo $ledger->creator->name; // Query per iteration
}

// ✅ Eager loading
$ledgers = Ledger::with('creator')->get();
foreach ($ledgers as $ledger) {
    echo $ledger->creator->name; // Single query
}
```

#### Complex Relation Eager Loading

```php
AttachedFile::with([
    'ledger:id,content,content_attached,ledger_define_id',
    'ledger.define:id,folder_id,title',
    'ledger.define.folder:id,title,path',
    'creator:id,name',
    'modifier:id,name',
])->findOrFail($fileId);
```

**Key points:**
- Use `:id,name` column selection to limit fetched data
- Chain relations with `.`
- Always include foreign keys (e.g., `ledger_define_id`)

### Caching Strategy

#### Application Cache

```php
use Illuminate\Support\Facades\Cache;

// 60-minute cache
$users = Cache::remember('users.all', 60, function () {
    return User::all();
});

// Tagged cache
Cache::tags(['users'])->put('user.1', $user, 60);
Cache::tags(['users'])->flush(); // Clear by tag
```

**Multi-tenant cache key design:** All `Cache::remember()` calls over tenant-scoped models must include the tenant ID in the cache key to prevent cross-tenant data leaks:

```php
$cacheKey = "my_key:{$tenantId}";  // use tenant()?->id ?? 'global'
```

#### Query Result Caching

```php
public function getCachedStatistics()
{
    return Cache::remember('statistics', 3600, function () {
        return DB::table('ledgers')
            ->select(DB::raw('COUNT(*) as total, AVG(composite_score) as avg_score'))
            ->first();
    });
}
```

### Async Processing

#### Queue Usage

```php
// ❌ Synchronous — user waits
ProcessAttachedFile::dispatchSync($file);

// ✅ Asynchronous — background
ProcessAttachedFile::dispatch($file);

// ✅ Delayed execution
ProcessAttachedFile::dispatch($file)->delay(now()->addSeconds(2));

// ✅ Dedicated queue
ProcessVlmExtraction::dispatch($file)->onQueue('vlm-processing');
```

#### Batch Processing

```php
use Illuminate\Support\Facades\Bus;

Bus::batch([
    new ProcessFile($file1),
    new ProcessFile($file2),
    new ProcessFile($file3),
])->then(function (Batch $batch) {
    // After all complete
})->dispatch();
```

#### Parallel VLM/OCR Processing

VLM and OCR jobs run in parallel, reducing total processing time by 30-40%:

```php
// app/Jobs/ProcessAttachedFile.php
ProcessVlmExtraction::dispatch($file)->onQueue('vlm-processing');
OcrAndOptimizeFile::dispatch($file)->delay(2)->onQueue('ocr');
```

The total time depends on the longer task (typically OCR at 15-120s). Users regain control after Tika processing (~5s), with VLM/OCR continuing in the background.

#### ColumnHtmlService Refactoring

Content column rendering was refactored from PHP string concatenation (~280 lines) to Blade components (~20 lines), with no performance regression due to Blade template caching.

---

## Database Optimization

### Mroonga Full-Text Search

#### Single-Column Indexes Only

Mroonga does not support composite full-text indexes. Always use single-column indexes combined with `OR`:

```sql
-- ✅ Correct: single indexes with OR
SELECT * FROM ledgers 
WHERE MATCH(content) AGAINST('keyword')
   OR MATCH(content_attached) AGAINST('keyword');

-- ❌ Does not work: composite index
SELECT * FROM ledgers 
WHERE MATCH(content, content_attached) AGAINST('keyword');
```

#### Query Builder Implementation

```php
// Ledger model scope method
public function scopeSearch($query, $keyword)
{
    return $query->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$keyword])
        ->orWhereRaw('MATCH(content_attached) AGAINST(? IN BOOLEAN MODE)', [$keyword]);
}
```

### Index Design

#### Composite Index Column Order

```php
Schema::table('ledgers', function (Blueprint $table) {
    // ✅ Most selective columns first
    $table->index(['ledger_define_id', 'status', 'created_at']);
});
```

**Principles:**
1. Equality comparison columns first
2. Range comparison columns afterward
3. ORDER BY columns last

#### Covering Indexes

```php
// Includes all SELECT columns — no table access needed
$table->index(['ledger_define_id', 'status', 'id', 'title']);
```

### Scoring System

Ledger importance is computed as a composite score via asynchronous batch processing. Queries read pre-computed scores from indexed columns with zero user-facing latency impact:

```php
// Migration: database/migrations/*_create_ledgers_table.php
Schema::table('ledgers', function (Blueprint $table) {
    $table->decimal('activity_score', 5, 2)->default(0);
    $table->decimal('composite_score', 5, 2)->default(0);
    $table->index('composite_score', 'idx_ledgers_composite_score');
});
```

---

## Performance Measurement and Monitoring

### Log-Based Measurement (`LogPerformance` Trait)

Use `App\Livewire\Traits\LogPerformance` for consistent measurement across Livewire components.

#### Blade-Level Measurement

```blade
<div x-data="{
    logPerformance(action, duration) {
        $wire.logPerformance(action, duration);
    }
}" 
@drawer-opened.window="
    const start = performance.now();
    setTimeout(() => {
        logPerformance('drawer_open', performance.now() - start);
    }, 10);
">
```

#### Livewire Component Measurement

```php
use App\Livewire\Traits\LogPerformance;

class LedgerHistoryManager extends Component
{
    use LogPerformance;

    public function mount()
    {
        $startTime = microtime(true);
        
        // ... processing ...

        $this->logPerformance('ledger_mount', (microtime(true) - $startTime) * 1000);
    }
    
    protected function getPerformanceContext(): array
    {
        return ['ledger_id' => $this->ledgerId];
    }
}
```

#### Configuration (`config/ledgerleap.php`)

```php
'performance' => [
    'enabled' => env('PERFORMANCE_LOGGING_ENABLED', false),
    'log_destination' => 'log', // 'log', 'json', 'both'
    'metrics' => [
        'ledger_mount' => true,
        'ledger_diff_render' => true,
        'ledger_load_more' => true,
        'ledger_toggle_selection' => true,
    ],
],
```

### Laravel Debugbar

```bash
composer require barryvdh/laravel-debugbar --dev
```

**Key features:**
- Query execution time visualization
- N+1 problem detection
- Memory usage monitoring
- View rendering time measurement

### Laravel Telescope

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Key features:**
- Full request performance analysis
- Job execution time monitoring
- Cache hit rate tracking
- Exception tracking

---

## Edge Cases and Constraints

### Livewire Full Re-Render Delay

**Symptom:** Input field changes take 1500ms+ to respond.

**Cause:** Livewire re-renders large Blade templates in full.

**Mitigation:**
1. Use `wire:model.live.debounce.1000ms` to reduce request frequency
2. Split components to reduce render scope
3. Use Lazy Loading for heavy sections

### N+1 Query Detection

Use Laravel Debugbar to identify duplicate queries, then apply eager loading:

```php
$ledgers = Ledger::with(['creator', 'modifier', 'define'])->get();
$ledgers = Ledger::with(['creator:id,name'])->get(); // Column selection
```

### Full-Text Search Latency

**Symptom:** Search queries take multiple seconds.

**Common causes:**
1. Mroonga index not created
2. Composite index used (unsupported — Mroonga requires single-column indexes)
3. `RefreshDatabase` used in test environment (Mroonga storage mode is non-transactional)

**Resolution:**
1. Use single-column indexes with `OR`
2. Use `DatabaseMigrations` for Mroonga tests
3. Add `sleep(1)` after index creation in tests to allow index rebuild

### Test Environment Constraints

- Mroonga storage mode does not support transactions. Use `DatabaseMigrations` or truncate Mroonga tables in `setUp()`.
- `RefreshDatabase` transaction rollback only affects InnoDB tables.
- When `CACHE_DRIVER=array` in tests, `Cache::tags()` behavior is unreliable — bypass caching entirely in test environment.

---

## Evidence Links

### Source Files

| Component | Path |
|-----------|------|
| LogPerformance trait | `app/Livewire/Traits/LogPerformance.php` |
| ProcessAttachedFile job | `app/Jobs/ProcessAttachedFile.php` |
| ProcessVlmExtraction job | `app/Jobs/ProcessVlmExtraction.php` |
| OcrAndOptimizeFile job | `app/Jobs/OcrAndOptimizeFile.php` |
| Ledger model (search scope) | `app/Models/Ledger.php` |
| Ledger model (scoring) | `app/Models/Ledger.php` |
| Performance config | `config/ledgerleap.php` |
| Ledgers table migration | `database/migrations/*_create_ledgers_table.php` |

### Test Files

| Area | Path |
|------|------|
| Mroonga search tests | `tests/Feature/Mroonga/` |
| Livewire performance tests | `tests/Feature/Livewire/` |
| Queue processing tests | `tests/Feature/Jobs/` |

### Related Documentation

- [Queue Processing](../architecture/QueueProcessing.md) — Queue workers and job design
- [File Processing Flow](../architecture/file-processing-flow.md) — VLM/OCR/Tika parallel processing
- [VLM/OCR Developer Guide](./vlm-ocr.md) — VLM/OCR optimization tips
- [Testing Best Practices](./Testing-Best-Practices.md) — Mroonga testing caveats
- [FileInspector Performance Monitoring](../operations/fileinspector-performance-monitoring.md) — Production measurement setup
- [Database Schema](../database/schema.md) — Mroonga index constraints

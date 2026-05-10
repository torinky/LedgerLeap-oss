# Early-Return Cache Pattern

When a Service has heavy initialization (`mount()`, DI resolution, etc.), check the cache **before** setup to avoid wasted work.

## Example

```php
public function show($columnDefineData, $initialValue, ..., ?Ledger $record = null)
{
    // Convert array to object early so type/id are accessible
    if (is_array($columnDefineData)) {
        $columnDefineData = new ColumnDefine($columnDefineData);
    }

    // Early cache hit: skip mount() and all rendering logic
    if (! $asCreate && ! $highlight && $record && is_object($columnDefineData)) {
        $type = $columnDefineData->type ?? null;
        if (in_array($type, ['textarea', 'auto_number', 'text', 'url', 'number'])) {
            $cacheKey = "column_html:{$type}:{$tenantId}:{$record->id}:{$colId}";
            $cached = Cache::memo()->get($cacheKey);
            if ($cached !== null) {
                // Wrap cached fragment in outer container if needed
                $html = $type === 'textarea'
                    ? '<div class="prose ...">' . $cached . '</div>'
                    : $cached;
                return new HtmlString($html);
            }
        }
    }

    // Cache miss: perform full initialization
    $this->mount($columnDefineData, $initialValue, ...);
    // ... rest of rendering
}
```

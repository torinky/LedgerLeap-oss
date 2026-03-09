# AsColumnArrayJson Cast Internals

## Serialization flow

```
Eloquent set()
  array_values($value)   ← converts associative → sequential
  json_encode()          ← stored as JSON string in DB

Eloquent get()
  json_decode($value, true)  ← returns [0=>..., 1=>..., ...]
```

## Why data_get() breaks

`AsColumnArrayJson` uses a `___serialized___` prefix internally for nested object
serialization. Laravel's `data_get()` traverses arrays with dot notation but cannot
handle the prefixed keys that the cast injects.

```php
// ❌ Returns NULL — internal prefix corrupts dot-path traversal
data_get($ledger->content_attached, '1.test.pdf.meta.content');

// ✅ Returns correct value — bypass data_get entirely
$ledger->content_attached[1]['test.pdf']['meta']['content'] ?? null;
```

## Why json_encode() must NOT be called manually

Cast columns (`files`, `chk`, `content`, `content_attached`) perform their own
`json_encode` inside `AsColumnArrayJson::set()`.  
Calling `json_encode()` before assignment double-encodes the string:
DB stores `"\"[...]\""`  → cast reads it as a plain string, not an array.

```php
// ❌ Double-encode corruption
$ledger->files = json_encode($filesArray);
$ledger->save();
// DB: "\"[\\\"file.pdf\\\"]\"" — broken

// ✅ Let the cast handle it
$ledger->files = $filesArray;
$ledger->save();
// DB: "[\"file.pdf\"]" — correct
```

## column_define.id → content index mapping

`column_define` is an array of `ColumnDefine` objects, each with an `id` field.
The `id` is **not** the array position — it is an explicit identifier.

```php
// column_define example
[
    ColumnDefine(id=0, name='title',  type='text'),
    ColumnDefine(id=2, name='amount', type='number'),  // id=1 is intentionally skipped
]

// After normalizeByColumnDefine([2 => '1000']):
// [0 => '', 1 => '', 2 => '1000']   ← indices 0..maxId filled

// DB stores: ["","","1000"]

// After read:
// $ledger->content[0] => ''
// $ledger->content[2] => '1000'  ✓
```

## Reference: source files

- `app/Casts/AsColumnArrayJson.php`
- `app/Models/Ledger.php` → `$casts` array
- `app/Models/LedgerDefine.php` → `normalizeByColumnDefine()`


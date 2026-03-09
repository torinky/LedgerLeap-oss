<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: ledger-content-data-structure
description: Explains LedgerLeap's Ledger content array structure (numeric index = column ID) and AsColumnArrayJson cast rules. Use when content[n] returns null, test data is misaligned, data_get() returns null on content/content_attached, or latest_diff_id is missing.
compatibility: LedgerLeap (AsColumnArrayJson cast, LedgerDefine column_define, Livewire)
---

# ledger-content-data-structure

## Decision Tree

```
content[n] returns null or wrong value?
│
├─ Is test data created with Ledger::factory()->create() directly?
│   YES → normalizeByColumnDefine() is NOT called. Gaps in column IDs shift indices.
│          FIX: fill all indices 0..maxId (see references/test-data-patterns.md)
│
├─ Using data_get($ledger->content, '1')?
│   YES → AsColumnArrayJson uses ___serialized___ prefix; data_get() breaks.
│          FIX: use direct array access $ledger->content[1]
│
├─ Accessing content_attached[n]?
│   YES → Same cast rules. Also requires index 0 => [] as a sentinel.
│          FIX: $ledger->content_attached[1]['file.pdf']['meta']['content']
│
└─ latest_diff_id null → latestDiff() returns null?
    YES → LedgerDiff::factory()->create() does NOT update Ledger.latest_diff_id.
          FIX: $ledger->update(['latest_diff_id' => $diff->id]); $ledger->fresh();
```

## content Storage Pipeline

```
Livewire input : [1 => 'text', 3 => 'val']         ← column IDs as keys
normalizeByColumnDefine() : [0=>'', 1=>'text', 2=>'', 3=>'val']
AsColumnArrayJson::set() : array_values() → ["","text","","val"]  ← stored JSON
AsColumnArrayJson::get() : [0=>'', 1=>'text', 2=>'', 3=>'val']   ← after read
```

## Key Rules

| Rule | Detail |
|---|---|
| Access pattern | `$ledger->content[0]` — never `data_get()` |
| column_define alignment | `content[n]` ↔ `column_define[*].id === n` |
| Missing indices | `normalizeByColumnDefine()` fills gaps with `''` or `[]` |
| `content_attached` | Same cast; requires `[0 => []]` sentinel at index 0 |
| `latest_diff_id` | Must be set explicitly; factory does not cascade |

## Checklist

- [ ] Test data uses consecutive indices from 0 to maxColumnId
- [ ] No `data_get()` on `content` or `content_attached`
- [ ] `content_attached` includes `0 => []`
- [ ] `latest_diff_id` set explicitly with `$ledger->update()`
- [ ] `$ledger->fresh()` called after `update()` when referencing relations

See [references/test-data-patterns.md](references/test-data-patterns.md) for code examples.
See [references/cast-internals.md](references/cast-internals.md) for AsColumnArrayJson details.


# Parent-Child Test Patterns (IndexManager + RecordsTable)

## Responsibility split

| Test in parent (IndexManager) | Test in child (RecordsTable) |
|---|---|
| State property values | Rendered HTML / filtering / sorting |
| URL query param sync | Individual row display logic |
| Event handling | Pagination |

## withQueryParams — correct initialisation

```php
// ✅ Sets initial state without triggering a Livewire reload
$component = Livewire::withQueryParams([
    'q'  => 'search term',
    'l'  => [$ledgerDefineId],
    'cf' => $folderId,
])->test(IndexManager::class);

$component->assertOk()
    ->assertSet('search', 'search term')
    ->assertSet('selectedLedgerDefineIds', [$ledgerDefineId]);
```

## render-order check via wire:key positions

```php
// ✅ Avoids false positives from duplicate text
$html = $component->html();
$posB = strpos($html, 'wire:key="ledger_record_' . $defineB->id . '"');
$posC = strpos($html, 'wire:key="ledger_record_' . $defineC->id . '"');
$this->assertLessThan($posC, $posB, 'B should appear before C');
```

## Anti-patterns

```php
// ❌ Child HTML not guaranteed in parent test render
$component->assertSee('SomeRecordTitle');

// ✅ Assert parent state only
$component->assertSet('totalRecords', 10);

// ✅ Or test the child component directly
Livewire::test(RecordsTable::class, ['search' => 'term', 'folderId' => $folderId])
    ->assertSee('SomeRecordTitle');
```

## wire:loading.remove.delay gotcha

`set()` triggers a Livewire reload; `wire:loading.remove.delay` temporarily hides
child HTML during the round-trip. Use `withQueryParams()` for initial state instead.

```php
// ❌ Child may be hidden by wire:loading.remove.delay
$component->set('search', 'term')->assertSee('Result');

// ✅ withQueryParams — no reload, child always present
$component = Livewire::withQueryParams(['q' => 'term'])->test(IndexManager::class);
$component->assertSet('search', 'term');
```

## #[Url] shared between parent and child

When both parent and child declare the same property as `#[Url]`, Livewire 3
recognises they point to the same query parameter and syncs them automatically.
Do **not** pass the value explicitly via `:param="$param"` in Blade — this causes
the parent's initial `null` to overwrite the child's URL-restored value on reload.

```php
// ✅ Each component restores its own value independently from the URL
// No explicit prop passing needed in Blade
```

## CannotMutateReactivePropException

```php
// ❌ Service calls loadMissing() inside child — Livewire detects mutation
$someService->process($this->record);  // $record->loadMissing('relation') inside

// ✅ Pass a defensive copy into the child
public function mount(Ledger $ledgerRecord): void
{
    $this->ledger = clone $ledgerRecord;
}
```

## Reference files

- `tests/Feature/Livewire/Ledger/IndexManagerIntegrationTest.php`
- `tests/Feature/Livewire/Ledger/RecordsTableCompositeScoreSortTest.php`
- `docs/development/testing/05-livewire.md`


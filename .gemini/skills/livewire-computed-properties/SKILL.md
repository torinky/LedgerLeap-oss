<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: livewire-computed-properties
description: Tests Livewire #[Computed], #[Reactive], and #[Url] properties correctly in LedgerLeap. Use when #[Computed] coverage is 0%, #[Reactive] child sync test fails, #[Url] query param not initializing, IndexManager+RecordsTable parent-child test is fragile, or CannotMutateReactivePropException is thrown.
compatibility: LedgerLeap (Livewire v3, PHPUnit, #[Computed] / #[Reactive] / #[Url] attributes)
---

# livewire-computed-properties

## Decision Tree

```
#[Computed] 0% coverage?
│  assertStatus(200) does not execute Computed methods — Blade ref required.
│  FIX: call instance()->methodName() directly (Pattern A)
#[Computed] wrong result in test?
│  Cache locks at first render(). Model must be in final state before Livewire::test().
│  FIX: set up model BEFORE Livewire::test() — not after (Pattern A)
#[Reactive] child sync test fails?
│  FIX: test from parent — Livewire::test(Parent::class)->set('prop')->assertSeeHtml(...)
#[Url] not initialized from query string?
│  FIX: Livewire::withQueryParams(['q' => 'val'])->test(Component::class)
CannotMutateReactivePropException?
│  Service called loadMissing inside child → Livewire treats it as mutation.
│  FIX: pass Collection::make($prop) or clone the model before passing to child.
totalRecords stays 0 in IndexManager test?
   recordsUpdated event not dispatched synchronously in tests.
   FIX: test RecordsTable child directly; don't assert parent counter.
```

## Pattern A — #[Computed] direct call

```php
// ❌ coverage stays 0%
Livewire::test(MyComponent::class, ['record' => $record])->assertStatus(200);

// ✅ instance() triggers the method
$instance = Livewire::test(MyComponent::class, ['record' => $record])->instance();
$result = $instance->computedData();
$this->assertInstanceOf(Collection::class, $result);
```

**Cache timing:** Model must be in final state *before* `Livewire::test()` is called.

## Pattern B — #[Url] initialization

```php
Livewire::withQueryParams(['q' => 'search', 'l' => [1, 2]])
    ->test(RecordsTable::class)
    ->assertSet('search', 'search')
    ->assertSet('selectedLedgerDefineIds', [1, 2]);
```

## Pattern C — Parent-child (IndexManager + RecordsTable)

Use `withQueryParams()` to set initial state — avoids `wire:loading.remove.delay` timing issues.
Assert parent state properties, not child HTML content.
See [references/parent-child-test-patterns.md](references/parent-child-test-patterns.md).

## #[CoversClass] requirement

Every test class must declare `#[CoversClass(ComponentClass::class)]`.
Without it, PHPUnit does not attribute coverage to that component.

## Checklist

- [ ] `#[Computed]` tested via `instance()->method()`, not `assertStatus(200)`
- [ ] Model state finalized before `Livewire::test()` (not after)
- [ ] `#[Url]` tests use `withQueryParams()`
- [ ] Parent-child tests use `withQueryParams()` for initial state
- [ ] `#[CoversClass]` declared on every test class
- [ ] `CannotMutateReactiveProp` → defensive clone/Collection::make() applied

See [references/test-patterns.md](references/test-patterns.md) for #[Computed] full examples.

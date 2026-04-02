---
applyTo: "app/Livewire/**,resources/views/livewire/**"
---

# Livewire Rules for LedgerLeap

## Tenant Context (`tenant()` null)

```
tenant() returns null in render()?
├─ #[Lazy] component? → placeholder() snapshots tenantId=null into state.
│   FIX: fall back to $model->tenant_id in render()
├─ /livewire/update request? → route has no {tenant} param.
│   FIX: use $this->tenantId saved in Livewire state
└─ mount() order? → boot() runs BEFORE mount(). tenantId set in mount() is NOT available at boot time.
    FIX: mount() must explicitly set $this->tenantId AND call initializeTenantContext()
```

**Pattern A — render-time fallback (required for ALL tenant-aware components):**
```php
$currentTenantId = $this->tenantId ?? tenant()?->id ?? $ledger->tenant_id;
```

**Shared resolver for Blade / URL helpers:**
- Prefer `resolveTenantId($model->tenant_id)` from `InitializesTenantContext` when the same tenant fallback is needed in multiple places.
- Avoid repeating `tenant()?->id ?? $model->tenant_id` inline in Blade partials; route generation should go through the shared resolver.

**Pattern B — #[Lazy] mount() explicit init:**
```php
if (is_null($this->tenantId)) {
    $this->tenantId = request()->route()?->originalParameters()['tenant'] ?? null;
}
```

## Computed / Reactive / URL Properties

```
#[Computed] 0% coverage? → assertStatus(200) does not execute Computed methods.
  FIX: $instance = Livewire::test(...)->instance(); $instance->computedData();
#[Computed] wrong result? → Cache locks at first render(). Set up model BEFORE Livewire::test().
#[Reactive] child sync fails? → Test from parent: ->set('prop')->assertSeeHtml(...)
#[Url] not initialized? → Livewire::withQueryParams(['q' => 'val'])->test(Component::class)
CannotMutateReactivePropException? → FIX: pass Collection::make($prop) or clone model
```

- **Heavy child components inside tab panels**: if parent tab switching causes the child to rerender expensively (for example history or related lists), avoid `#[Reactive]` for props that only need occasional sync. Prefer `#[On(...)]` + explicit dispatch so the tab body stays mounted and only the required state is updated.

## Loading UI

```
What changes when action completes?
├─ Page navigation → Tier 0: top progress bar (built-in)
├─ Major structure change → Tier 1: wire:loading.remove + skeleton element
├─ List content change, structure stays → Tier 2: opacity-50 + pointer-events-none overlay
└─ Single button → Tier 3: maryUI spinner attribute
```

- **Always specify `wire:target`** — omitting causes unrelated components to flicker
- **wire:key**: use stable values (`item-{{ $item->id }}`), never dynamic hash keys
- **x-show not working**: check for `display: X !important` CSS conflict; daisyUI injects `display:grid` on `.menu li > div`
- **table-pin-rows sticky**: parent MUST have height constraint (`max-h-[70vh]`)
- **After new Tailwind class**: run `sail npm run build`
- **Tab panels**: keep body DOM mounted and split tab-entry loading from inner-control loading (`selectedTab` vs `displayLevel` / filters) so the skeleton can appear on updates without remounting the whole tab.

## Parent-Child

- Use `$parent.method()` for sort/filter calls — NOT `Livewire.dispatch()`
- Public properties must be plain arrays — objects cause serialization errors
- See `.github/skills/livewire-tenant-context/SKILL.md` for full patterns
- See `.github/skills/livewire-computed-properties/SKILL.md` for test patterns
- See `.github/skills/livewire-loading-ui/SKILL.md` for loading tier examples

## Redundant Data Filtering (UI Optimization)

When displaying audit trails or history lists (e.g., `LedgerDiff`), sequential entries may share the same state (version, status, modifier, comments) due to multiple saves or system updates.

**Pattern — Filtering in `render()`:**
```php
public function render()
{
    $items = $this->ledger->history()->latest()->get()
        ->reduce(function ($carry, $item) {
            if ($carry->isEmpty()) {
                return $carry->push($item);
            }
            $prev = $carry->last();
            // Check redundancy criteria
            $isRedundant = $item->version === $prev->version
                && $item->status === $prev->status
                && $item->modifier_id === $prev->modifier_id
                && trim($item->comments ?? '') === trim($prev->comments ?? '');

            return $isRedundant ? $carry : $carry->push($item);
        }, collect())
        ->values(); // Re-index for Livewire

    return view('livewire.my-component', ['items' => $items]);
}
```

- **Why in `render()`?** Preserves DB audit integrity while improving UX.
- **Re-indexing**: Always call `->values()` on filtered collections to avoid "undefined array key" errors in Livewire's internal tracking.


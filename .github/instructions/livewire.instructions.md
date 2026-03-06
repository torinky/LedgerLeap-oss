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

## Parent-Child

- Use `$parent.method()` for sort/filter calls — NOT `Livewire.dispatch()`
- Public properties must be plain arrays — objects cause serialization errors
- See `.github/skills/livewire-tenant-context/SKILL.md` for full patterns
- See `.github/skills/livewire-computed-properties/SKILL.md` for test patterns
- See `.github/skills/livewire-loading-ui/SKILL.md` for loading tier examples


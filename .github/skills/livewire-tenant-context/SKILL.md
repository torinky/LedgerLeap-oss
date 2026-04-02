---
name: livewire-tenant-context
description: Diagnoses and fixes tenant context (tenancy()) returning null inside Livewire components. Also covers child↔parent property sync via wire:model.live="$parent.prop" and loading indicator control from parent. Use when tenant()?->id is null in render(), route generation fails with missing tenant parameter, #[Lazy] components show tenant-related errors, or child component displayLevel sync is needed.
compatibility: LedgerLeap (Livewire v3 + Stancl Tenancy + InitializesTenantContext trait)
---

# livewire-tenant-context

## Decision Tree

```
tenant() returns null in Livewire render()?
│
├─ Is the component #[Lazy]?
│   YES → placeholder() snapshots tenantId=null into Livewire state.
│          FIX: fall back to $model->tenant_id in render() (see Pattern A)
│
├─ Is it a subsequent request (/livewire/update)?
│   YES → route() has no {tenant} param → InitializesTenantContext cannot read it.
│          FIX: $this->tenantId (saved in state) should be restored by bootInitializesTenantContext
│          If still null → use Pattern A
│
└─ Is mount() called before boot()?
    NO — boot() runs BEFORE mount(). tenantId set in mount() is NOT available at boot time.
    FIX: mount() must explicitly set $this->tenantId AND call initializeTenantContext()
```

## Pattern A — Render-time fallback (recommended for all tenant-aware components)

```php
// render() — always use $model->tenant_id as final fallback
$currentTenantId = $this->tenantId      // Livewire state (saved across requests)
    ?? tenant()?->id                     // tenancy() initialized by boot
    ?? $ledger->tenant_id;              // model always has correct tenant_id
```

## Pattern C — Shared resolver for Blade and URL helpers

```php
$tenantId = $this->resolveTenantId($ledger->tenant_id);
$url = route('file.download-ocr-pdf', [
    'tenant' => $tenantId,
    'attachedFile' => $file->id,
]);
```

- Use this when a Livewire component, computed property, or Blade partial needs the same tenant fallback order in more than one place.
- Do not duplicate `tenant()?->id ?? $model->tenant_id` inline in Blade; call the shared resolver from `InitializesTenantContext`.
- Keep `render()` / `mount()` responsible for state setup; keep URL generation on the resolver path so `/livewire/update` requests still work when the route tenant parameter is missing.

### Evidence

- `docs/work/testing/2026-04-02_livewire-tenant-resolver-sharing.md`

### Freshness metadata

- `status`: confirmed
- `last_confirmed_at`: 2026-04-02
- `recheck_after`: 2026-07-02
- `recheck_trigger`: Livewire boot order or route generation changes, or another `tenant()?->id` null failure appears in tenant-aware views

## Pattern B — mount() explicit init (required for #[Lazy] components)

```php
public function mount(int $ledgerId): void
{
    $this->ledgerId = $ledgerId;

    // boot() runs before mount() so $this->tenantId may still be null here.
    // Explicitly read route param and initialize tenant.
    if (is_null($this->tenantId)) {
        $this->tenantId = request()->route()?->originalParameters()['tenant'] ?? null;
    }
    if ($this->tenantId) {
        $tenancy = app(\Stancl\Tenancy\Tenancy::class);
        if (! $tenancy->initialized) {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            if ($tenant) $tenancy->initialize($tenant);
        }
    }
}
```

## Why #[Lazy] breaks tenant context

| Request phase | What happens |
|---|---|
| Initial page load | `placeholder()` renders; Livewire snapshots `tenantId=null` into JS state |
| Lazy-load request | `mount()` is called with correct route; sets `tenantId` correctly |
| Subsequent update | `mount()` NOT called; `boot()` runs with `tenantId` from snapshot (OK if saved) |
| BUT: lazy-load via `/livewire/update` | Route has no `{tenant}` param → `boot()` cannot recover `tenantId` from route |

See [references/patterns.md](references/patterns.md) for full examples, tenant context edge cases, and loading indicator control.  
See [references/parent-binding.md](references/parent-binding.md) for `$parent` binding patterns and Tailwind JIT note.


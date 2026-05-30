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

- Use when a component, computed property, or Blade partial needs the same tenant fallback in multiple places.
- Do not duplicate `tenant()?->id ?? $model->tenant_id` inline in Blade; use the shared resolver from `InitializesTenantContext`.
- Keep `render()` / `mount()` responsible for state setup; keep URL generation on the resolver path.

## Pattern D — Tenant-safe shared Blade component links

See [references/blade-component-links.md](references/blade-component-links.md) for:
- Passing `tenantId` explicitly from parent to shared Blade components
- Why `tenant()` can be null during `/livewire/update` re-renders
- `wire:ignore` anti-pattern on clickable anchors

## Pattern B — mount() explicit init (required for #[Lazy] components)

See [references/mount-init-pattern.md](references/mount-init-pattern.md) for:
- Full `mount()` implementation with route param recovery
- `#[Lazy]` tenant context lifecycle table
- Loading indicator control and `$parent` binding patterns

## Evidence

- `docs/work/core-features/confidentiality-classification/2026-05-03_retrospective_issue191.md`
- `docs/work/testing/2026-04-02_livewire-tenant-resolver-sharing.md`

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-03
- recheck_after: 2026-08-03
- recheck_trigger: clickable tenant-aware Blade component fails to navigate, Livewire UI test checks rendering but not link target, or `wire:ignore` added to an anchor inside a shared component

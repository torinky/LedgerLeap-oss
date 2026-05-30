# Tenant-Safe Shared Blade Component Links

When a shared Blade component (e.g. `x-ledger.confidentiality-stamp`) generates `route()` URLs inside a tenant-scoped page, do **not** rely only on `tenant()` inside the component.

```blade
{{-- Parent view: pass tenantId explicitly --}}
<x-ledger.confidentiality-stamp
    :tenant-id="$tenantId ?? tenant('id')"
    :source-id="$ledgerDefine->id"
    source-type="ledger_define"
/>
```

```blade
{{-- Component: use the passed tenantId for route generation --}}
@props(['tenantId' => null, 'sourceId' => null, 'sourceType' => null])

@if ($sourceId && $sourceType)
    @php
        $url = match ($sourceType) {
            'ledger_define' => route('ledgerDefine.edit', ['tenant' => $tenantId, 'ledgerDefineId' => $sourceId]),
            'folder' => route('folder.edit', ['tenant' => $tenantId, 'folder' => $sourceId]),
            default => null,
        };
    @endphp
@endif
```

**Why this matters:**
- `/livewire/update` requests do not carry the `{tenant}` route parameter, so `tenant()` can return `null` during Livewire re-renders.
- If the component uses `tenant()` internally, the generated URL may lose the tenant segment and fail on click.
- Passing `tenantId` from the parent (which already resolved it via Pattern A or C) keeps the link stable across all request phases.

**Avoid `wire:ignore` on clickable links:**
- If the link `href` must update after a Livewire state change (e.g. tab switch), `wire:ignore` blocks DOM diff and the old URL remains.
- Only use `wire:ignore` on decorative or static elements, never on the anchor tag itself.

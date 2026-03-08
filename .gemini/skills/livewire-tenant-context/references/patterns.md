# livewire-tenant-context — Additional Patterns

## InitializesTenantContext trait behavior

The `bootInitializesTenantContext(Tenancy $tenancy)` method runs on **every** request lifecycle.

```
Request lifecycle:
  boot() → [bootInitializesTenantContext runs] → mount() (first request only) → render()
```

- If `$this->tenantId` is in Livewire snapshot → `boot()` can initialize tenancy → OK
- If `$this->tenantId` is `null` in snapshot (e.g. `#[Lazy]` initial render) → `boot()` cannot recover → ❌

## Full example: #[Lazy] component with tenant-safe render()

```php
#[Lazy]
class RelatedLedgers extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public int $ledgerId;

    public function mount(int $ledgerId): void
    {
        $this->ledgerId = $ledgerId;

        // Explicit init because boot() ran before mount()
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

    public function render()
    {
        $ledger = Ledger::findOrFail($this->ledgerId);

        // Safe: $ledger->tenant_id is always correct regardless of tenancy() state
        $currentTenantId = $this->tenantId ?? tenant()?->id ?? $ledger->tenant_id;

        return view('livewire.ledger.related-ledgers', [
            'currentTenantId' => $currentTenantId,
        ]);
    }
}
```

## route() generation with tenant parameter

Always pass `$currentTenantId` (not `tenant()?->id`) to `route()`:

```blade
{{-- ✅ Safe: uses view variable, not tenant() helper --}}
<a href="{{ route('ledger.edit', ['tenant' => $currentTenantId, 'ledgerId' => $record->id]) }}">

{{-- ❌ Unsafe in Lazy components: tenant() may be null --}}
<a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId' => $record->id]) }}">
```

## Error signature

```
Missing required parameter for [Route: ledger.edit]
[URI: {tenant}/ledger/edit/{ledgerId}] [Missing parameter: tenant].
```

This error in a `#[Lazy]` component almost always means `$currentTenantId` is null.
Root cause: `placeholder()` was rendered before tenancy was initialized.

## Issue reference

- First discovered and fixed in Issue #54 (Sprint 4)
- `$parent` binding and loading patterns added in Issue #54 (Sprint 8) — see [parent-binding.md](parent-binding.md)
- Commit: 1633cc30


# Mount Init Pattern for #[Lazy] Components

```php
public function mount(int $ledgerId): void
{
    $this->ledgerId = $ledgerId;

    // boot() runs before mount() so $this->tenantId may still be null here.
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
| Lazy-load request | `mount()` called with correct route; sets `tenantId` correctly |
| Subsequent update | `mount()` NOT called; `boot()` runs with `tenantId` from snapshot (OK if saved) |
| Lazy-load via `/livewire/update` | Route has no `{tenant}` param → `boot()` cannot recover `tenantId` from route |

See [references/patterns.md](references/patterns.md) for full examples, tenant context edge cases, and loading indicator control.  
See [references/parent-binding.md](references/parent-binding.md) for `$parent` binding patterns and Tailwind JIT note.

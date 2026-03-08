# Livewire Child↔Parent Property Sync Patterns

## `wire:model.live="$parent.prop"` binding (since Issue #54 Sprint 8)

When a child Livewire component needs to keep a property in sync with the parent
**and** the parent's `updatedXxx()` hook should fire on change, bind directly to the parent:

```blade
{{-- ✅ Child blade — binds to Show::$displayLevel directly --}}
<x-mary-group wire:model.live="$parent.displayLevel" ... />
```

```php
// ✅ Parent (Show.php) — fires automatically on any change (from child or self)
public function updatedDisplayLevel(int $level): void
{
    $this->dispatch('displayLevelUpdated', displayLevel: $level);
}
```

The child's own property is kept in sync via `#[On('displayLevelUpdated')]`:

```php
// Child (RelatedLedgers.php) — receives event for render-time use
#[On('displayLevelUpdated')]
public function syncDisplayLevel(int $displayLevel): void
{
    $this->displayLevel = $displayLevel;
}
```

**Why not `dispatch()` from child's `updatedXxx()`?**
Causes infinite loop: child dispatches → parent receives → parent dispatches → child receives → …
Use `$parent.prop` binding to let the parent own the property.

---

## Child component loading indicator controlled from parent

When a child is embedded with `wire:loading.remove`, the parent's `wire:loading`
controls visibility. Add the parent property name to `target`:

```blade
{{-- show.blade.php — covers both tab switch AND displayLevel change --}}
<div wire:loading wire:target="{{ $tabNavTargets }},displayLevel" class="w-full block">
    {{-- skeleton --}}
</div>
<div wire:loading.remove wire:target="{{ $tabNavTargets }},displayLevel">
    <livewire:ledger.related-ledgers ... lazy />
</div>
```

The child's own `wire:loading` cannot detect parent-scope property changes.

---

## Tailwind JIT — silent missing classes

After adding new utility classes (`group-hover:`, `opacity-*`, etc.),
run `sail npm run build`. Without a build, JIT-compiled classes silently have no effect.

```bash
./vendor/bin/sail npm run build
```

Symptoms: CSS transition/hover works in other components but not the new one.
Root cause: the new class was never compiled into the CSS bundle.


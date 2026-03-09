# Loading Indicator Patterns

## Tier 1 — Skeleton (Heavy Actions)

Use for: folder switch, initial load, actions that change page structure.

```blade
{{-- Hide current content while loading --}}
<div wire:loading.remove.delay wire:target="switchFolder,loadInitial">
    @include('livewire.ledger.records-table')
</div>

{{-- Show skeleton while loading --}}
<div wire:loading.delay wire:target="switchFolder,loadInitial">
    @include('livewire.placeholders.records-skeleton')
</div>
```

**Managing targets in parent component:**

```php
// In IndexManager.php — centralise target strings to avoid duplication
public string $heavyTargets = 'switchFolder,loadInitial,applyFolder';
public string $lightTargets = 'sortBy,filterBy,nextPage,prevPage';
```

```blade
<div wire:loading.remove.delay wire:target="{{ $heavyTargets }}">
    {{-- content --}}
</div>
```

## Tier 2 — Overlay (Light Actions)

Use for: sort, filter, pagination — content changes but structure stays.

```blade
<div class="relative">
    {{-- Overlay: dims + blocks interaction --}}
    <div wire:loading.delay wire:target="{{ $lightTargets }}"
         class="absolute inset-0 bg-base-100/50 z-10 pointer-events-auto">
    </div>

    {{-- Content: lower opacity while loading --}}
    <div wire:loading.class="opacity-50 pointer-events-none" wire:target="{{ $lightTargets }}">
        {{-- list items --}}
    </div>
</div>
```

## Tier 3 — Micro Interaction (maryUI)

```blade
{{-- maryUI button with built-in spinner --}}
<x-mary-button label="Save" wire:click="save" spinner="save" />

{{-- Custom button --}}
<button wire:click="toggleStatus" wire:loading.attr="disabled">
    <span wire:loading.remove wire:target="toggleStatus">Approve</span>
    <span wire:loading wire:target="toggleStatus" class="loading loading-spinner loading-xs"></span>
</button>
```

## Font Awesome icon stability in placeholders

Prevent icons showing as "?" during Livewire lazy placeholder rendering:

```css
/* In app.css or relevant CSS file */
.fa-solid, .fa-regular, .fa-brands {
    font-family: "Font Awesome 6 Free", sans-serif;
    font-weight: 900 !important;
}
```

Place `Font Awesome 6 Free` first in font-family to avoid fallback rendering before
the font loads.

## wire:loading vs wire:loading.class vs wire:loading.remove

| Directive | Behaviour |
|---|---|
| `wire:loading` | Show element while loading |
| `wire:loading.remove` | Hide element while loading |
| `wire:loading.class="..."` | Add class(es) while loading |
| `wire:loading.attr="disabled"` | Set attribute while loading |
| `.delay` modifier | Start showing after 200ms (prevents flash on fast responses) |

## Reference

- `app/Livewire/Ledger/IndexManager.php` — $heavyTargets / $lightTargets pattern
- `resources/views/livewire/ledger/index-manager.blade.php` — Tier 1/2 combined
- See [alpine-init-overlay.md](alpine-init-overlay.md) for Tier 0.5 overlay + requestIdleCallback pattern (since #77)


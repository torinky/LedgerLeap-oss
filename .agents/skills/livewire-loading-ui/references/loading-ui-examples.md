# Livewire Loading UI Examples

This file collects concrete patterns that are too verbose for `SKILL.md`.
They are aligned with LedgerLeap's Livewire guidance: keep `wire:target` explicit, keep tab bodies mounted when possible, and separate initial tab-entry loading from internal updates.

## Tab panel loading: keep DOM mounted, split targets

Use when a tab should remain mounted across tab switches, but inner controls still need loading feedback.

```blade
<div class="relative">
    {{-- Initial tab entry loading --}}
    <x-element.loading-overlay tier="2" :target="'selectedTab'">
        <div class="space-y-4 p-2 w-full animate-pulse">
            <div class="flex items-center gap-4 p-3 bg-base-200/40 rounded-lg">
                <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
                <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
            </div>
            <x-element.skeleton-table rows="5" cols="5" />
        </div>
    </x-element.loading-overlay>

    {{-- Internal updates only --}}
    <div wire:loading wire:target="displayLevel" class="w-full block">
        <div class="space-y-4 p-2 w-full">
            <div class="flex items-center gap-4 p-3 bg-base-200/40 rounded-lg">
                <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
                <div class="h-8 bg-base-300 rounded-full w-32 shimmer"></div>
            </div>
            <x-element.skeleton-table rows="5" cols="5" />
        </div>
    </div>

    {{-- Persistent body --}}
    <div wire:loading.remove wire:target="selectedTab,displayLevel">
        {{-- content stays mounted here --}}
    </div>
</div>
```

## Sticky table rows: parent height is required

`table-pin-rows` only works when the scroll container has an explicit height.

```blade
<div class="overflow-x-auto max-h-[70vh]">
    <table class="table table-pin-rows">...</table>
</div>
```

## Why this matters

- If the same loading gate controls both tab switching and inner updates, one state can suppress the other.
- A dedicated tab-entry overlay prevents blank tabs on first open.
- A dedicated internal-update loading block keeps skeleton feedback available when filters or display controls change.
- An explicit container height is required for pinned table headers to activate.


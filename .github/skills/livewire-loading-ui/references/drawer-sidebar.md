# Drawer Sidebar Fixed Positioning

## Problem

With `xl:drawer-open`, daisyUI's `.drawer-side` content scrolls with the page.
Tailwind `sticky top-16` does not work when an ancestor element has
`overflow: hidden` or `overflow: auto`.

## Solution: position fixed (inline style)

```blade
{{-- resources/views/layouts/appWithDrawer.blade.php --}}
<div class="drawer-side z-40 xl:w-64 2xl:w-72"
    style="position: fixed; top: 64px; height: calc(100vh - 64px); overflow-y: auto; overflow-x: hidden;">
    <label for="app-drawer" class="drawer-overlay w-full"></label>
    <ul class="menu overflow-y-auto overflow-x-hidden h-full xl:w-64 2xl:w-72 p-2">
        {{ $drawer ?? '' }}
    </ul>
</div>
```

- `top: 64px` — navbar height (measure actual value; may differ from `pt-20`=80px)
- `height: calc(100vh - 64px)` — full viewport minus navbar
- `overflow-y: auto` — sidebar scrolls independently

## Auto-scroll selected folder node

After Livewire re-renders the sidebar, the selected node may be offscreen.
`$nextTick` is required to run after DOM update:

```blade
{{-- resources/views/components/folder/tree.blade.php --}}
@if ($folder->id == $currentFolderId)
    x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
@endif
```

Without `$nextTick`, `scrollIntoView` executes before Livewire patches the DOM
and has no effect.

## Why not Tailwind sticky?

`position: sticky` requires:
1. A positioned scroll container ancestor
2. No ancestor with `overflow: hidden/auto` between the sticky element and the scroll container

daisyUI's `.drawer` wrapper sets its own overflow, which breaks condition 2.
Using `position: fixed` bypasses the ancestor chain entirely.

## Reference

- `resources/views/layouts/appWithDrawer.blade.php`
- `resources/views/components/folder/tree.blade.php`
- `docs/development/Livewire-Best-Practices.md` § 5


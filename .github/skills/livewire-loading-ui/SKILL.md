---
name: livewire-loading-ui
description: Implements consistent loading indicators and Alpine.js/daisyUI CSS rules in LedgerLeap Livewire components. Use when adding wire:loading, fixing x-show not working, fixing wire:key flicker, implementing sticky table headers, or fixing DaisyUI drawer sidebar scrolling.
compatibility: LedgerLeap (Livewire v3, Alpine.js, daisyUI v5, TailwindCSS JIT)
---

# livewire-loading-ui

## Loading Tier Selection

```
What changes when the action completes?
├─ Page navigation (wire:navigate)?
│   → Tier 0: top progress bar (built-in)
├─ Alpine.js client-side init (height measurement, many components)?
│   → Tier 0.5: Alpine init overlay — see references/alpine-init-overlay.md
├─ Major structure change (folder switch, initial load)?
│   → Tier 1: skeleton (wire:loading.remove + skeleton element)
├─ List content changes, structure stays (sort/filter/pagination)?
│   → Tier 2: overlay (opacity-50 + pointer-events-none on the list)
└─ Single button action?
    → Tier 3: maryUI spinner attribute on the button
```

**Always specify `wire:target`** — omitting it causes unrelated components to flicker.

See [references/loading-patterns.md](references/loading-patterns.md) for Tier 1/2/3 examples.
See [references/alpine-init-overlay.md](references/alpine-init-overlay.md) for Tier 0.5 + pitfalls (since #77).
See [references/loading-ui-examples.md](references/loading-ui-examples.md) for tab-panel and table-height examples.

## Tab Panel Loading

Tab panels that must stay mounted across tab switches should not gate the body with a single `wire:loading.remove` block.

- Keep the tab content DOM mounted; use an overlay or skeleton on top of it instead of swapping the body in and out.
- Separate **tab entry** loading from **internal updates** (`displayLevel`, filters, sort, etc.) with different `wire:target` values.
- If a loading state must show a skeleton, put the skeleton in the `wire:loading` slot of the overlay or in a dedicated `wire:loading` block — do not rely on the same gate that controls tab visibility.
- When a tab needs both a preserved DOM and a loading skeleton, prefer two layers:
  1. a lightweight initial tab-entry overlay
  2. an update-only skeleton/overlay for the inner controls

See [references/loading-ui-examples.md](references/loading-ui-examples.md) for concrete patterns.

## wire:key Rules

| ✅ Safe | ❌ Unsafe |
|---|---|
| `wire:key="ledger-records-stable"` | `wire:key="{{ Hash::make($id) }}"` |
| `wire:key="item-{{ $item->id }}"` | Any key that changes every render |

Dynamic keys force full destroy+recreate → focus loss, flicker, performance hit.

## Alpine.js x-show vs CSS Conflicts

```
x-show not working (element stays visible)?
├─ CSS `display: X !important` on the element?
│   YES → Alpine.js inline style (no !important) loses to CSS !important.
│          FIX: remove !important from the display rule.
└─ Inside .menu li > div (daisyUI)?
    YES → daisyUI auto-injects display:grid.
          FIX: display:block (no !important) — specificity beats :where().
          See references/daisy-css-specificity.md
```

## table-pin-rows (sticky header)

Parent element **must** have a height constraint — without it sticky never triggers.

See [references/loading-ui-examples.md](references/loading-ui-examples.md) for a minimal height-constrained example.

## Drawer sidebar fixed positioning

`xl:drawer-open` — `.drawer-side` doesn't scroll independently; `sticky` fails with `overflow:hidden/auto` ancestor.
Use `position: fixed` inline style. See [references/drawer-sidebar.md](references/drawer-sidebar.md).

## Tailwind JIT reminder

After adding new utility classes (`opacity-50`, `group-hover:*`, etc.),
run `sail npm run build`. New classes are silently ignored without a rebuild.

## Telemetry Pitfall

Do not route client-side loading or init telemetry through `$wire` unless the metric itself is intended to trigger a Livewire update. In LedgerLeap, sending Alpine timing events to a Livewire action can create extra `livewire/update` requests and duplicate rerenders. Prefer browser-side logging or another non-Livewire sink for purely observational metrics.

## Checklist

- [ ] `wire:loading` always has `wire:target`
- [ ] `wire:key` uses stable, non-random values
- [ ] `x-show` elements have no conflicting `display !important` CSS
- [ ] `table-pin-rows` parent has explicit height
- [ ] New Tailwind classes → `sail npm run build`
- [ ] Alpine init overlay: use `x-on:livewire:navigated.window.once` (NOT `@livewire:navigated`)
- [ ] Alpine `x-data` with methods: register via `Alpine.data()`, not inline shorthand
- [ ] Timing telemetry that should not rerender components stays off `$wire`
- [ ] Tab panels needing both persistence and loading use separate tab-entry and internal-update targets

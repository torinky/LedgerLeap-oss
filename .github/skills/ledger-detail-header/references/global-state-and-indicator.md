# Global State & Indicator Patterns

## Global State Management Pattern

Use an Alpine.js store (`ledgerState`) to centrally manage expand/collapse state across multiple column groups.

- Store group IDs as boolean keys + `__global__` flag.
- When `__global__` changes, reset all individual states to follow the global setting.

### Sub-component Reactivity (`checkStorage`)
- Individual groups (e.g., `LedgerDiffViewer`) sync local `collapsed` with the shared store via polling/watch.
- This ensures header toggle operations propagate immediately to all child components.

## Mandatory Indicator Pattern

Replace "required" text badges with modern indicator dots + tooltips.

```blade
<div class="indicator tooltip tooltip-right" data-tip="{{ __('ledger.diff.contains_required_items') }}">
    <span class="indicator-item badge badge-error badge-xs p-0 w-2 h-2 border-none"></span>
    <x-mary-icon name="o-folder-open" class="text-primary/70" />
</div>
```

- Wrapper: `indicator` class
- Dot: `indicator-item badge badge-error badge-xs` (2x2px, no border)
- Tooltip: `tooltip tooltip-right` with `data-tip`

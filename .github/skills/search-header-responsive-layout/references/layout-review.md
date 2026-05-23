# Search Header Responsive Layout Review

This reference expands the reusable review steps for LedgerLeap search headers. It exists because the main `SKILL.md` should stay compact, while the concrete breakpoint and scroll-occlusion reasoning needs a place to live when the pattern is reused.

## Canonical pattern seen in LedgerLeap

The current approved search card uses this shape:

- a short, visually distinct header band
- one dominant search input
- `sort_by` and `per_page` kept together through intermediate widths
- low-frequency controls gathered under a single collapse
- sticky placement that does not hide too much of the results list

```blade
<div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_minmax(11rem,11rem)_minmax(8.5rem,8.5rem)] xl:grid-cols-[minmax(0,1.5fr)_minmax(12rem,12rem)_minmax(8.5rem,8.5rem)] items-center">
    <x-mary-input ... class="input-primary input-lg shadow-md sm:col-span-2 lg:col-span-1" />
    <label class="form-control rounded-xl ...">sort_by</label>
    <label class="form-control rounded-xl ...">per_page</label>
</div>
```

## Breakpoint ladder to check

| Width stage | What to verify |
|---|---|
| Base / mobile | The search input is easy to hit, and the card does not feel tall. |
| Intermediate widths (`sm` / `md`) | `sort_by` and `per_page` still read as one group, even before the final single-column collapse. |
| Wide layouts (`lg` / `xl`) | The search field remains dominant and the card does not waste width on unnecessary empty space. |
| Sticky scroll state | The top card does not hide more of the results list than intended. |

## Scroll-occlusion checklist

When the card is sticky, always answer these questions before finalizing the layout:

1. How much of the first result row is hidden after the header sticks?
2. Does the header still feel thin enough that the list remains the main focus?
3. Does opening the collapse create too much vertical displacement?
4. Is the vertical motion acceptable on both mobile and desktop?

If any answer is negative, prefer one of these adjustments:

- reduce `pt` / `pb`
- shorten the header band
- keep the search input large but simplify secondary labels
- move more content into the collapse

## Good review questions

- Is the search field obviously the primary action?
- Are controls grouped by frequency of use?
- Is the header visually distinctive without becoming tall?
- Does the layout feel stable while the page scrolls?
- Does the final design preserve enough visible results beneath the sticky area?

## Promotion rule

If the same breakpoint/scroll issue appears in more than one feature, the pattern can graduate from a docs note into a reusable skill. Until then, keep feature-specific spacing values and local class tweaks in `docs/work/*`.


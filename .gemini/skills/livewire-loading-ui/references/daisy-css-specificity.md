# daisyUI CSS Specificity Rules

## Priority order (high → low)

```
1. inline style with !important   (highest)
2. CSS rule with !important
3. inline style (no !important)   ← Alpine.js x-show writes here
4. CSS rule (no !important)       (lowest)
```

**Alpine.js `x-show` writes `display: none` as a plain inline style (no !important).**  
A CSS rule with `!important` beats it → element stays visible even when x-show=false.

## Fix: remove !important from display rules

```css
/* ❌ Breaks x-show */
.some-class {
    display: block !important;
}

/* ✅ x-show inline style wins (it is still inline, just no !important) */
.some-class {
    display: block;
}
```

## daisyUI v5 .menu — automatic display:grid injection

daisyUI targets: `:where(li:not(.menu-title) > :not(ul,details,...))` — **specificity 0**.

Any class selector (specificity ≥ 1) overrides it without `!important`:

```css
/* ✅ Override daisyUI's display:grid on tree collapse divs */
.menu .tree li > div.tree-collapse {
    display: block;   /* overrides :where() display:grid */
    grid-template-rows: unset !important;
    grid-auto-columns: unset !important;
    grid-auto-flow: unset !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    background-color: transparent !important;
    width: 100% !important;
}
```

> Note: `display: block` has no `!important` here — this is intentional.
> The grid-related properties do use `!important` because daisyUI sets them
> through a higher-specificity selector.

## Diagnosis checklist

When `x-show` does not hide an element:

1. Open DevTools → inspect the element's computed `display`
2. Look for a CSS rule with `display: X !important` winning over inline style
3. Find the rule source (likely daisyUI `.menu` or a custom class)
4. Remove `!important` from `display` property only (keep for other properties)

## Reference

- `resources/css/tree.css` — real-world example of the fix
- `docs/development/Livewire-Best-Practices.md` § 4


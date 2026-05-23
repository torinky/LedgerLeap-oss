<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: responsive-text-icon-sizing
description: Sets readable, device-aware text and icon sizes for LedgerLeap UI. Use when text looks too small on desktop, when icons are fixed at one size, when size classes should respond to device or context, or when reviewing mixed-density pages.
compatibility: LedgerLeap (Mary UI, daisyUI v5, TailwindCSS v4, Livewire v3)
---

# responsive-text-icon-sizing

## Decision Tree

```text
Are text or icons being hard-coded to a tiny size?
├─ No → use another layout or content skill.
└─ Yes
   ├─ Is the content primary or scanned on desktop?
   │   ├─ Yes → increase legibility and let size grow with the screen.
   │   └─ No → keep it compact only if the element is truly secondary.
   ├─ Is the size expressed as one fixed value everywhere?
   │   ├─ Yes → replace it with responsive sizing or component defaults.
   │   └─ No → keep the responsive approach.
   ├─ Is it a badge, metadata chip, or dense table chrome?
   │   ├─ Yes → small sizes are acceptable only for that role.
   │   └─ No → keep the readable default or a responsive step.
   └─ Will the same size rule repeat across pages?
       ├─ Yes → record it in `docs/work/ui-ux/*` and promote if reusable.
       └─ No → keep the implementation local.
```

## Core Rules

- Prefer readable defaults for primary text.
- Avoid locking shared text to `text-xs` or similar tiny sizes unless it is clearly secondary.
- Icons that support readable text should scale with that text or with the surrounding component role.
- Avoid fixed icon sizes like `w-4 h-4` or `size-4` for primary actions, titles, or important metadata.
- Use compact sizes only for badges, subtle metadata, table chrome, or other clearly secondary roles.
- Prefer responsive size steps over one frozen value when the same UI appears on both mobile and desktop.
- Use Mary UI and daisyUI size variants before reaching for custom CSS.
- For required markers or helper icons in column titles or form headers, prefer a responsive icon or badge treatment rather than a one-size-fits-all tiny square.
- If a Mary icon already reads well at its component default size, leave it unforced instead of re-declaring a tiny width/height.
- For persistent footers and summary bars, keep the visible text minimal and let badge, icon, and tooltip carry the meaning together.
- If a footer badge needs explanation, move the explanation into a tooltip instead of adding a second text label next to it.
- Avoid text-only footer labels when the label is only there to describe a short state or count; prefer icon + badge or icon + tooltip instead.

## Practical layout guidance

- For desktop-facing content, allow text to grow at larger breakpoints instead of freezing it at a mobile-small size.
- For icons paired with text, keep the icon visually proportional to the text and the surrounding control.
- Prefer component defaults and theme tokens over ad hoc per-element overrides.
- When multiple items must line up, apply one shared responsive sizing pattern to the whole group.
- Do not use tiny defaults as a shortcut for density; make compactness deliberate and role-based.
- If a marker needs to stay visible in a dense table header, let its size breathe slightly at larger breakpoints instead of freezing it at one tiny value.
- In sticky action bars and other persistent footers, prefer a compact footer summary row with badge-first state, a small icon when it helps scanning, and tooltip text for the longer explanation.
- If the footer content is a status summary rather than a sentence, do not render it as a plain text line; treat it as metadata chrome.

## Evidence and references

- Repo evidence: `docs/work/ui-ux/2026-04-18_text-icon-size-responsiveness-note.md`
- Repo evidence: `docs/work/ui-ux/2026-04-18_design-workflow-reorganization-note.md`
- Repo evidence: `docs/work/ui-ux/2026-04-22_sticky-action-bar-footer-pattern.md`
- Related skills: `title-block`, `form-layout`, `search-header-responsive-layout`
- Official references that shaped this pattern:
  - daisyUI v5 component size scale and size tokens
  - Material Design 3 density and typography guidance
  - Apple HIG Dynamic Type guidance

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-18
- recheck_after: 90d
- recheck_trigger:
  - too many pages start locking text or icons to fixed tiny sizes again
  - daisyUI or Mary UI size scale guidance changes upstream
  - Material 3 or Apple Dynamic Type guidance changes upstream


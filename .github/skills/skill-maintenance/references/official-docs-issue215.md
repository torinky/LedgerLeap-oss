# Issue #215 official docs summary

- `status`: confirmed
- `last_confirmed_at`: 2026-05-21
- `recheck_after`: 90d
- `recheck_trigger`: Livewire / Alpine major release, `@livewireScriptConfig` guidance changes, Alpine.data invocation semantics change, or Tailwind line-clamp availability changes

## Livewire

- Installation: manual bundling must include `@livewireScriptConfig` in the layout.
  - Source: https://livewire.laravel.com/docs/installation
- Alpine integration: when bundling manually, import Livewire / Alpine from the ESM entry and call `Livewire.start()`.
  - Source: https://livewire.laravel.com/docs/alpine
- Troubleshooting: avoid duplicate Alpine instances.
  - Source: https://livewire.laravel.com/docs/troubleshooting

## Alpine.js

- `Alpine.data()` providers can be used directly from markup as functions, for example `x-data="dropdown(true)"`.
  - Source: https://alpinejs.dev/globals/alpine-data
- `init()` on an `Alpine.data()` component is evaluated automatically during initialization.
  - Source: https://alpinejs.dev/directives/init

## MDN / Tailwind

- `line-clamp` documents the fixed-line truncation pattern and its limited availability.
  - Source: https://developer.mozilla.org/en-US/docs/Web/CSS/line-clamp
- `-webkit-line-clamp` explains the legacy compatibility stack for line clamping.
  - Source: https://developer.mozilla.org/en-US/docs/Web/CSS/-webkit-line-clamp
- `mask-image` documents the visual fade approach used for overflow hints.
  - Source: https://developer.mozilla.org/en-US/docs/Web/CSS/mask-image
- `ResizeObserver` is a practical minimal-measurement primitive for height-based UI decisions.
  - Source: https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver
- `IntersectionObserver` is a practical lightweight trigger for deferred measurement.
  - Source: https://developer.mozilla.org/en-US/docs/Web/API/IntersectionObserver
- Tailwind line-clamp utilities are built in and do not require a separate plugin in recent versions.
  - Source: https://tailwindcss.com/docs/line-clamp

## Reusable lessons

1. Put the official upstream references in a dedicated references file instead of duplicating long URLs inside work notes.
2. Treat Livewire / Alpine browser regressions as a bootstrap mismatch until bundle, layout config, and plugin registration are confirmed.
3. Use the summary file as the durable evidence target, and keep the work note focused on the issue-specific decision trail.


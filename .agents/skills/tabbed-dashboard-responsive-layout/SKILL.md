---
name: tabbed-dashboard-responsive-layout
description: Designs and reviews tabbed summary dashboards and notification surfaces so counts, labels, and workflow state stay readable on wide screens. Use when a page uses Mary UI tabs, tab labels carry counts or state badges, or the controller and Livewire state must stay synchronized with the initial active tab.
compatibility: LedgerLeap (Livewire v3, Mary UI, daisyUI v5, TailwindCSS v4)
---

# tabbed-dashboard-responsive-layout

## Decision Tree

```text
Is this a tabbed dashboard, notification surface, or summary page with tabbed subviews?
├─ No → use the normal UI design guidance for the page.
└─ Yes
   ├─ Does Mary UI provide the tab surface?
   │   ├─ Yes → use `x-mary-tabs` / `x-mary-tab` instead of hand-built tab markup.
   │   └─ No → only build custom tabs if the component truly cannot express the surface.
   ├─ Does a tab need a count or compact state marker?
   │   ├─ Yes → keep the badge in the tab `label` slot and avoid duplicating the same badge in the page header.
   │   └─ No → keep the label text clean and compact.
   ├─ Does the page have a broad business-layout wrapper?
   │   ├─ Yes → widen the outer container first and keep internal groups centered with consistent spacing.
   │   └─ No → keep the current shell and do not spread controls toward both edges.
   ├─ Does the active tab depend on controller or Livewire state?
   │   ├─ Yes → verify the initial active tab, count events, and visible tab content together.
   │   └─ No → keep the tab surface local and avoid extra state wiring.
   └─ Is the tabbed surface repeating across more than one page?
       ├─ Yes → record the pattern in `docs/work/ui-ux/*` and keep the skill as the shared entry point.
       └─ No → keep the implementation local but still follow the same tab rules.
```

## Core Rules

- Prefer Mary UI tabs when they are available. Keep the tab component responsible for the tab chrome instead of rebuilding it with custom wrappers.
- Put tab-specific counts, badges, or status markers in the `label` slot when the tab owns the count.
- Do not show the same count badge in both the page header and the tab label unless the two counters represent different scopes.
- Keep the tab label readable at desktop widths; use Mary UI attributes and the `label` slot before adding extra markup.
- For wide administrative layouts, expand the outer container first and keep the inner content grid stable.
- Keep the controller's initial tab selection, any Livewire count/event updates, and the rendered tab contents in sync.
- If the page is also a workflow surface, verify the workflow state still lands on the intended tab after the layout change.
- Use translation keys for all visible tab labels and helper text.

## Review Checklist

- [ ] The tab surface uses `x-mary-tabs` / `x-mary-tab` when Mary UI supports it.
- [ ] Any tab count or badge appears in one clear place only.
- [ ] The page header does not repeat the same count marker as the tab label.
- [ ] The outer container width is adjusted before child spacing is scattered.
- [ ] The controller's initial active tab matches the intended default state.
- [ ] Livewire count events still update the visible tabs correctly.
- [ ] The relevant tab content is present and reachable after the layout change.
- [ ] The pattern is recorded in `docs/work/ui-ux/*` if it is reusable.

## Evidence

- `docs/work/ui-ux/2026-04-27_notifications_layout_responsive_retro.md`
- `resources/views/notifications/index.blade.php`
- `resources/views/livewire/notifications/notification-list.blade.php`
- `resources/views/livewire/common/activity-history-display.blade.php`
- `resources/views/livewire/workflow/pending-list.blade.php`
- `resources/views/livewire/workflow/other-related-tasks-list.blade.php`
- `app/Http/Controllers/NotificationController.php`
- `resources/views/livewire/attached-file/file-inspector.blade.php`

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-27
- recheck_after: 2026-07-27
- recheck_trigger: tab label/count placement changes, a tabbed dashboard starts duplicating badges, or initial tab / Livewire count sync changes


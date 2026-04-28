---
name: notification-banner-alert-surface-pattern
description: Designs reusable top-of-page notification surfaces such as announcement banners, alerts, and dismissible notices so level, actions, dismissal, and motion stay consistent across features. Use when a UI needs banner/alert semantics, a level-based palette, a right-aligned action cluster, or sticky offset handling.
compatibility: LedgerLeap (Mary UI, daisyUI v5, TailwindCSS v4, Livewire v3, Alpine.js)
---

# notification-banner-alert-surface-pattern

## Decision Tree

```text
Is this a top-of-page notification surface, announcement banner, or dismissible alert?
├─ No → use another layout/content skill.
└─ Yes
   ├─ Is the surface reusable across features?
   │   ├─ Yes → keep one skill-backed pattern and record repo evidence in docs/work.
   │   └─ No → keep the implementation local but still follow the surface rules.
   ├─ Does the surface need a semantic level?
   │   ├─ Yes → map level to alert semantics or palette, not to a new structural wrapper per level.
   │   └─ No → keep the chrome neutral and avoid extra visual branches.
   ├─ Does the surface need actions or dismissal?
   │   ├─ Yes → put CTA, metadata, and close controls in a single right-aligned action cluster.
   │   └─ No → keep the surface thin and avoid an unused action area.
   ├─ Does the surface sit above content or need to push layout?
   │   ├─ Yes → verify offset, z-index, and scroll occlusion with the sibling layout.
   │   └─ No → keep it static and avoid sticky behavior.
   ├─ Does the surface need motion?
   │   ├─ Yes → keep motion inside the surface and animate entry or exit, not the whole page.
   │   └─ No → keep it visually calm and accessible.
   └─ Is the pattern repeated in another feature?
       ├─ Yes → promote the shared element pattern here and keep feature-local wording in docs/work.
       └─ No → keep the implementation local and simple.
```

## Core Rules

- Prefer one semantic surface per notification, usually a single DaisyUI alert or card shell.
- Treat info, warning, and critical states as variants of the same surface, not as separate component families.
- Keep the message body on the left and the action cluster on the right when horizontal space allows.
- Put published time, CTA, and dismiss controls in the same action cluster when they belong to the same notification.
- Use clear buttons for actions; do not leave important follow-up links as plain text if they are intended to be acted on.
- Use `x-show` plus leave transitions for dismissible banners so the close gesture feels deliberate.
- Keep moving background effects inside the banner root so they do not leak beyond the surface or create a second card.
- If the surface is sticky or pushes content, verify the layout offset with sibling shells and preserve the local scroll experience.
- Use translation keys for all visible copy and labels.
- Prefer Mary UI buttons, badges, icons, and daisyUI semantic classes before custom wrappers.

## Review Checklist

- [ ] The surface uses one clear semantic shell rather than nested cards.
- [ ] The level is expressed as a variant, not as a separate layout branch.
- [ ] The action cluster is right-aligned and contains only the intended controls.
- [ ] Dismiss and entry or exit behavior use transitions rather than instant removal.
- [ ] Sticky surfaces preserve offset and z-index with sibling layouts.
- [ ] Background motion stays within the surface bounds.
- [ ] The pattern is recorded in `docs/work/ui-ux/*` if it recurs.

## Evidence and references

- Repo evidence: `docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_retrospective.md`
- Repo evidence: `docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md`
- Repo evidence: `resources/views/components/admin/announcement-banner.blade.php`
- Repo evidence: `resources/views/ui-previews/admin-announcement-banner.blade.php`
- Repo evidence: `resources/views/layouts/app.blade.php`
- Repo evidence: `resources/views/layouts/appWithDrawer.blade.php`
- Repo evidence: `resources/sass/app.scss`
- Repo evidence: `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- Related skill: `title-block`
- Related skill: `tabbed-dashboard-responsive-layout`

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-28
- recheck_after: 90d
- recheck_trigger:
  - a second feature reuses the same announcement banner shell
  - level variants require different structure instead of different semantics
  - sticky banner offset or dismiss animation behavior changes
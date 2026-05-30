---
name: mockup-driven-development
description: When a user cannot decide all design questions upfront, use mockups and prototypes to settle UI layout, behavior, and open questions before writing production code. Proven in admin-announcement-banner (#180-#185) and confidentiality-classification (#186-#190).
compatibility: "LedgerLeap (Livewire, Blade, MaryUI, daisyUI, Tailwind)"
---

# mockup-driven-development

## When to use

Use this skill when **any** of the following is true:
- The user says "I cannot decide everything at once"
- There are open UI/UX questions (z-index, responsive layout, sticky overlap, mobile occlusion)
- The user wants to "see the screen first" before committing to backend schema
- A new component's placement in a complex layout (e.g., IndexManager with drawer + sticky header) is uncertain

## Decision Tree

```text
New feature with UI surface?
├─ User can decide all questions upfront? → Proceed directly to implementation
├─ User wants to see the screen first? → Sprint 1 = Mockup Sprint
│   ├─ Place dummy form fields in existing Blade files
│   ├─ Place dummy component in target layout
│   ├─ Verify in browser (desktop + mobile)
│   ├─ Capture screenshots / DevTools readings as evidence
│   └─ Clean up dummies before Sprint 2
└─ Backend schema is uncertain too? → Parallel spike, not mockup
```

## Mockup Sprint Structure

### 1. Place dummies in existing Blade files
- Add dummy `<select>`, `<x-mary-choices>`, or placeholder divs **directly in the production Blade file**.
- Wrap with `@if(true) {{-- mockup --}} ... @endif` or use a temporary partial so cleanup is easy.
- Do NOT create database columns or migrations yet.

### 2. Place component mockup in target layout
- Create the component Blade file (e.g., `resources/views/components/ledger/confidentiality-stamp.blade.php`) with hard-coded sample data.
- Include it in the parent layout (e.g., `resources/views/livewire/ledger/index-manager.blade.php`).
- Check z-index, sticky overlap, and mobile occlusion immediately.

### 3. Browser verification checklist
- [ ] Desktop: component visibility, no overlap with sticky header / drawer
- [ ] Mobile (375px–768px): no content occlusion, touch targets usable
- [ ] DevTools: z-index stack reading recorded
- [ ] Responsive breakpoints: `lg:`, `md:` behavior confirmed

### 4. Capture evidence
- Screenshots saved to `docs/work/ui-ux/<feature>/` or attached to the Sprint issue comment.
- DevTools readings (z-index, computed styles) copied into the issue body.

### 5. Finalize decisions
- Update the pre-MVP checklist or decision log with "Confirmed via mockup" labels.
- Remove or disable dummy code before Sprint 2 starts.

## Cleanup Rule

- After decisions are finalized, **remove or disable all mockup-only data** from production Blade / PHP files.
- **Preferred cleanup pattern** (proven in #187 Sprint 1-4):
  - **Blade**: Comment out the dummy component call and leave a commented usage example showing how to wire it with dynamic data in Sprint 2.
  - **PHP (render methods)**: Comment out dummy option arrays with `TODO(#<issue>-Sprint2)` markers, return empty arrays `[]` instead, and keep the UI structure in Blade untouched.
  - **Rationale**: The UI layout (grid, spacing, z-index) is reused in production; only the data source changes. Full removal forces re-design in Sprint 2.
- If the component file itself is still needed, replace hard-coded data with props/bindings.
- Keep mockup screenshots in `docs/work/ui-ux/<feature>/` for future reference.

## Traps

- **Trap: leaving dummies in production**
  - Always grep for `mockup`, `dummy`, `TODO: remove` before committing Sprint 2.
- **Trap: mocking too far from real data**
  - Use realistic text lengths and option counts; short lorem ipsum hides overflow bugs.
- **Trap: skipping mobile check**
  - IndexManager drawer + sticky header + stamp is a high-risk overlap surface; always verify mobile.
- **Trap: decisions made without evidence links**
  - Every finalized decision must point to a screenshot or DevTools reading; otherwise it is not finalized.

## Evidence

- `docs/work/ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md` — Sprint 3-1 "画面シェル先行"
- `docs/work/core-features/confidentiality-classification/2026-05-01_retrospective.md` — Sprint 1 "モックアップと UI 設計確定"
- Issue #183 (Sprint 3) — Admin announcement UI shell first
- Issue #187 (Sprint 1) — Confidentiality stamp mockup first

## Related Skills

- `github-issue-workflow` — Epic/Sprint structure, sub-issue manual linkage
- `livewire-loading-ui` — Loading tiers when mockup transitions to real data fetching
- `design.instructions.md` — MaryUI/daisyUI component selection during mockup

## Freshness

- `status`: confirmed
- `last_confirmed_at`: 2026-05-01
- `recheck_after`: 90d
- `recheck_trigger`: a new feature where the user asks for UI confirmation before backend work, a mockup Sprint leaves production dummies behind, or a cross-session handover lacks a structured handover section in the Epic

## Cross-Sprint TODO Updates

When a mockup Sprint's `TODO(#<issue>-Sprint<N>)` markers carry over into the next Sprint:
1. **Update the TODO marker** to the next Sprint issue number (e.g., `TODO(#187-Sprint2)` → `TODO(#189-Sprint3)`).
2. **Update the comment text** to reference the newly implemented Service/Model instead of "DB から取得".
3. **Do NOT remove the TODO until the actual implementation is done** — the marker helps the next session find the exact lines that need wiring.

Evidence: `FolderForm.php` and `Edit.php` TODOs were updated from `#187-Sprint2` to `#189-Sprint3` after `ConfidentialityLevelService` was implemented in Sprint 2.

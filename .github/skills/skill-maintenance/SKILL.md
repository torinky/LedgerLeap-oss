---
name: skill-maintenance
description: Maintains LedgerLeap AI operating assets across prompts, skills, instructions, issue templates, runbooks, and AGENTS. Use when a bug fix, sprint, investigation, or user-requested retrospective proves a new reusable pattern, disproves an old rule, or reveals a missing workflow.
compatibility: LedgerLeap (.github/prompts, .github/skills, .github/instructions, .github/ISSUE_TEMPLATE, docs/runbooks, AGENTS.md)
---

# skill-maintenance

## Decision Tree

```text
A new learning was proven?
├─ Short repo-wide fact or invariant? → .github/copilot-instructions.md
├─ Path-specific codegen rule? → .github/instructions/*.instructions.md
├─ On-demand workflow or slash entry? → .github/prompts/*.prompt.md
├─ Reusable decision tree / diagnosis / capability? → .github/skills/<name>/SKILL.md
├─ Long examples or deep procedure? → .github/skills/<name>/references/*.md
├─ Missing structured human input? → .github/ISSUE_TEMPLATE/*
├─ Repeatable human/AI operating sequence? → docs/runbooks/*
└─ Agent-wide routing or discovery rule? → AGENTS.md
```

## Core Rules

- Treat `.github` as one system; never update only the skill and stop.
- Prompts are the primary JetBrains entrypoints; skills are reusable deep knowledge behind them.
- Keep `.github/copilot-instructions.md` short and repo-wide only.
- Keep one source of truth per rule; replace duplicates with links.
- If a prompt and skill cover the same domain, make them cross-reference each other.
- If the same investigation step stalls twice (for example CI status checks with unstable `gh` / shell / Python flows), promote the stable command recipe into the prompt, skill, and runbook.
- When a user supersedes a prior plan or scope, treat the new instruction as authoritative immediately; sync the plan, issue, and docs in the same pass and remove stale wording instead of keeping both versions active.
- When a user override changes the visible result, rewrite the affected branch from the nearest semantic anchor and keep only the authoritative version visible; do not leave the superseded label or checklist alongside the new one.
- When a Blade or Livewire edit breaks a narrow branch, prefer a clean branch rewrite over stacking fragment patches, then validate before widening the search.
- Keep feature-local UI choreography (for example, "close one drawer before opening another" or browser-event URL bridging) in `docs/work/*` until the pattern has been proven reusable in more than one feature; do not promote it into a reusable skill too early.
- For layout-sensitive UI work, record the approved breakpoint ladder and any scroll-occlusion threshold (for example, how much sticky content hides below the fold) in `docs/work/*` before considering it reusable.
- For layout-sensitive UI work, if a small set of cards should become narrower, keep them inside the same visual flow container and tune width utilities there; do not split them into a separate row unless the user explicitly asked for a new section.
- For static trees, accordions, and other hover-sensitive UI shells, do not assume `wire:ignore` or Livewire morphing is the whole cause of flicker; inspect tooltip, overflow, z-index, and pseudo-element layering next, and prefer static `title` / `aria-label` fallbacks over hover-only critical text.
- For the retire-review-brushup-commit loop, use `docs/runbooks/ai-asset-maintenance-playbook.md` as the canonical sequence: write `docs/work/*` first, then update the primary `.github` destination, sync neighbors, validate, run `/skill-maintenance`, and finish with `/git-commit`.
- When GitHub issue numbers and sprint labels can drift, treat the GitHub issue title/number as authoritative, add a visible `GitHub 追跡` mapping in the plan or issue body, and rewrite the affected branch immediately so the old numbering does not remain visible.
- If a task finishes, run a short retrospective before routing the result. Capture what went well, what caused rework, what should be promoted, what should be retired, what failed, and which dead ends were proven to be dead ends.
- For issue retrospectives, explicitly capture `良かったこと`, `悪かったこと`, and `上書き指示されたこと` before classifying the learning as technical or process-related.
- Keep local lessons in `docs/work/*` first; promote only durable guidance after the pattern is proven.
- Review learnings in two layers: (1) the process / approach (target selection, evidence order, hypothesis comparison, validation gate, handoff timing) and (2) the concrete technique / implementation detail (commands, config, UI changes, templates, wording, code pattern).
- For tenant-aware test suites, reapply the testing DB connection at the start of setup and avoid assuming the previous class left `mysql_testing` in the right database; see `docs/work/testing/2026-04-16_issue-149-retrospective.md`.
- For Livewire tenant recovery, load the model without tenant scoping first (for example `Ledger::withoutTenancy()->findOrFail(...)`), then restore tenancy from that model's `tenant_id` before rendering; see `docs/work/testing/2026-04-16_issue-149-retrospective.md`.
- When working in a mixed WSL / Mac environment, confirm the active directory and Git root with `pwd` and `git rev-parse --show-toplevel` before file edits or terminal actions; stop if the paths do not match the expected project root.
- When a task proves that an MCP / API tool description is carrying too much workflow guidance, route the workflow to `resources/ai/capabilities/*.yaml` or discovery docs, and keep the tool description contract-centered.
- Do not approve guidance removal until the receiver asset preserves equivalent client-facing information for generated skills, prompts, or bootstrap flows.
- Every durable learning must point to traceable evidence: link the repo proof in `docs/work/*` or the official source in `references/*.md` before treating it as reusable guidance.
- Record freshness metadata for doc-sensitive claims: `status`, `last_confirmed_at`, `recheck_after`, and a concrete `recheck_trigger`.

## Recent guardrail evidence

- [docs/work/ui-ux/2026-04-27_issue-176-retrospective-skill-brushup.md](../../../docs/work/ui-ux/2026-04-27_issue-176-retrospective-skill-brushup.md)
- `status`: confirmed
- `last_confirmed_at`: 2026-04-27
- `recheck_after`: 90d
- `recheck_trigger`: another issue-number drift, a repeated hover flicker in a static tree or accordion, or a new user override that supersedes the active branch

- [docs/work/ui-ux/navigation/2026-04-29_my-portal-notification-pending-layout-retrospective.md](../../../docs/work/ui-ux/navigation/2026-04-29_my-portal-notification-pending-layout-retrospective.md)
- `status`: confirmed
- `last_confirmed_at`: 2026-04-29
- `recheck_after`: 90d
- `recheck_trigger`: a user asks to rebalance stat cards, a masonry flow is broken by wrapper changes, or the same notification / pending-task pair is restyled again

- [docs/work/ui-ux/admin-announcement-banner/2026-04-29_admin_announcement_banner_validation_status_retrospective.md](../../../docs/work/ui-ux/admin-announcement-banner/2026-04-29_admin_announcement_banner_validation_status_retrospective.md)
- `status`: confirmed
- `last_confirmed_at`: 2026-04-29
- `recheck_after`: 90d
- `recheck_trigger`: banner validation rules or display-status semantics change again, or the retrospective→skill→commit flow stalls and needs a fresh runbook refresh

- [docs/work/ui-ux/admin-announcement-banner/2026-04-29_admin_announcement_edit_permission_retrospective.md](../../../docs/work/ui-ux/admin-announcement-banner/2026-04-29_admin_announcement_edit_permission_retrospective.md)
- `status`: confirmed
- `last_confirmed_at`: 2026-04-29
- `recheck_after`: 90d
- `recheck_trigger`: admin announcement permission scopes change again, issue-number drift reappears, or the retrospective structure needs another refresh

## Retrospective Gate

- For every finished task and every user-requested retrospective, answer the same five questions before deciding where the learning belongs.
- What went well and should be repeated?
- What caused rework, confusion, or over-scope?
- What was a local workaround versus a reusable pattern?
- What evidence should be preserved so the learning stays trustworthy?
- What failed, and what proof showed the dead end was wrong?
- If the same dead end appears twice, reclassify it before a third try instead of repeating the same investigation path.
- Classify each learning as `local`, `reusable`, or `retire` after splitting it into `technical elements` and `process elements`.
- If the learning is not yet reusable, keep it in `docs/work/*` and only promote it after the pattern is proven.

## Maintenance Loop

1. Collect newly proven facts, failure patterns, workarounds, and missing workflows.
2. Classify each item with the routing table.
3. Attach evidence for each durable claim: repo proof in `docs/work/*`, official source URLs in `references/*.md`, or both.
4. Record freshness metadata for claims that may drift over time.
5. Update the primary file first.
6. Sync neighbors: prompt ↔ skill ↔ instructions ↔ AGENTS ↔ issue template ↔ runbook.
7. Remove stale or conflicting text immediately, especially after a user overrides an earlier plan or scope.
8. Validate links, line budgets, discovery, slash entrypoints, evidence reachability, and overdue rechecks.
9. For tool-description slimming, confirm `tool = contract`, `capability = flow`, and `docs/work = rationale` after the update.

## Sync Neighbors Checklist

- [ ] Updated `copilot-instructions.md` only if the fact is global and short
- [ ] Updated prompt if the workflow should be user-invocable
- [ ] Updated skill if the pattern should auto-load or be reusable
- [ ] Updated `references/*.md` for long examples or detailed steps
- [ ] Updated `AGENTS.md` if routing/discovery changed
- [ ] Updated issue template or runbook if intake/ops flow changed
- [ ] Added a stable command recipe when repeated CI investigation friction was observed
- [ ] Confirmed that removed tool guidance still exists in capability / discovery assets when slimming descriptions
- [ ] Added evidence links for each durable claim
- [ ] Recorded `last_confirmed_at` and `recheck_after` for doc-sensitive guidance
- [ ] Captured breakpoint / scroll-occlusion notes when the UI decision depends on responsive transitions or sticky overlap

See [routing](./references/routing.md) for the authoritative destination matrix.
See [workflow](./references/workflow.md) for the quality gate and commit flow.
See [evidence-freshness](./references/evidence-freshness.md) for evidence fields and recheck defaults.
See [jetbrains-copilot-support](./references/jetbrains-copilot-support.md) for JetBrains/Copilot support notes.
See [skill-inventory](./references/skill-inventory.md) for inventory and anti-patterns.

---
name: skill-maintenance
description: Maintains LedgerLeap AI operating assets across prompts, skills, instructions, issue templates, runbooks, and AGENTS. Use when a bug fix, sprint, or investigation proves a new reusable pattern, disproves an old rule, or reveals a missing workflow.
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
- Keep feature-local UI choreography (for example, "close one drawer before opening another" or browser-event URL bridging) in `docs/work/*` until the pattern has been proven reusable in more than one feature; do not promote it into a reusable skill too early.
- For layout-sensitive UI work, record the approved breakpoint ladder and any scroll-occlusion threshold (for example, how much sticky content hides below the fold) in `docs/work/*` before considering it reusable.
- If any task finishes — bug fix, feature review, investigation, sprint, doc update, or user-requested retrospective — extract the learnings even if they are not yet reusable; keep feature-local notes in `docs/work/*` and only promote durable guidance when the pattern is proven.
- Before routing a finished task, do a short retrospective: what went well, what went poorly, what should be promoted, and what should be retired.
- Review learnings in two layers: (1) the process / approach (target selection, evidence order, hypothesis comparison, validation gate, handoff timing) and (2) the concrete technique / implementation detail (commands, config, UI changes, templates, wording, code pattern).
- For tenant-aware test suites, reapply the testing DB connection at the start of setup and avoid assuming the previous class left `mysql_testing` in the right database; see `docs/work/testing/2026-04-16_issue-149-retrospective.md`.
- For Livewire tenant recovery, load the model without tenant scoping first (for example `Ledger::withoutTenancy()->findOrFail(...)`), then restore tenancy from that model's `tenant_id` before rendering; see `docs/work/testing/2026-04-16_issue-149-retrospective.md`.
- If any task finishes — bug fix, feature review, investigation, sprint, doc update, or user-requested retrospective — extract the learnings even if they are not yet reusable; keep feature-local notes in `docs/work/*` and only promote durable guidance when the pattern is proven. Include failed operations and discarded approaches alongside the successful fix.
- Before routing a finished task, do a short retrospective: what went well, what went poorly, what should be promoted, what should be retired, and which dead ends were proven to be dead ends.
- When working in a mixed WSL / Mac environment, confirm the active directory and Git root with `pwd` and `git rev-parse --show-toplevel` before file edits or terminal actions; stop if the paths do not match the expected project root.
- Review learnings in two layers: (1) the process / approach (target selection, evidence order, hypothesis comparison, validation gate, handoff timing, failure triage) and (2) the concrete technique / implementation detail (commands, config, UI changes, templates, wording, code pattern).
- When a task proves that an MCP / API tool description is carrying too much workflow guidance, route the workflow to `resources/ai/capabilities/*.yaml` or discovery docs, and keep the tool description contract-centered.
- Do not approve guidance removal until the receiver asset preserves equivalent client-facing information for generated skills, prompts, or bootstrap flows.
- Every durable learning must point to traceable evidence: link the repo proof in `docs/work/*` or the official source in `references/*.md` before treating it as reusable guidance.
- Record freshness metadata for doc-sensitive claims: `status`, `last_confirmed_at`, `recheck_after`, and a concrete `recheck_trigger`.

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

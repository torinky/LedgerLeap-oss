<!-- Generated from .github - DO NOT EDIT MANUALLY -->
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

## Maintenance Loop

1. Collect newly proven facts, failure patterns, workarounds, and missing workflows.
2. Classify each item with the routing table.
3. Update the primary file first.
4. Sync neighbors: prompt ↔ skill ↔ instructions ↔ AGENTS ↔ issue template ↔ runbook.
5. Remove stale or conflicting text immediately.
6. Validate links, line budgets, discovery, and slash entrypoints.

## Sync Neighbors Checklist

- [ ] Updated `copilot-instructions.md` only if the fact is global and short
- [ ] Updated prompt if the workflow should be user-invocable
- [ ] Updated skill if the pattern should auto-load or be reusable
- [ ] Updated `references/*.md` for long examples or detailed steps
- [ ] Updated `AGENTS.md` if routing/discovery changed
- [ ] Updated issue template or runbook if intake/ops flow changed
- [ ] Added a stable command recipe when repeated CI investigation friction was observed

See [routing](./references/routing.md) for the authoritative destination matrix.
See [workflow](./references/workflow.md) for the quality gate and commit flow.
See [jetbrains-copilot-support](./references/jetbrains-copilot-support.md) for JetBrains/Copilot support notes.
See [skill-inventory](./references/skill-inventory.md) for inventory and anti-patterns.

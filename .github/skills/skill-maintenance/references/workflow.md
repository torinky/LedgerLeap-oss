# .github Maintenance Workflow

## Step 1 — Collect findings from the task

After a bug fix, sprint, or investigation, gather:

```
- What new fact, invariant, or failure pattern was proven?
- What existing prompt / skill / instruction was wrong or incomplete?
- What workaround is now stable enough to reuse?
- What input template or runbook was missing?
- What routing or discovery rule should agents know automatically?
```

## Step 2 — Route each finding to the primary destination

| Finding type | Primary destination |
|---|---|
| Global short invariant | `.github/copilot-instructions.md` |
| File/path-specific codegen rule | `.github/instructions/*.instructions.md` |
| User-invocable workflow | `.github/prompts/*.prompt.md` |
| Reusable decision tree / diagnosis | `.github/skills/<name>/SKILL.md` |
| Long examples / deep procedure | `.github/skills/<name>/references/*.md` |
| Missing structured intake | `.github/ISSUE_TEMPLATE/*` |
| Repeatable ops sequence | `docs/runbooks/*` |
| Agent-wide routing / discovery | `AGENTS.md` |

## Step 3 — Sync neighbors

For each primary update, check adjacent assets.

- Prompt changed → should a skill / runbook / issue template also change?
- Skill changed → should prompt / AGENTS / inventory / instructions change?
- Instruction changed → should `copilot-instructions.md` mention the rule class?
- Runbook or issue template changed → should the prompt link to it?
- Routing changed → update `AGENTS.md` and maintenance refs.
- Tool description was slimmed → did `resources/ai/capabilities/*.yaml` or discovery docs receive the removed client-facing workflow text?

## Step 4 — Attach evidence and freshness

Before a finding becomes durable guidance:

- add a reachable evidence link for the claim
- store repo proof in `docs/work/*` when the evidence is implementation-specific
- summarize official product evidence in `references/*.md` when the evidence comes from upstream docs
- record `status`, `last_confirmed_at`, and `recheck_after` for doc-sensitive claims
- if `today > last_confirmed_at + recheck_after`, refresh the source before you keep the rule

## Step 5 — Quality gate

- [ ] `copilot-instructions.md` stays short and repo-wide
- [ ] SKILL `name` matches directory name exactly
- [ ] SKILL `description` contains WHAT and WHEN
- [ ] Prompt is the primary slash entry for JetBrains-facing workflows
- [ ] Skill and prompt cross-link when they cover the same domain
- [ ] No duplicate rule remains in two places without an explicit reason
- [ ] All links resolve
- [ ] Inventory reflects new or removed skills
- [ ] For tool-description slimming, `tool = contract`, `capability = flow`, and `docs/work = rationale` are all true after the change
- [ ] Every durable claim has reachable evidence
- [ ] Official-doc-sensitive claims have `last_confirmed_at` and `recheck_after`
- [ ] No overdue recheck is being silently carried forward as confirmed guidance

## Step 6 — Commit flow

Use the `git-commit` prompt instead of ad-hoc shell quoting.

Suggested type:

```text
docs(.github): sync AI operating assets from proven learnings
```

Suggested body bullets:

```text
- updated routing / prompts / skills / instructions based on <finding>
- added or removed files to keep `.github` consistent
- refreshed agent discovery references
```

## Final checklist

- [ ] Primary destination chosen for every new learning
- [ ] Neighbor sync checked across prompt / skill / instructions / AGENTS / issue template / runbook
- [ ] Stale text removed, not merely appended around
- [ ] New recurring workflow promoted to prompt or skill
- [ ] Evidence and freshness recorded for reusable claims
- [ ] Commit prepared with `/git-commit`

See [routing](./routing.md) for the authoritative matrix.
See [evidence-freshness](./evidence-freshness.md) for the evidence template and recheck defaults.
See [jetbrains-copilot-support](./jetbrains-copilot-support.md) for support notes.

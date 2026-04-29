# .github Maintenance Workflow

## Step 1 — Collect findings from the task

After a bug fix, sprint, or investigation, gather:

```
- What new fact, invariant, or failure pattern was proven?
- What existing prompt / skill / instruction was wrong or incomplete?
- What workaround is now stable enough to reuse?
- What input template or runbook was missing?
- What routing or discovery rule should agents know automatically?
- What failed operation or discarded approach exposed the mismatch?
- What expectation was wrong: implementation, test config, or workflow?
```

## Step 1.5 — Run a short retrospective

Before deciding where a learning belongs, record the answer to each of these:

- What went well and should be repeated?
- What caused rework, confusion, or over-scope?
- What was a local workaround versus a reusable pattern?
- What evidence should be preserved so the learning stays trustworthy?
- What did we try that failed, and what proof showed it was the wrong path?
- If the same dead end appeared twice, what should be reclassified before a third try?

Write the findings first as `良かったこと`, `悪かったこと`, and `上書き指示されたこと`, then split each one into `技術要素` and `作業の進め方` before choosing a destination.

Use the answer to decide whether the item stays in `docs/work/*` or graduates into `.github` assets.

When the failure is due to a test / config mismatch, capture the expected condition, the actual runtime condition, and the smallest opt-in needed before promoting the learning.

When a Blade change produces a syntax or rendering error that does not match the visible source, clear compiled views before widening the diagnosis; stale cache can make the wrong branch look guilty.

Canonical sequence for a finished task: `docs/work/*` retrospective → primary `.github` destination update → neighbor sync → validation → `/skill-maintenance` → `/git-commit`. See `docs/runbooks/ai-asset-maintenance-playbook.md` for the exact steps.

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

## Handling user overrides

When the user supersedes an earlier plan, treat the override as the new authoritative scope rather than a side note.

- Update the issue body/comments and the session plan in the same pass.
- Remove or rewrite stale checklist items instead of leaving both versions visible.
- Restate the new scope in the next progress note so the user can confirm what changed.
- Keep the old wording only if it is explicitly marked as superseded.
- For a user override, rewrite the affected branch so the new instruction is the only visible source of truth.

Evidence anchor: issue `#135` scope was repeatedly re-centered on 2026-04-12, and the stable result came from writing the new scope back into the issue / plan immediately (`https://github.com/torinky/LedgerLeap/issues/135#issuecomment-4230914437`).

## Step 5 — Quality gate

- [ ] `copilot-instructions.md` stays short and repo-wide
- [ ] SKILL `name` matches directory name exactly
- [ ] SKILL `description` contains WHAT and WHEN
- [ ] Prompt is the primary slash entry for JetBrains-facing workflows
- [ ] Skill and prompt cross-link when they cover the same domain
- [ ] No duplicate rule remains in two places without an explicit reason
- [ ] Failed operations and discarded approaches were captured alongside successful fixes
- [ ] Issue completion retained both completion evidence and the rewritten body, not comments alone
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

# .github File Routing Matrix

This is the authoritative destination map for new learnings.

## Core Rule

Choose **one primary destination** first, then sync adjacent files only when they improve discovery or prevent drift.

## Destination Table

| If the learning is... | Primary destination | Why |
|---|---|---|
| A short repo-wide invariant | `.github/copilot-instructions.md` | Always-on context; must stay compact |
| A rule that only applies to certain files or paths | `.github/instructions/*.instructions.md` | Auto-applied by path |
| A task users or agents should run on demand | `.github/prompts/*.prompt.md` | Slash entrypoint and repeatable workflow |
| A reusable capability, diagnosis tree, or decision table | `.github/skills/<name>/SKILL.md` | Reusable deep knowledge |
| A long procedure, examples, sample outputs, or edge cases | `.github/skills/<name>/references/*.md` | Offloads bulk from SKILL body |
| A missing input form for humans | `.github/ISSUE_TEMPLATE/*` | Standardizes intake quality |
| A repeatable human/AI operational sequence | `docs/runbooks/*` | Human-readable procedure document |
| A repository-wide agent routing / discovery rule | `AGENTS.md` | Agent-optimized meta instructions |

## Neighbor Sync Rules

### Update prompt neighbors when...
- a skill was added but there is no obvious slash entrypoint
- a runbook or issue template became part of the normal workflow
- JetBrains users need a first-class entrypoint

### Update skill neighbors when...
- a prompt now relies on a recurring decision tree
- deep examples are growing inside the prompt
- a rule should be reusable across multiple prompts

### Update instructions neighbors when...
- the learning is path-specific and should auto-apply during file edits
- the rule is stable enough to influence code generation without manual invocation

### Update `copilot-instructions.md` only when...
- the fact is short
- it is broadly applicable to most requests
- it is worth spending always-on context for it

Do **not** put long workflows, examples, or task-specific procedures there.

## Red Flags

- The same rule is copied into prompt, skill, and instructions with no source of truth.
- A new skill exists but no prompt or AGENTS path helps users discover it.
- `copilot-instructions.md` is absorbing long operational content.
- A runbook changed but the prompt still points to the old flow.
- A prompt and skill drift apart on terminology or steps.

## Recommended Maintenance Sequence

1. Record the new learning.
2. Pick the primary destination from the table above.
3. Update the destination file.
4. Check prompt / skill / AGENTS / issue template / runbook neighbors.
5. Remove stale duplicates.
6. Validate links and discovery.


# End-of-Sprint Skill Maintenance Workflow

## Step 1 — Collect findings from the sprint

After completing a sprint, gather:

```
- What errors/failures were encountered and solved?
- What new patterns were proven to work?
- What existing skill instructions were wrong or incomplete?
- What workarounds are likely to recur in future sprints?
```

## Step 2 — Triage each finding

| Finding type | Action |
|---|---|
| New error pattern with fix | Add to relevant skill's `references/fix-patterns.md` or similar |
| New code pattern (≤3 lines) | Add inline to SKILL.md decision tree or table |
| New code pattern (code example needed) | Add to `references/*.md`, link from SKILL.md |
| Existing instruction was wrong | Fix in place; note the issue # that proved it |
| New recurring workflow (≥2 occurrences) | Create new skill with `skill-maintenance` |
| copilot-instructions.md trigger missing | Add row to Skills table |

## Step 3 — Quality gate before committing

For each modified SKILL.md, verify:

- [ ] `name` matches directory name exactly (lowercase, hyphens only)
- [ ] `description` is third-person voice and includes "Use when <trigger>"
- [ ] `compatibility` field present
- [ ] Body ≤80 lines
- [ ] No `python3 -c` or heredoc in git-commit skill (use `create_file` + script)
- [ ] No time-stamped instructions ("as of 2026-02") — use "since Issue #N"
- [ ] Each `references/*.md` file ≤120 lines and covers one topic
- [ ] All reference links in SKILL.md are one level deep (`references/foo.md`)
- [ ] copilot-instructions.md ≤50 lines

## Step 4 — Commit with git-commit skill

Use `skill-maintenance` skill for the commit type guidance:

```
docs(.github/skills): <what changed and why>

Updated skills based on Sprint N findings:
- skill-name: <what was added/fixed>
- skill-name: <what was added/fixed>

Closes #N  (if triggered by an issue)
```

---

## Anti-Patterns to Avoid

| Anti-pattern | Problem | Fix |
|---|---|---|
| Instructions in Japanese imperative form ("〜すること") | Agent interprets as user-to-agent command, not system fact | Rewrite as third-person English fact |
| All details in SKILL.md body | Every token loaded on activation regardless of relevance | Move code examples to `references/` |
| `python3 -c` for commit messages | Shell encoding corrupts Japanese/special chars | `create_file` tool → `python3 script.py` |
| Nested reference chains (A→B→C) | Agent uses `head -100` preview and misses content | Keep all refs one level from SKILL.md |
| `git add -A` before commit | Stages `coverage-*/`, `wnjpn.db`, `.playwright-mcp/` | Explicit `git add <file>` only |
| Duplicate patterns across skills | Maintenance burden, inconsistency | Single source of truth; cross-link |

---

## Reference: Progressive Disclosure Architecture

```
Agent startup (always loaded):
  copilot-instructions.md  ← ≤50 lines, constraints + skill trigger table

On task match (loaded when triggered):
  SKILL.md body            ← ≤80 lines, decision tree + comparison table + links

On demand (loaded only when referenced):
  references/*.md          ← ≤120 lines each, code examples + detailed procedures
```

Token budget intuition:
- SKILL.md body at 80 lines ≈ ~1,000 tokens
- 5 skills × 80 lines = ~5,000 tokens (acceptable for active context)
- references/ file at 120 lines ≈ ~1,500 tokens (only loaded if needed)

---

## LedgerLeap Skill Inventory

| Skill | Trigger | Key references |
|---|---|---|
| `git-commit` | any git commit | `conventional-commits.md` |
| `github-issue-workflow` | issue read/write/sprint | `comment-format.md` |
| `ci-failure-investigation` | CI failure / timeout | `fix-patterns.md` |
| `database-migrations-test-optimization` | Mroonga tests / slow CI | `trait-usage.md` |
| `test-external-dependency-isolation` | Ledger/AttachedFile tests | `queue-fake-patterns.md` |
| `skill-maintenance` | end of sprint / new pattern | `workflow.md` (this file) |


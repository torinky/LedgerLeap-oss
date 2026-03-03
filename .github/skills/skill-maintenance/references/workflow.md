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
- [ ] **No subdirectory nesting** — `skills/category/skill-name/` violates the spec; `name` must equal the directory name. Use the `Category` column in the Inventory table for grouping instead.
- [ ] `description` is third-person voice and includes "Use when <trigger>"
- [ ] `compatibility` field present
- [ ] Body ≤80 lines
- [ ] No `python3 -c` or heredoc in git-commit skill (use `create_file` + script)
- [ ] No time-stamped instructions ("as of 2026-02") — use "since Issue #N"
- [ ] Each `references/*.md` file ≤120 lines and covers one topic
- [ ] All reference links in SKILL.md are one level deep (`references/foo.md`)
- [ ] copilot-instructions.md ≤50 lines

## SKILL.md Body Rules (complete)

| Rule | Rationale |
|---|---|
| ≤80 lines | Loaded entirely on activation — every token competes with context |
| Decision tree first | Agent needs to reach the right branch fast |
| Comparison table second | Concise alternative enumeration |
| Reference links last | `See [references/foo.md](references/foo.md) for details` |
| No time-sensitive info | Use "since Issue #N" instead of "as of 2026-02" |
| English preferred | Agent processes English constraints with highest accuracy |
| Checklist at bottom | Compact format; max 6–8 items |

> The SKILL.md body shows a condensed subset of this table. This is the authoritative version.

## Step 4 — Commit with git-commit skill

Commit type: `docs(.github/skills): <what changed and why>`.

```
docs(.github/skills): <what changed and why>

Updated skills based on Sprint N findings:
- skill-name: <what was added/fixed>
- skill-name: <what was added/fixed>

Closes #N  (if triggered by an issue)
```

**Use `bash -c` for all git operations** (see git-commit skill):

```bash
bash -c "cd /path && git add .github/skills .github/copilot-instructions.md && git status --short"
bash -c "cd /path && git commit -m 'docs(.github/skills): Sprint N skill updates

- skill-name: added X pattern
- skill-name: fixed Y

Refs #N'"
bash -c "cd /path && git push origin <branch>"
```

---

## Sprint Completion Checklist

Execute in this order — do not skip steps:

- [ ] **Plan doc**: Update sprint checkbox in `docs/work/.../*plan.md` (mark tasks ✅)
- [ ] **Issue**: Update GitHub issue body with evidence (commit hash or test output)
- [ ] **Skill maintenance**: Check if new patterns emerged → update skills (this workflow)
- [ ] **Commit**: `bash -c "cd /path && git add .github/skills .github/copilot-instructions.md"`
- [ ] **Commit**: `bash -c "cd /path && git commit -m 'docs(.github/skills): ...'"`
- [ ] **Push**: `bash -c "cd /path && git push origin <branch>"`

**Critical**: All git commands after any `sail` command in the session MUST use `bash -c`.
If `git status` shows nothing staged after `git add`, switch to `bash -c` immediately.

See [skill-inventory.md](skill-inventory.md) for Anti-Patterns, Skill Inventory, and Reference Docs.

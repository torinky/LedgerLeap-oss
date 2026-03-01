---
name: skill-maintenance
description: Creates or updates LedgerLeap Agent Skills (SKILL.md files). Use after completing any sprint, bug fix, or investigation when new patterns should be captured, or when creating a new skill from scratch.
compatibility: LedgerLeap (.github/skills/ directory, agentskills.io open standard)
---

# skill-maintenance

## When to Run

After every sprint/task completion, check:
- New error pattern encountered and solved → add to appropriate `references/*.md`
- New workaround proven in practice → update relevant SKILL.md decision tree
- Existing instruction found wrong or incomplete → fix it
- New recurring workflow emerged → consider a new skill

**Do not skip this step.** Stale skills mislead future work more than no skills at all.

## Structure Rules (agentskills.io standard)

```
.github/skills/<skill-name>/
├── SKILL.md          ← required; body ≤80 lines; decision tables + reference links
└── references/
    └── *.md          ← loaded on demand; each file ≤120 lines; one topic per file
```

### SKILL.md frontmatter (required fields)

```yaml
---
name: skill-name
description: <what it does>. Use when <specific trigger conditions>.
compatibility: LedgerLeap (...)
---
```

**description rules**: Third-person voice. Include WHAT and WHEN. Include class/error names.

## SKILL.md Body Rules

| Rule | Rationale |
|---|---|
| ≤80 lines | Loaded entirely on activation — every token competes with context |
| Decision tree first | Agent needs to reach the right branch fast |
| Comparison table second | Concise alternative enumeration |
| Reference links last | `See [references/foo.md](references/foo.md) for details` |
| No time-sensitive info | Use "since Issue #N" instead of "as of 2026-02" |
| English preferred | Agent processes English constraints with highest accuracy |
| Checklist at bottom | Compact format; max 6–8 items |

## Adding to an Existing Skill

1. Read the current SKILL.md body — identify which section the new knowledge belongs to
2. If it fits in ≤3 lines → add inline to SKILL.md
3. If it requires code examples or multi-step procedure → add to `references/*.md`
4. If adding to references, add a one-line link in SKILL.md pointing to it
5. Keep SKILL.md body under 80 lines — move overflow to references

## Creating a New Skill

1. Identify the recurring workflow (at least 2 occurrences justify a skill)
2. Name it: `verb-noun` or `noun-noun` pattern, lowercase hyphenated
3. Write description: run the "does X / Use when Y" test
4. Write the body: start with decision tree or classification table
5. Move all code examples to `references/`
6. Add trigger row to `copilot-instructions.md` Skills table

## Updating copilot-instructions.md

`copilot-instructions.md` is sent with **every** request — keep it ≤50 lines.

- Add skill trigger only if it cannot be auto-discovered from description alone
- Remove triggers for deleted/merged skills immediately
- Never add implementation details — they belong in skills

## Full Workflow

See [references/workflow.md](references/workflow.md) for the complete
end-of-sprint checklist and quality gate table.

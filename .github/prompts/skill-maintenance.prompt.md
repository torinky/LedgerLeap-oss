---
description: Create or update LedgerLeap Agent Skills (SKILL.md files). Run after every sprint or bug fix to capture new patterns.
---

# skill-maintenance

## When to Run

After every sprint/task completion, check:
- New error pattern encountered and solved → add to `references/*.md`
- New workaround proven in practice → update SKILL.md decision tree
- Existing instruction found wrong → fix it
- New recurring workflow emerged → consider a new skill

## Skill Structure (agentskills.io standard)

```
.github/skills/<skill-name>/
├── SKILL.md          ← required; body ≤80 lines
└── references/
    └── *.md          ← loaded on demand; each ≤120 lines; one topic per file
```

### SKILL.md Frontmatter

```yaml
---
name: skill-name
description: <what it does>. Use when <specific trigger conditions>.
compatibility: LedgerLeap (...)
---
```

## Adding to Existing Skill

1. Read current SKILL.md — identify which section the new knowledge belongs to
2. ≤3 lines → add inline to SKILL.md
3. Requires code examples → add to `references/*.md`
4. Add one-line link in SKILL.md pointing to new reference
5. Keep SKILL.md body ≤80 lines

## Creating a New Skill

1. Identify recurring workflow (≥2 occurrences justify a skill)
2. Name: `verb-noun` or `noun-noun`, lowercase hyphenated
   - No subdirectories — `name` must equal directory name
3. Write description: "does X / Use when Y"
4. Body: start with decision tree or classification table
5. Move code examples to `references/`

## Updating instructions/ and prompts/

After updating a skill, also update the corresponding files:
- `skills/<name>/SKILL.md` changes → reflect key rules in `.github/instructions/*.instructions.md` or `.github/prompts/<name>.prompt.md`
- `copilot-instructions.md` Skills table: add trigger row only if not auto-discoverable

**`copilot-instructions.md` target: ≤50 lines total.** Never add implementation details there.

## End-of-Sprint Checklist

- [ ] All new patterns captured in SKILL.md or references/
- [ ] `copilot-instructions.md` Skills table updated
- [ ] Corresponding `instructions/` or `prompts/` file updated
- [ ] SKILL.md body still ≤80 lines (move overflow to references/)
- [ ] Post sprint summary comment to relevant GitHub issue

See `.github/skills/skill-maintenance/references/workflow.md` for quality gate details.


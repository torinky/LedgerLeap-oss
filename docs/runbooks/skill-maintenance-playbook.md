# Skill Maintenance Playbook

## Purpose

Keep LedgerLeap `.github/skills/` lean, discoverable, and context-efficient.
Skills that are too large waste context window space and degrade LLM performance.
This playbook defines when and how to split, merge, or retire skills.

---

## Size Limits (Hard Guidelines)

| File | Target Max | Absolute Max | Rationale |
|---|---|---|---|
| `SKILL.md` | **80–120 lines** | 150 lines | Loaded into context when matched; every line competes with code |
| `references/*.md` | 300 lines | 400 lines | Loaded on demand via explicit `See [references/...]` links |
| `scripts/*` | n/a | n/a | External executables; size is irrelevant to context |

> **Why 120 lines?**  
> Claude Code best practices state: *"Bloated CLAUDE.md files cause Claude to ignore your actual instructions!"*  
> The same applies to skills. When a skill exceeds ~120 lines, the decision tree and checklist get buried, and the LLM starts ignoring key constraints.

---

## When to Split

Run this check after every sprint, bug fix, or retrospective that updates a skill:

```
SKILL.md line count > 120?
├─ YES → Is there a natural chapter boundary?
│   ├─ YES → Extract into references/*.md, keep only the decision tree + checklist in SKILL.md
│   └─ NO  → Can a section be merged into copilot-instructions.md or an instructions file?
│       ├─ YES → Move the invariant there; link from skill
│       └─ NO  → Split into two sibling skills with narrower scope
└─ NO  → OK; keep monitoring
```

### Natural Chapter Boundaries (examples)

| Pattern | Extract to |
|---|---|
| Long code example (>20 lines) | `references/<topic>-examples.md` |
| Performance benchmark table | `references/performance-impact.md` |
| Step-by-step terminal commands | `references/command-recipes.md` |
| Deep dive into one API/class | `references/<api>-deep-dive.md` |
| Historical retrospective list | `references/recent-guardrails.md` or `docs/work/*` |
| Freshness metadata for many items | Collapse into one link per item; move detail to `docs/work/*` |

---

## Splitting Procedure

1. **Read the skill** and identify the boundary.
2. **Create the reference file** under `references/<name>.md`.
3. **Trim SKILL.md** to:
   - Front matter (`name`, `description`, `compatibility`)
   - Decision tree (the main routing logic)
   - Core rules / key constraints (the "must not forget" items)
   - Checklist
   - Links to references
4. **Update all cross-links**:
   - Prompts that mention the skill
   - Other skills that link to it
   - `AGENTS.md` routing table
   - `copilot-instructions.md` if the skill name appears there
5. **Validate**:
   - `SKILL.md` ≤ 120 lines
   - All `See [references/...]` links resolve
   - No duplicate text between SKILL.md and references
6. **Commit** with message: `maintain(skills): split <skill> to keep SKILL.md under 120 lines`

---

## Merging Procedure

When two skills cover nearly identical territory:

1. Choose the **more general** skill as the survivor.
2. Move the **narrower** skill's unique content into the survivor's `references/`.
3. Update the survivor's `description` to cover both scopes.
4. Delete the narrower `SKILL.md`.
5. Add a redirect note in the narrower skill's old path if other docs link to it.

---

## Retirement Procedure

When a skill is no longer accurate:

1. Move its content to `docs/work/<area>/` with a date prefix if it may be useful historically.
2. Delete the skill directory.
3. Remove references from `AGENTS.md`, prompts, and other skills.
4. Run `grep -r "skill-name" .github/ docs/` to find broken links.

---

## Freshness Rules for Split Skills

- Freshness metadata (`status`, `last_confirmed_at`, `recheck_after`) belongs in `SKILL.md` **only** for the skill as a whole.
- Per-item freshness (e.g., each guardrail entry) moves to `references/*.md` or `docs/work/*`.
- If a reference file grows stale, update or delete it; do not accumulate outdated guardrails in the skill.

---

## Context-Efficiency Checklist

- [ ] Every line in `SKILL.md` answers: *"Would removing this cause the LLM to make a mistake?"*
- [ ] No tutorial or explanatory prose in `SKILL.md`; link to references instead
- [ ] Decision trees use ASCII art or Mermaid; they compress better than bullet paragraphs
- [ ] Checklists are the last thing in the file so they survive context trimming
- [ ] Links use relative paths that resolve from the skill directory
- [ ] No duplicated rules between skill and `copilot-instructions.md`

---

## Anti-Patterns to Avoid

| Anti-Pattern | Why It Hurts | Fix |
|---|---|---|
| "Everything in SKILL.md" | Context bloat; LLM ignores the checklist | Split into references |
| "Copy-paste from skill to prompt" | Two sources of truth drift apart | Prompt links to skill; skill is the source of truth |
| "Historical evidence in SKILL.md" | Old retrospectives compete with current rules | Move to `docs/work/*`; link from skill |
| "One skill per file changed" | Too many tiny skills; discovery cost rises | Merge related skills; use `references/` for detail |
| "No references directory" | SKILL.md becomes the only place for detail | Create `references/` early, before the skill hits 80 lines |

---

## Related

- `/.github/skills/skill-maintenance/SKILL.md` — the skill that runs this playbook
- `/AGENTS.md` — repo-wide routing rules
- `/.github/copilot-instructions.md` — global invariants (keep short!)
- `/.github/instructions/ai-assets.instructions.md` — asset editing rules

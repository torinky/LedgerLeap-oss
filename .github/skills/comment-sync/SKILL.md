---
name: comment-sync
description: Audit and update PHPDoc comments on source code referenced by a public doc. For each class, method, and parameter in the doc's source anchors, verify that comments match the actual code signatures. One execution = one doc's source anchor set.
compatibility: LedgerLeap (`docs/`, `app/`, `.github/skills/comment-sync/references/phpdoc-inspection-checklist.md`)
---

# comment-sync

## When to use

- A doc-creation-sprint or doc-publication-audit just created/rewrote a doc and the packet's `comment_sync_policy` is `required` or `optional`.
- You found undocumented classes/methods that a public doc describes directly.
- You want to batch-audit one doc's source anchors without a repo-wide sweep.

## Routing Boundary

- This skill **audits + updates** PHPDoc for one packet's source anchor set. It does not discover or write docs.
- Use inside `doc-creation-sprint` (step 14) or as a standalone `comment-sync` lane.
- For repo-wide sweeping or template-level PHPDoc rules, use the playbook's source comment policy.

## Decision Tree

```text
comment_sync_policy = required or optional?
├─ required → run the full checklist on every class/method in source_anchor.
│   └─ First verify: is every public method signature correctly reflected in its PHPDoc?
│       └─ No → fix the stale comment.
│       └─ Yes but missing → add the minimum PHPDoc.
│       └─ Yes and correct → skip.
└─ optional → same as required, but only apply to undocumented classes.
    └─ Skip any class that already has a complete class-level summary + method docs.
```

## Phase 1 — Scope

1. Read the packet handoff or acceptance record to extract `source_anchor` and `comment_anchors`.
2. Resolve each anchor to a concrete file path and symbol name (class, interface, trait, or public method).
3. Exclude: `private`/`protected` helpers, boilerplate accessors (getters/setters with no logic), migration-only code, and internal-only DTOs not described in the doc.

## Phase 2 — Audit and Update

4. For each source file, use `get_symbols_overview` to list top-level classes.
5. **Class-level**: if the class has no PHPDoc, add one with a short summary and `@see` references. If it has one but the summary is stale (does not match current behavior), update it.
6. **Public methods**: for each public method in `comment_anchors`, inspect the current signature (parameters + return type) and compare against any existing PHPDoc:
   - `@param` tags: verify **count, name, and type** match. Remove stale tags, add missing ones. Use `@param` for complex inputs only — skip trivial scalars.
   - `@return` tags: verify the declared return type (or actual return if untyped). Add if non-void and missing.
   - `@throws` tags: add only for **observable failure modes** (exceptions explicitly thrown, not generic runtime errors).
7. DocBlock order: `summary → description → @param → @return → @throws → @see`.

See `references/phpdoc-inspection-checklist.md` for the step-by-step audit workflow.

## Phase 3 — Verification

8. After writing, read back each changed file and confirm the comment matches the code signature directly below it.
9. Run PhpStorm inspections on each changed file; fix any warnings introduced by the new comments.
10. Do NOT reformat the whole file — only the DocBlock areas changed.

## Output

- A list of changed files, symbol names, and what was added or fixed.
- If any class/method was skipped (already documented), note it explicitly.
- Record the sync result in the packet acceptance table.

## Evidence

- `docs/runbooks/doc-publication-packet-playbook.md` — source comment policy and PHPDoc minimum rule
- `.github/skills/doc-creation-sprint/SKILL.md` — the parent skill that invokes this
- `references/phpdoc-inspection-checklist.md` — detailed per-class audit workflow

## Freshness

- status: confirmed-repo
- last_confirmed_at: 2026-05-29
- recheck_after: 180d
- recheck_trigger: PHPDoc minimum rule changes, phpDocumentor compatibility policy changes, or a new doc area introduces different comment conventions

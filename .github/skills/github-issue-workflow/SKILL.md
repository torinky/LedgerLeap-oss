---
name: github-issue-workflow
description: Manages GitHub issues for LedgerLeap — drafting, reading, commenting, updating checklists, sprint planning, and coverage reporting. Use when performing any GitHub issue operation.
compatibility: "LedgerLeap (owner: torinky, repo: LedgerLeap)"
---

# github-issue-workflow

## Repository

`owner: torinky` / `repo: LedgerLeap` — pass to every GitHub tool call.

## Tool Priority

1. MCP issue tools first when available.
2. `gh` when MCP is unavailable or body needs full canonical-file sync.
3. Avoid mixing both in the same step unless required.

## Issue Read Flow

```
1. issue_read(get)              — title, body, labels, assignees
2. issue_read(get_comments)     — history, evidence, sprint reports
3. (opt) list_issues / search_issues — parent/child/related
4. (opt) pull_request_read      — linked PRs
```

## Issue Update Flow

```
1. issue_read(get) + get_comments  — fetch current state first
2. add_issue_comment               — progress reports, sprint summaries
3. issue_write(update, body)       — checklist updates (replace entire body)
4. issue_write(update, closed)     — when all acceptance criteria met
```

## Body Sync Rule

- For substantive rewrites, keep a canonical markdown file locally and update GitHub with full-body replacement.
- After `gh issue edit --body-file ...`, immediately re-fetch and verify published content matches the canonical file.
- If stale sections remain, rewrite the full body again instead of repairing with comments.
- See [GitHub Issue Body Sync Playbook](/docs/runbooks/github-issue-body-sync-playbook.md).

## Numbering Guardrail

- Confirm actual GitHub issue number and title before writing docs or checklists; sprint labels in filenames are not authoritative.
- Add/update a visible `GitHub 追跡` block when sprint numbers map to different issue numbers.
- If user overrides scope or numbering, rewrite affected issue body and companion docs in the same pass.

## Issue Drafting Flow

1. **Choose the correct issue form first**
   - `bug_report.yml` for bugs, regressions, CI failures
   - `issue_request.yml` for improvements, features, investigations, docs, refactors
2. Keep wording neutral; describe problem and goal without presuming the fix.
3. Keep form fields aligned with the canonical template body order.
4. Use sprint breakdowns only when work spans multiple steps.
5. Attach concrete evidence: file paths, test names, coverage, screenshots, logs, commit refs.
6. Add directly checkable acceptance criteria.
7. Before creating a new issue, confirm whether an existing issue already covers the same work.
8. Keep issue body in Japanese unless user explicitly asks otherwise.
9. If the owner must click through GitHub manually, add a dedicated `Owner manual steps` section to the canonical issue body with the UI path, required permission, expected result, and confirmation evidence.

## Checklist Update Rule

**Always update the body directly** — do not leave checkboxes unchecked and only post a comment.

```markdown
- [x] Task completed
    - Evidence: `ClassName`: 0% → **75%** ✅  (commit a1b2c3d)
```

## Coverage Evaluation

1. Read `coverage/index.html` via MCP markdown conversion
2. Compare against acceptance criteria in issue body
3. Post completion table as comment; close issue if all criteria met

**Targets** (Epic #66): Overall ≥65%, Services ≥80%, Models ≥60%, Livewire ≥65%, Filament ≥30%

## Sub-Issues and Epic/Sprint Hierarchy

### Sub-Issues
- GitHub Sub-issues **cannot** be created or linked via API, MCP, or `gh` CLI.
- After creating an Epic and its Sprint issues, **manually open the Epic in Web UI** and add each Sprint as a sub-issue.
- Always include a visible `GitHub 追跡` block in the Epic body.

```markdown
## GitHub 追跡
- Epic: #186（本 Issue） / Sprint 1: #187 / Sprint 2: #188
```

### When to use mockup-first Sprints
- If user says "I cannot decide everything at once" or "I want to confirm UI/UX before implementation", make **Sprint 1 a mockup/prototype sprint**.
- Sprint 1: placeholder UI, no data persistence, browser verification, design decision finalization.
- Sprint 2+: backend implementation based on Sprint 1 decisions.

## Sprint Operations

See [references/sprint-operations.md](references/sprint-operations.md) for:
- Sprint handover sections in Epic
- Sprint N-A naming for discovered gaps
- Pre-Sprint codebase scan checklist
- Preparation vs Implementation distinction
- Spec-vs-Implementation diff analysis

## Comment / Sprint Format

See [references/comment-format.md](references/comment-format.md) for heading templates, emoji conventions, sprint plan structure, and evidence examples.

## Key Rules

- Fetch latest state before every write operation (prevent stale overwrites)
- Always include concrete evidence in comments (file path, coverage %, test counts)
- No duplicate sprint completion comments (check existing comments first)
- If coverage misses target by ≥2pt, do NOT close — post next sprint plan instead
- **Always perform a spec-vs-implementation diff analysis before closing an Epic or major Sprint**

## Numbering Guardrail Evidence

See [references/numbering-guardrails.md](references/numbering-guardrails.md) for confirmed retrospective evidence and freshness metadata.

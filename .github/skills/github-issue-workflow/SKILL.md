---
name: github-issue-workflow
description: Manages GitHub issues for LedgerLeap — drafting, reading, commenting, updating checklists, sprint planning, and coverage reporting. Use when performing any GitHub issue operation.
compatibility: "LedgerLeap (owner: torinky, repo: LedgerLeap)"
---

# github-issue-workflow

## Repository

`owner: torinky` / `repo: LedgerLeap`  
Always pass these to every GitHub tool call.

## Issue Read Flow

```
1. issue_read(method: get)              — title, body, labels, assignees
2. issue_read(method: get_comments)     — history, evidence, sprint reports
3. (optional) list_issues / search_issues — parent/child/related
4. (optional) pull_request_read         — linked PRs
```

## Issue Update Flow

```
1. issue_read(get) + issue_read(get_comments)  — always fetch current state first
2. add_issue_comment                            — progress reports, sprint summaries
3. issue_write(method: update, body: <full>)   — checklist checkbox updates (replace entire body)
4. issue_write(method: update, state: closed)  — when all acceptance criteria are met
```

## Issue Drafting Flow

Use this flow for **bugs, improvements, feature additions, refactors, docs, and investigation issues**.

1. **Choose the correct issue form first**
   - `/.github/ISSUE_TEMPLATE/bug_report.yml` for bugs, regressions, CI failures, and error reports
   - `/.github/ISSUE_TEMPLATE/issue_request.yml` for improvements, feature additions, investigations, docs, and refactors
2. **Keep the wording neutral**; describe the problem and goal without presuming the fix.
3. **Keep the form fields aligned** with the canonical body order in the chosen template.
4. **Use sprint breakdowns only when the work spans multiple steps** or needs evidence checkpoints.
5. **Attach concrete evidence**: file paths, test names, coverage, screenshots, logs, and commit refs.
6. **Add acceptance criteria** that are directly checkable.
7. **Before creating a new issue, confirm whether an existing issue already covers the same work; prefer `issue_write(method: update)` when the target entity already exists.**
8. **Keep the issue body in the project’s required session language**; for LedgerLeap, that means Japanese unless the user explicitly asks otherwise.

### Drafting rule

The issue form is the source of truth for issue structure. Do not duplicate the full body skeleton here; link to the template instead and keep any extra guidance brief.

## Checklist Update Rule

**Always update the body directly** — do not leave checkboxes unchecked and only post a comment.

```markdown
- [x] Task completed
    - Evidence: `ClassName`: 0% → **75%** ✅  (commit a1b2c3d)
```

## Coverage Evaluation

1. Read `coverage/index.html` via `mcp_microsoft_mar_convert_to_markdown`
2. Compare against acceptance criteria in issue body
3. Post completion table as comment; close issue if all criteria met

**Project targets** (from Epic #66):
- Overall ≥65% (target 70%), Services ≥80%, Models ≥60%, Livewire ≥65%, Filament ≥30%

## Comment / Sprint Format

See [references/comment-format.md](references/comment-format.md) for heading templates, emoji conventions, sprint plan structure, and evidence examples.

## Key Rules

- Fetch latest state before every write operation (prevent stale overwrites)
- Always include concrete evidence in comments (file path, coverage %, test counts)
- No duplicate sprint completion comments (check existing comments first)
- If coverage misses target by ≥2pt, do NOT close — post next sprint plan instead

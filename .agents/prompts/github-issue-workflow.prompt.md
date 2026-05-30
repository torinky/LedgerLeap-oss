---
description: Manage GitHub issues for LedgerLeap — read, comment, update checklists, close. Always use owner=torinky, repo=LedgerLeap.
---

# github-issue-workflow

## Repository

`owner: torinky` / `repo: LedgerLeap` — pass to every GitHub tool call.

## Read Flow

1. `issue_read(method: get)` — title, body, labels, assignees
2. `issue_read(method: get_comments)` — history, evidence, sprint reports
3. (optional) `list_issues` / `search_issues` — related issues
4. (optional) `pull_request_read` — linked PRs

## Update Flow

1. Always fetch current state first (`get` + `get_comments`)
2. `add_issue_comment` — progress reports, sprint summaries
3. `issue_write(method: update, body: <full>)` — checklist checkbox updates (replace entire body)
4. `issue_write(method: update, state: closed)` — when all acceptance criteria are met

## Checklist Update Rule

Always update the body directly — do not leave checkboxes unchecked and only post a comment.

```markdown
- [x] Task completed
    - Evidence: `ClassName`: 0% → **75%** ✅  (commit a1b2c3d)
```

## Coverage Evaluation

1. Read `coverage/index.html` via `mcp_microsoft_mar_convert_to_markdown`
2. Compare against acceptance criteria in issue body
3. Post completion table as comment; close issue if all criteria met

**Project targets** (Epic #66):
- Overall ≥65% (target 70%), Services ≥80%, Models ≥60%, Livewire ≥65%, Filament ≥30%

## Key Rules

- Fetch latest state before every write (prevent stale overwrites)
- Always include concrete evidence (file path, coverage %, test counts)
- No duplicate sprint completion comments (check existing comments first)
- If coverage misses target by ≥2pt → do NOT close, post next sprint plan instead

See `.github/skills/github-issue-workflow/references/comment-format.md` for heading templates.


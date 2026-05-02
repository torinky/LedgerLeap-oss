---
name: github-issue-workflow
description: Manages GitHub issues for LedgerLeap — drafting, reading, commenting, updating checklists, sprint planning, and coverage reporting. Use when performing any GitHub issue operation.
compatibility: "LedgerLeap (owner: torinky, repo: LedgerLeap)"
---

# github-issue-workflow

## Repository

`owner: torinky` / `repo: LedgerLeap`  
Always pass these to every GitHub tool call.

## Tool Priority

1. Use MCP issue tools first when the requested GitHub operation is available through MCP.
2. Use `gh` as the second choice when MCP does not expose the operation or when the body needs a full canonical-file sync.
3. Avoid mixing both in the same step unless the workflow explicitly needs MCP for read/comment and `gh` for a write that MCP cannot do.

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

- Prefer MCP issue tools for these steps when available.
- Fall back to `gh` only for operations the MCP tools cannot perform, such as a full body rewrite from a canonical markdown file.

## Body Sync Rule

- When the issue body needs a substantive rewrite, keep a canonical markdown file locally and update GitHub with a full-body replacement.
- After `gh issue edit --body-file ...`, immediately re-fetch the issue body and verify the published content matches the canonical file.
- If stale sections, old sprint numbers, or duplicated headings remain, rewrite the full body again instead of trying to repair the remote body with comments.
- Prefer a single source of truth for the body file; see [GitHub Issue Body Sync Playbook](/docs/runbooks/github-issue-body-sync-playbook.md).

## Numbering Guardrail

- Always confirm the actual GitHub issue number and title before writing docs or checklists; sprint labels in local filenames are not authoritative.
- When a plan or issue body uses Sprint numbers that map to different GitHub issue numbers, add or update a visible `GitHub 追跡` block so the mapping stays explicit.
- If the user overrides scope or numbering, rewrite the affected issue body and companion docs in the same pass instead of leaving both old and new numbering visible.
- If issue body synchronization drifts, return to the canonical file and re-fetch pattern from the Body Sync Rule before adding more comments.

### Evidence

- [docs/work/ui-ux/2026-04-27_issue-176-retrospective-skill-brushup.md](../../../docs/work/ui-ux/2026-04-27_issue-176-retrospective-skill-brushup.md)
- [docs/work/core-features/confidentiality-classification/2026-05-01_retrospective.md](../../../docs/work/core-features/confidentiality-classification/2026-05-01_retrospective.md)
- [docs/work/core-features/confidentiality-classification/2026-05-02_retrospective_sprint3.md](../../../docs/work/core-features/confidentiality-classification/2026-05-02_retrospective_sprint3.md)
- `status`: confirmed
- `last_confirmed_at`: 2026-05-02
- `recheck_after`: 90d
- `recheck_trigger`: a sprint/issue numbering mismatch, a stale checklist left behind after a scope override, a new issue body that needs GitHub 追跡 mapping, or a cross-session sprint handover that lacks structured file/TODO locations in the Epic body

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

- When a checklist item is completed, include concrete evidence next to the checkbox and keep the issue body, plan, and completion report aligned.
- If the issue number changes or was inferred incorrectly, correct the number in the body before adding evidence so the checklist does not preserve stale references.

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

## Sub-Issues and Epic/Sprint Hierarchy

### Sub-Issues
- GitHub Sub-issues **cannot** be created or linked via API, MCP, or `gh` CLI.
- After creating an Epic and its Sprint issues, **manually open the Epic in the Web UI** and add each Sprint issue as a sub-issue.
- Always include a visible `GitHub 追跡` block in the Epic body so the mapping stays explicit even if sub-issue linkage fails.

```markdown
## GitHub 追跡
- Epic: #186（本 Issue）
- Sprint 1: #187
- Sprint 2: #188
```

### Epic / Sprint / Sub-task Structure
- **Epic**: The parent issue that describes the overall goal, scope, non-scope, and acceptance criteria.
- **Sprint**: Child issues that break the Epic into time-boxed, demo-able increments.
- **Sub-task**: Checklist items inside a Sprint issue; keep them actionable and attach evidence on completion.

### When to use mockup-first Sprints
- If the user says "I cannot decide everything at once" or "I want to confirm the UI/UX before implementation," make **Sprint 1 a mockup/prototype sprint**.
- Sprint 1 scope: placeholder UI components, no data persistence, browser verification, and design decision finalization.
- Sprint 2 onward: backend implementation based on the decisions made in Sprint 1.
- Evidence for mockup Sprints: browser screenshots, DevTools z-index stacks, responsive layout checks.

### Sprint Handover Section in Epic
- When a Sprint finishes and the next Sprint will start in a **new session**, add a **"Sprint N 引継ぎ情報"** section to the Epic body.
- Include:
  1. **Branch name**: the branch where the Sprint was completed, and the recommended branch name for the next Sprint.
  2. **Changed files table**: file path, what was changed, and how to treat it in the next Sprint (reuse / comment-out / replace).
  3. **TODO comment locations**: list files where `TODO(#issue-SprintN)` markers were placed.
  4. **Open questions**: decisions still pending that the next Sprint must resolve.
- Rationale: Session context is lost between sprints; a single handover section prevents re-discovery of file locations and design intent.

### Additional Sprint Naming (Sprint N-A)
- When a Sprint finishes and **new unimplemented items are discovered** during a spec-vs-implementation diff analysis, create a follow-up issue.
- **Naming rule**: Use `Sprint N-A` (e.g., `Sprint 3A`) instead of incrementing the main number. This avoids collision with an already-existing Sprint N+1 issue.
- **Scope**: Sprint N-A should only contain items discovered from the diff analysis or deferred from Sprint N. Do NOT mix unrelated new features.
- **Epic update**: Add the new sprint to the Epic's `GitHub 追跡` block immediately. Update the Epic's sprint decomposition table to show `Sprint N-A` between `Sprint N` and `Sprint N+1`.
- Evidence: Sprint 3 (#189) completion revealed 4 unimplemented items; Sprint 3A (#191) was created to avoid collision with existing Sprint 4 (#190).

### Pre-Sprint Codebase Scan
- **Always scan the codebase before starting implementation** in a new session, even if the issue checkboxes are unchecked.
- Previous sessions may have partially or fully implemented some items without updating the issue body.
- **Scan checklist**:
  1. `git log --grep="#<issue-number>"` — find commits from previous sessions
  2. `grep -r "keyword" app/ resources/ --include="*.php" --include="*.blade.php"` — locate existing code
  3. Run relevant tests: `./vendor/bin/sail test <path>` — verify if implementation already passes
  4. If implementation is already present, update the issue body checkboxes and evidence immediately instead of re-implementing
- **Rationale**: Prevents duplicate work and stale issue state. Sprint 3-2 (LedgerDefineEdit integration) was already fully implemented in the codebase, saving ~30 minutes of redundant work.

### Preparation vs Implementation
- **"準備してください"** (prepare) means planning, branch creation, issue updates, and checklist reviews. **Do NOT write migrations, create services, or modify models** until the user explicitly says "着手してください" (start implementation) or similar.
- **"着手してください"** (start) means actual coding: migrations, services, model changes, UI integration.
- If unsure whether the user wants preparation or implementation, **ask explicitly**: "準備（計画・ブランチ作成）まででよろしいでしょうか、それとも実装（マイグレーション・コーディング）も含めて進めてよろしいでしょうか？"
- Evidence: Sprint 2 (#188) — user said "着手準備をしてください" but I implemented migrations anyway, causing a correction.

## Comment / Sprint Format

See [references/comment-format.md](references/comment-format.md) for heading templates, emoji conventions, sprint plan structure, and evidence examples.

## Spec-vs-Implementation Diff Analysis

- **Perform a diff analysis after every Sprint completion**, before declaring the Epic complete.
- Compare the issue's acceptance criteria, the spec documents (`docs/work/...`), and the actual codebase.
- **Discovery rule**: If the spec mandates something that the implementation lacks, do NOT silently add it to the current Sprint's scope. Instead:
  1. Document the gaps in a structured comment on the completed Sprint issue
  2. Create a new Sprint N-A issue for the gaps
  3. Update the Epic body to reflect the new Sprint
- **Priority classification**:
  - 🔴 Operationally required: blocks production use (e.g., admin UI for a new DB column)
  - 🟡 Within original MVP scope: spec said MVP but was missed
  - 🟢 Minor deviation: acceptable trade-off; document only
- Evidence: Sprint 3 (#189) diff analysis uncovered 4 gaps (abbreviation admin UI, Create form integration, multi-level inheritance tooltip, Intersection Observer scroll tracking), leading to Sprint 3A (#191).

## Key Rules

- Fetch latest state before every write operation (prevent stale overwrites)
- Always include concrete evidence in comments (file path, coverage %, test counts)
- No duplicate sprint completion comments (check existing comments first)
- If coverage misses target by ≥2pt, do NOT close — post next sprint plan instead
- **Always perform a spec-vs-implementation diff analysis before closing an Epic or major Sprint**

# GitHub Issue Sprint Operations

## Sprint Handover Section in Epic

When a Sprint finishes and the next Sprint starts in a **new session**, add a **"Sprint N handover"** section to the Epic body.

Include:
1. **Branch name**: branch where Sprint was completed + recommended branch for next Sprint
2. **Changed files table**: file path, what changed, how to treat in next Sprint (reuse / comment-out / replace)
3. **TODO comment locations**: files with `TODO(#issue-SprintN)` markers
4. **Open questions**: pending decisions the next Sprint must resolve

Rationale: Session context is lost between sprints; a single handover section prevents re-discovery.

## Additional Sprint Naming (Sprint N-A)

When new unimplemented items are discovered during spec-vs-implementation diff analysis:
- Create a follow-up issue named `Sprint N-A` (e.g. `Sprint 3A`) instead of incrementing the main number.
- Scope: only items from diff analysis or deferred from Sprint N. Do NOT mix unrelated features.
- Update Epic `GitHub 追跡` block immediately.

## Pre-Sprint Codebase Scan

**Always scan the codebase before starting implementation** in a new session, even if issue checkboxes are unchecked.

**Scan checklist**:
1. `git log --grep="#<issue-number>"` — find commits from previous sessions
2. `grep -r "keyword" app/ resources/ --include="*.php" --include="*.blade.php"` — locate existing code
3. `./vendor/bin/sail test <path>` — verify if implementation already passes
4. If implementation exists, update issue body checkboxes and evidence instead of re-implementing

Rationale: Prevents duplicate work. Sprint 3-2 (LedgerDefineEdit integration) was already fully implemented, saving ~30 minutes.

## Preparation vs Implementation

- **"準備してください"** (prepare) = planning, branch creation, issue updates, checklist reviews. **Do NOT write migrations, create services, or modify models** until user explicitly says "着手してください" (start) or similar.
- **"着手してください"** (start) = actual coding: migrations, services, model changes, UI integration.
- If unsure, **ask explicitly**: "準備（計画・ブランチ作成）まででよろしいでしょうか、それとも実装（マイグレーション・コーディング）も含めて進めてよろしいでしょうか？"

## Spec-vs-Implementation Diff Analysis

**Perform after every Sprint completion**, before declaring Epic complete.

1. Document gaps in a structured comment on the completed Sprint issue
2. Create a new Sprint N-A issue for the gaps
3. Update Epic body to reflect the new Sprint

**Priority classification**:
- 🔴 Operationally required: blocks production use
- 🟡 Within original MVP scope: spec said MVP but was missed
- 🟢 Minor deviation: acceptable trade-off; document only

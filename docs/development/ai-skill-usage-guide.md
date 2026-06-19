# AI Skill Usage Guide

This document describes every skill, command, and prompt available in LedgerLeap's AI asset system. Use it to understand when to invoke which capability and how they relate to each other.

## Execution Layers

The AI asset system has three invocation layers:

| Layer | Location | Trigger |
|---|---|---|
| **Skill** | `.github/skills/<name>/SKILL.md` | Auto-loaded when topic matches; or explicit `skill` tool call |
| **Prompt** (IDE) | `.github/prompts/<name>.prompt.md` | JetBrains `/<name>` slash command |
| **Command** (opencode) | `.opencode/commands/<name>.md` | opencode `/<name>` slash command |

## Skills by Category

### Git / CI / Release

| Skill | When to Use | Location |
|---|---|---|
| `git-commit` | Any git commit. Handles Japanese/special characters safely. | `.github/skills/git-commit/SKILL.md` |
| `ci-failure-investigation` | CI fails, tests timeout, or pass locally but fail in CI. | `.github/skills/ci-failure-investigation/SKILL.md` |
| `release-workflow` | Creating a CalVer release tag, pre-release (alpha/beta/rc), or managing release lifecycle. | `.github/skills/release-workflow/SKILL.md` |

### GitHub

| Skill | When to Use | Location |
|---|---|---|
| `github-issue-workflow` | Any GitHub issue operation: drafting, reading, commenting, updating checklists, sprint planning, coverage reporting. | `.github/skills/github-issue-workflow/SKILL.md` |

### Testing

| Skill | When to Use | Location |
|---|---|---|
| `database-migrations-test-optimization` | Writing Mroonga full-text search tests, multi-tenant boundary tests, or when tests exceed 300s in CI. | `.github/skills/database-migrations-test-optimization/SKILL.md` |
| `test-external-dependency-isolation` | Writing tests involving Ledger, AttachedFile, or code that dispatches jobs to external containers (Embedding, VLM, LDAP, OCR). | `.github/skills/test-external-dependency-isolation/SKILL.md` |

### Debugging / Bug Fix

| Skill | When to Use | Location |
|---|---|---|
| `bug-investigation` | Bug triage, exception analysis, UI regression, CI failure, or unexpected behavior needs root-cause analysis before changing code. | `.github/skills/bug-investigation/SKILL.md` |
| `bug-execution` | Investigation is complete and a fix approach has been selected. Implements minimal changes with regression prevention. | `.github/skills/bug-execution/SKILL.md` |

### Browser / HAR

| Skill | When to Use | Location |
|---|---|---|
| `browser-har-analysis` | Comparing HAR files, standardizing repeated scripts, reviewing network captures. | `.github/skills/browser-har-analysis/SKILL.md` |

### Livewire

| Skill | When to Use | Location |
|---|---|---|
| `livewire-tenant-context` | `tenant()?->id` is null in `render()`, route generation fails with missing tenant parameter, `#[Lazy]` components break. | `.github/skills/livewire-tenant-context/SKILL.md` |
| `livewire-loading-ui` | Adding `wire:loading`, fixing `x-show` not working, `wire:key` flicker, sticky table headers, DaisyUI drawer sidebar scrolling. | `.github/skills/livewire-loading-ui/SKILL.md` |
| `livewire-computed-properties` | Testing `#[Computed]` properties, `#[Url]` initialization, `#[Reactive]` child sync. | `.github/skills/livewire-computed-properties/SKILL.md` |

### UI / UX Design

| Skill | When to Use | Location |
|---|---|---|
| `search-header-responsive-layout` | Search header / sticky toolbar / responsive breakpoint / scroll occlusion. | `.github/skills/search-header-responsive-layout/SKILL.md` |
| `title-block` | Page shell, compact header, breadcrumbs, metadata, primary action. | `.github/skills/title-block/SKILL.md` |
| `form-layout` | Create/edit forms, field grouping, labels, helper text. | `.github/skills/form-layout/SKILL.md` |
| `responsive-text-icon-sizing` | Text too small on desktop, icons fixed at one size, size classes should respond to device or context. | `.github/skills/responsive-text-icon-sizing/SKILL.md` |
| `ledger-detail-header` | Building or refactoring ledger page headers, global expand toggles, mandatory indicators. | `.github/skills/ledger-detail-header/SKILL.md` |
| `mary-ui-component-patterns` | Building new screens or refactoring existing views with Mary UI components (`x-mary-card`, `x-mary-modal`, `x-mary-header`). | `.github/skills/mary-ui-component-patterns/SKILL.md` |
| `tabbed-dashboard-responsive-layout` | Mary UI tabs, tab labels carry counts or state badges, controller and Livewire state must synchronize with initial active tab. | `.github/skills/tabbed-dashboard-responsive-layout/SKILL.md` |
| `sticky-action-bar-footer-pattern` | Page needs a bottom action bar, slot responsibilities, or badge-first status summaries. | `.github/skills/sticky-action-bar-footer-pattern/SKILL.md` |
| `notification-banner-alert-surface-pattern` | UI needs banner/alert semantics, level-based palette, right-aligned action cluster, or sticky offset handling. | `.github/skills/notification-banner-alert-surface-pattern/SKILL.md` |

### Design Process

| Skill | When to Use | Location |
|---|---|---|
| `mockup-driven-development` | User cannot decide all design questions upfront; use mockups to settle UI layout, behavior before production code. | `.github/skills/mockup-driven-development/SKILL.md` |

### Data / Content

| Skill | When to Use | Location |
|---|---|---|
| `ledger-content-data-structure` | `content[n]` returns null, test data misaligned, `data_get()` returns null on `content`/`content_attached`, `latest_diff_id` missing. | `.github/skills/ledger-content-data-structure/SKILL.md` |

### ACL / Permission

| Skill | When to Use | Location |
|---|---|---|
| `permission-model` | 403 errors, Role/Organization/User changes not reflected, permission checks unstable in tests, adding new folder-based checks. | `.github/skills/permission-model/SKILL.md` |

### Workflow (Status Machine)

| Skill | When to Use | Location |
|---|---|---|
| `workflow-status-machine` | Workflow status not transitioning, `latestDiff()` returns null, inspector/approver modal won't open, workflow-related tests fail. | `.github/skills/workflow-status-machine/SKILL.md` |

### Search / RAG

| Skill | When to Use | Location |
|---|---|---|
| `rag-vector-search` | Search results unexpected, rebuilding RAG index after model change, EmbeddingService times out in CI, switching between search modes. | `.github/skills/rag-vector-search/SKILL.md` |

### Cache

| Skill | When to Use | Location |
|---|---|---|
| `tenant-aware-cache-design` | Designing cache keys, tags, and invalidation strategies for multi-tenant Redis. Prevents cross-tenant cache leaks. | `.github/skills/tenant-aware-cache-design/SKILL.md` |

### Development Environment

| Skill | When to Use | Location |
|---|---|---|
| `sail-dev-workflow` | Git commands produce no output inside Sail, CSS/JS changes not reflected, test DB broken, VLM/embedding container not responding. | `.github/skills/sail-dev-workflow/SKILL.md` |

### Translation

| Skill | When to Use | Location |
|---|---|---|
| `translation` | Adding/updating/reviewing ledger translation keys, running `translations:compare`, diagnosing missing/duplicate labels in Blade views. | `.github/skills/translation/SKILL.md` |

### Client-Facing Contracts

| Skill | When to Use | Location |
|---|---|---|
| `client-facing-contract-promotion` | WebUI feature exists but MCP or REST does not yet expose it. Promotes workflow to a client-facing contract. | `.github/skills/client-facing-contract-promotion/SKILL.md` |

### Documentation

**Three-layer PHPDoc workflow** (see PHPDoc Workflow section below):

| Skill | When to Use | Location |
|---|---|---|
| `phpdoc-sweep` ***(command)*** | Discovery + batch sweep: scan `app/` for undocumented files, pick one per invocation. | `.opencode/commands/phpdoc-sweep.md` |
| `phpdoc-maintenance` | File-by-file PHPDoc add/update/maintain. Given a specific PHP file, adds class and method DocBlocks. | `.github/skills/phpdoc-maintenance/SKILL.md` |
| `comment-sync` | PHPDoc audit for doc-publication-packet anchor sets. Verifies comments match source anchors. | `.github/skills/comment-sync/SKILL.md` |

**Doc creation lifecycle** (see Doc Publication section below):

| Skill | When to Use | Location |
|---|---|---|
| `doc-creation-sprint` | Find highest-priority unwritten doc from the #226 backlog and create one per execution. | `.github/skills/doc-creation-sprint/SKILL.md` |
| `doc-source-inventory` | Refresh #226-derived backlog / readiness deltas without rerunning initial inventory. | `.github/skills/doc-source-inventory/SKILL.md` |
| `doc-publication-audit` | Create or rewrite one stable public-facing doc from a packet handoff (packet_id, target_path, anchors fixed). | `.github/skills/doc-publication-audit/SKILL.md` |

### Meta

| Skill | When to Use | Location |
|---|---|---|
| `skill-maintenance` | Bug fix, sprint, investigation, or user-requested retrospective proves new reusable pattern, disproves old rule, or reveals missing workflow. | `.github/skills/skill-maintenance/SKILL.md` |

## PHPDoc Workflow

Three-layer architecture for PHPDoc comment maintenance:

```
/phpdoc-sweep                発見 + キュー管理（opencode slash）
  ↓ 次ファイルを選んで処理
phpdoc-maintenance           1ファイルのPHPDoc書き込み（Skill）
  ↓ 公開ドキュメントのアンカーと同期
comment-sync                 PHPDoc監査（Skill）
```

- `/phpdoc-sweep` → discover undocumented files, queue them, pick next, call `phpdoc-maintenance` per file.
- `phpdoc-maintenance` → given a file path, pre-analyze docs/callers, write class + method DocBlocks.
- `comment-sync` → verify PHPDoc against doc-publication-packet anchor sets (not for routine maintenance).

## Doc Publication Lifecycle

```
/ doc-creation-sprint         発見 + 作成（opencode slash）
  ↓ 優先度順に1ドキュメント作成
doc-source-inventory         在庫更新（Skill）
  ↓ パケットハンドオフ
doc-publication-audit         公開書き換え（Skill）
  ↓ ソースアンカー同期
comment-sync                 PHPDoc監査（Skill）
```

1. `/doc-creation-sprint` — discovers the top unwritten doc from #226 backlog, creates one file per invocation.
2. `doc-source-inventory` — refreshes backlog/readiness when the inventory is stale.
3. `doc-publication-audit` — edits an existing doc with a fixed packet contract.
4. `comment-sync` — syncs PHPDoc anchors after a doc change.

## Debugging Flow

```
bug-investigation             原因調査（Skill）
  → root cause が特定されたら
bug-execution                 修正実装（Skill）
  → テンプレート・ルールが証明されたら
skill-maintenance             AI資産メンテナンス（Skill）
```

1. `bug-investigation` — collect evidence, compare hypotheses, propose response options.
2. `bug-execution` — implement minimal fix with regression prevention.
3. `skill-maintenance` — promote reusable learnings into `.github` assets.

## Index of All Assets

### Skills (`/github/skills/*/SKILL.md`)

| # | Name | Category |
|---|---|---|
| 1 | `browser-har-analysis` | Browser |
| 2 | `bug-execution` | Debugging |
| 3 | `bug-investigation` | Debugging |
| 4 | `ci-failure-investigation` | Git/CI |
| 5 | `client-facing-contract-promotion` | Contracts |
| 6 | `comment-sync` | Documentation |
| 7 | `database-migrations-test-optimization` | Testing |
| 8 | `doc-creation-sprint` | Documentation |
| 9 | `doc-publication-audit` | Documentation |
| 10 | `doc-source-inventory` | Documentation |
| 11 | `form-layout` | UI/UX |
| 12 | `git-commit` | Git/CI |
| 13 | `github-issue-workflow` | GitHub |
| 14 | `ledger-content-data-structure` | Data |
| 15 | `ledger-detail-header` | UI/UX |
| 16 | `livewire-computed-properties` | Livewire |
| 17 | `livewire-loading-ui` | Livewire |
| 18 | `livewire-tenant-context` | Livewire |
| 19 | `mary-ui-component-patterns` | UI/UX |
| 20 | `mockup-driven-development` | Design |
| 21 | `notification-banner-alert-surface-pattern` | UI/UX |
| 22 | `permission-model` | ACL |
| 23 | `phpdoc-maintenance` | Documentation |
| 24 | `rag-vector-search` | Search |
| 25 | `release-workflow` | Git/CI |
| 26 | `responsive-text-icon-sizing` | UI/UX |
| 27 | `sail-dev-workflow` | DevEnv |
| 28 | `search-header-responsive-layout` | UI/UX |
| 29 | `skill-maintenance` | Meta |
| 30 | `sticky-action-bar-footer-pattern` | UI/UX |
| 31 | `tabbed-dashboard-responsive-layout` | UI/UX |
| 32 | `tenant-aware-cache-design` | Cache |
| 33 | `test-external-dependency-isolation` | Testing |
| 34 | `title-block` | UI/UX |
| 35 | `translation` | Translation |
| 36 | `workflow-status-machine` | Workflow |

### Commands (`/opencode/commands/*.md`)

| # | Command | Description |
|---|---|---|
| 1 | `doc-creation-sprint` | Create one doc from #226 backlog |
| 2 | `packet-comment-sync` | Sync comment anchors for one packet |
| 3 | `packet-plan` | Prepare one packet handoff |
| 4 | `packet-rewrite` | Rewrite one packet with single writer |
| 5 | `phpdoc-sweep` | Scan + queue + process PHPDoc one file per call |

### Prompts (`/github/prompts/*.prompt.md`)

| # | Prompt | Description |
|---|---|---|
| 1 | `browser-har-analysis` | Analyze browser HAR files |
| 2 | `bug-execution` | Execute a selected bug fix |
| 3 | `bug-investigation` | Investigate a bug before implementation |
| 4 | `ci-failure-investigation` | Investigate CI failures |
| 5 | `client-facing-contract-triage` | Triage client-facing contracts |
| 6 | `doc-creation-sprint` | Create one doc from backlog |
| 7 | `doc-publication-packet` | Doc publication packet router |
| 8 | `git-commit` | Create git commits |
| 9 | `github-issue-workflow` | Manage GitHub issues |
| 10 | `phpdoc-maintenance` | Add PHPDoc to one file |
| 11 | `rag-vector-search` | Debug hybrid search |
| 12 | `release-workflow` | Manage release lifecycle |
| 13 | `skill-maintenance` | Maintain AI assets |

## Freshness

- status: confirmed-repo
- last_confirmed_at: 2026-06-07
- recheck_after: 180d
- recheck_trigger: New skills added, skills retired, or skill locations change.

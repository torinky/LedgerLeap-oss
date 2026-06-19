# Test Data Design

## Purpose

This guide documents the design of demo and integration test datasets used to validate MCP tools and UI features. The dataset is built from the perspective of what each MCP tool requires, ensuring comprehensive coverage across all input types, workflow states, and permission patterns.

---

## Scope

- Demo dataset seeded via `DemoCompleteSeeder`
- Covers all 11 MCP tools: Search, Create, GetLedgerDefines, GetPendingApprovals, ExecuteApproval, GetWorkflowHistory, ClaimWorkflowTask, GetActivityLog, GetLedgerStats, GetUserActivityStats, GetFolderStats
- All 10 `InputType` variants, all 5 workflow statuses, and all permission levels are exercised

---

## Architecture

### Design Principles

| Principle | Implementation |
|---|---|
| Test isolation | `DemoSeeder` is fully independent; run with `php artisan db:seed --class=DemoSeeder` |
| Environment separation | Controlled by `APP_ENV=demo` |
| Identifiable data | `[DEMO]` prefix on all demo ledger defines and organizations |
| Test DB non-interference | Does not affect tests using `RefreshDatabaseWithTenant` |

### Data Hierarchy

```
Tenant (1)
├── Organization (3): 本社, 営業部, 技術部
├── User (12): 管理者×2, 実務担当者×6, 点検者×2, 承認者×2
├── Role (7): 管理者, デモユーザー, 一般ユーザー(営業), 一般ユーザー(技術), 点検者, 承認者, 監査
└── Folder (10): nested hierarchy under 営業部, 技術部, 全社共通
```

### Folder Tree

```
/ (ルート)
├── 営業部/
│   ├── 日報/
│   └── 商談記録/
├── 技術部/
│   ├── 開発日報/
│   └── 障害報告/
└── 全社共通/
    ├── 申請書/
    ├── 報告書/
    └── 議事録/
```

---

## Factories

### LedgerDefine (4 definitions)

| Name | Featured InputTypes |
|---|---|
| `[DEMO] 営業日報` | TextType, TextareaType, DateType, SelectType |
| `[DEMO] 経費申請` | NumberType, FilesType, AutoNumberType, SelectType |
| `[DEMO] 設備点検表` | CheckboxType, SelectType, TextType, TextareaType, DateType |
| `[DEMO] 週報` | SelectType, TextareaType, DateType |

> `PhoneNumberType` is supported by the existing type system but not used in the current demo dataset.

### Ledger (27 records)

| Status | Count |
|---|---|
| NONE | 7 |
| DRAFT | 3 |
| PENDING_INSPECTION | 3 |
| PENDING_APPROVAL | 2 |
| APPROVED | 12 |

### Tags

25 tags across categories: 営業×5, 技術×5, 全社×5, プロジェクト×5, その他×5

### Workflow Tasks

- Pending inspection: 3
- Pending approval: 2

### Activity Logs

120+ auto-generated entries.

---

## Seeders

| Seeder | Role |
|---|---|
| `DemoSeeder` | Base demo data independent of environment |
| `DemoCompleteSeeder` | Orchestrator that composes all demo seeders |

Run with:

```bash
php artisan db:seed --class=DemoCompleteSeeder
```

---

## Configuration

| Setting | Value | Notes |
|---|---|---|
| `APP_ENV` | `demo` | Gate for seeding and tool behavior |
| Database | `mysql_demo` | Separate from `mysql` and `mysql_testing` |
| Cache | `redis` or `file` | Not `array` (demo environment is persistent) |

---

## Edge Cases

### InputType Coverage

All 10 input types are demonstrable:

`TextType`, `TextareaType`, `NumberType`, `DateType`, `SelectType`, `CheckboxType`, `PhoneNumberType`, `FilesType`, `AutoNumberType`

### Workflow State Coverage

Every workflow state has representative records so that approval, inspection, rollback, and history tools can be verified.

### Permission Coverage

| Level | Role | Verified |
|---|---|---|
| Read-only | 監査 | Yes |
| Write | 一般ユーザー | Yes |
| Admin | 管理者 | Yes |
| Inspect | 点検者 | Yes |
| Approve | 承認者 | Yes |
| Folder inheritance | All roles | System-level |

### Search & Filter Coverage (SearchLedgersTool)

| Parameter | Coverage |
|---|---|
| `q` (keyword) | Yes |
| `tags` | Data exists |
| `folder_id` | Yes |
| `content_attached` (via `q`) | Yes |
| `created_from` / `created_to` | Planned |
| `status` | Planned |
| `creator_id` | Planned |

---

## MCP Tool Matrix

| # | Tool | Required Data |
|---|---|---|
| 1 | SearchLedgersTool | 30 ledgers, 20 tags, 10 attachments |
| 2 | CreateLedgerTool | 8 ledger defines, 10 folders |
| 3 | GetLedgerDefinesTool | 8 ledger defines (all InputTypes) |
| 4 | GetPendingApprovalsTool | 10 pending approval, 8 pending inspection |
| 5 | ExecuteApprovalTool | 5 pending approval (return-to-draft capable) |
| 6 | GetWorkflowHistoryTool | 20 workflow history entries |
| 7 | ClaimWorkflowTaskTool | 5 unassigned tasks |
| 8 | GetActivityLogTool | 100 activity log entries |
| 9 | GetLedgerStatsTool | 3-month ledger distribution |
| 10 | GetUserActivityStatsTool | Multi-user activity data |
| 11 | GetFolderStatsTool | Folder hierarchy with ledger distribution |

---

## Technical Evidence

### Mroonga Vector Column Double-JSON Encoding

**Problem**: Ledgers created via Seeder with numeric column values had `content` return `null`.

**Root cause**: Mroonga's vector column pre-processing, when encountering integer values inside JSON arrays with numeric keys, re-encodes the array as JSON, corrupting the structure.

**Solution**: The `AsColumnArrayJson` cast class was updated to automatically convert integers and floats to strings before DB persistence, bypassing the Mroonga side effect.

**Ongoing constraint**: Top-level keys of the `content` array must always be `int` type. Using string keys (e.g., `'0' => 'val'`) causes inconsistency on retrieval and breaks dependent features such as `AutoLinkService`.

---

## Related Documents

- [Database Seeding Guide](./database-seeding-guide.md): Usage guide for database seeders including demo data.

# Sprint 2-A1: ソースコード起点の公開ドキュメント候補棚卸し

## GitHub 追跡
- Umbrella: #225
- Sprint 2-A1: #226（本 Issue）
- Downstream target: #219

## 概要
公開 doc リストを既存想定から起こすのではなく、routes / Livewire / Filament / API / MCP / tests / lang keys / existing docs から **source-derived に feature inventory を生成** し、公開候補・粒度差・coverage gap を整理する。

## 背景 / 目的
- 現行の #219 doc list は feature coverage を仮定しているが、source 側の実装面と 1:1 ではない
- import/export, file inspector, rollback, bootstrap manifest, admin announcement, notifications など、public candidate の粒度が混在している
- 先に source-derived inventory を作らないと、packet backlog 全体が仮説ベースのままになる

## source scan で確定した信号
| 信号 | 主要エビデンス |
|---|---|
| Route / controller | `routes/tenant.php:43-136`, `routes/api.php:27-71` |
| End-user UI | `app/Livewire/Ledger/*`, `app/Livewire/Workflow/*`, `app/Livewire/AttachedFile/*`, `app/Livewire/MyPortal.php`, `app/Livewire/Notifications/*` |
| Admin / operator UI | `app/Filament/Resources/*`, `app/Filament/Pages/AdminAnnouncementBanner*`, `app/Filament/Widgets/DashboardLinksWidget.php` |
| API / MCP contract | `app/Mcp/Servers/LedgerLeapServer.php`, `app/Mcp/Tools/*`, `app/Mcp/Resources/*`, `tests/Feature/Api/*`, `tests/Feature/Mcp/*` |
| Observable behavior | `tests/Feature/Livewire/*`, `tests/Feature/Filament/*`, `tests/Feature/Ledger/*`, `tests/Feature/Jobs/*`, `tests/Feature/Http/Controllers/*` |
| Translation / terminology | `lang/ja/ledger/workflow.php`, `lang/ja/ledger/notifications.php`, `lang/ja/ledger/file_inspector.php`, `lang/ja/ledger/mcp.php`, `lang/ja/ledger/folders.php`, `lang/ja.json` |
| Existing doc coverage | `docs/function/*.md`, `docs/api/README.md`, `docs/api/JAPANESE_SEARCH_GUIDE.md`, `docs/architecture/*` |

## source-derived feature inventory
| Feature family | 主要ソース面 | Audience | public / internal | 現行 coverage | doc type candidate | comment anchor candidate |
|---|---|---|---|---|---|---|
| Ledger lifecycle | `routes/tenant.php:46-95`, `app/Livewire/Ledger/*`, `tests/Feature/Livewire/Ledger/*`, `tests/Feature/Api/Ledger*` | 利用者 / API 利用者 | mixed | `docs/function/Ledger.md` はあるが developer-facing | Features guide + API reference | create / edit / show / duplicate / import / export / diff / default-sort / prefill |
| Workflow / rollback | `routes/tenant.php:98-101`, `app/Livewire/Ledger/Workflow*`, `app/Livewire/Workflow/*`, `tests/Feature/Livewire/Workflow/*`, `tests/Feature/Ledger/Rollback*` | 利用者 / 承認者 | mixed | `docs/function/WorkFlow.md`, `docs/function/History.md` はあるが粒度が粗い | Features guide + operations note | pending-list / assignee / comment modal / approval / return-to-draft / rollback / history |
| Search / lookup / taxonomy | `routes/tenant.php:43,107`, `routes/api.php:34-35`, `app/Mcp/Tools/SearchLedgersTool.php`, `app/Filament/Resources/Synonym/*`, `app/Filament/Resources/TagResource.php`, `tests/Feature/Api/SearchApiTest.php`, `tests/Feature/Mcp/SearchLedgersTool*` | 利用者 / 管理者 / API・MCP 利用者 | mixed | `docs/function/Search.md`, `docs/api/JAPANESE_SEARCH_GUIDE.md`, `docs/architecture/tag-design.md` | Features guide + API/client guide + admin reference | search API GET/POST / synonym / technical-term / tag / lookup / related-ledgers |
| Attachments / file inspector / OCR-VLM | `routes/tenant.php:115-128`, `app/Livewire/AttachedFile/*`, `tests/Feature/Livewire/AttachedFile/*`, `tests/Feature/Jobs/ProcessAttachedFileTest.php`, `tests/Feature/Jobs/ProcessVlmExtractionTest.php` | 利用者 / 実装者 | mixed | `docs/function/Attachment.md`, `docs/architecture/file-processing-flow.md`, `docs/architecture/vlm-*` | Features guide + architecture reference | secure download / file inspector / OCR PDF / VLM / async jobs / reprocess |
| My Portal / navigation | `routes/tenant.php:109`, `app/Livewire/MyPortal.php`, `tests/Feature/Livewire/MyPortalTest.php`, `app/Filament/Widgets/DashboardLinksWidget.php` | 利用者 | public | `docs/function/MyPortal.md` はある | Getting Started + feature guide | first landing / assigned folders / quick links / notification entry |
| Notifications / history / access / announcements | `app/Livewire/Notifications/*`, `app/Livewire/Common/ActivityHistoryDisplay.php`, `app/Livewire/Common/PermissionDisplay.php`, `app/Filament/Pages/AdminAnnouncementBanner*`, `tests/Feature/Views/AdminAnnouncementBannerTest.php`, `tests/Feature/Livewire/Notifications/*` | 利用者 / 管理者 | mixed | `docs/function/Notification.md`, `docs/function/Activity.md`, `docs/function/AccessAndActivity.md` はあるが分離不足 | Features guide + admin note | notification list / settings / workflow summary / activity history / permission display / admin announcement banner |
| Folders / hierarchy / folder permissions | `routes/tenant.php:103-106`, `app/Livewire/Folder/*`, `app/Filament/Resources/FolderResource/*`, `tests/Feature/Livewire/Folder/*`, `tests/Feature/Filament/FolderResourceTest.php` | 利用者 / 管理者 | mixed | `docs/function/Authority.md` と architecture 側に分散 | Features guide + admin reference | folder tree / create-edit / inherited permission / tenant scoping |
| Identity / RBAC / tenant administration | `app/Filament/Resources/UserResource.php`, `RoleResource.php`, `OrganizationResource.php`, `TenantResource.php`, `PermissionResource.php`, `tests/Feature/Filament/UserResourceTest.php`, `RoleResourceTest.php`, `TenantResourceTest.php` | 管理者 / 開発者 | internal | `docs/function/User.md`, `Role.md`, `Organization.md`, `Authority.md`, `docs/architecture/multi-tenancy.md`, `permission-system.md` | Admin reference + architecture reference | users / roles / organizations / tenants / tokens / folder-permission matrix |
| REST API contract | `routes/api.php:27-61`, `tests/Feature/Api/AuthTest.php`, `SearchApiTest.php`, `LedgerReadUpdateApiTest.php`, `BootstrapManifestApiTest.php` | API 利用者 | public | `docs/api/README.md` はあるが overview 寄り | API overview + endpoint reference | auth / search / ledger-defines / ledger CRUD / bootstrap-manifest |
| MCP contract / bootstrap resources | `app/Mcp/Servers/LedgerLeapServer.php`, `app/Mcp/Tools/*`, `app/Mcp/Resources/*`, `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php`, `BootstrapClientResourceTest.php` | MCP client / AI client 利用者 | public | `docs/api/*` に明示的な MCP guide が不足 | MCP client guide + contract reference | bootstrap manifest / search-ledgers / get-ledger-detail / update-ledger / attachment resources |

## current doc coverage gap
| 現行 #219 想定 | source-derived gap | v2 調整 |
|---|---|---|
| `docs/getting-started/*` を broad に決めている | tenant context / My Portal / navigation が独立した導線として見えている | Getting Started は `overview`, `tenant-context`, `portal-and-navigation` を最小単位に分ける |
| `docs/features/*` が 1 つの大きな機能ガイド群になっている | ledger / workflow / search / attachments / notifications / folders が別 family として確認できた | Features は family 単位で分割し、admin-only な説明を混ぜない |
| `docs/api/*` で API / MCP を一括に扱う想定 | REST API と MCP contract の読者・入口・安定面が異なる | `REST API` と `MCP client guide` を分離し、bootstrap manifest を独立項目にする |
| `docs/architecture/*` を広く公開候補に置いている | `permission-system.md`, `multi-tenancy.md`, `file-processing-flow.md` などは内部 detail が多い | 公開側は boundary / processing / permission の要点だけに絞り、既存 architecture doc の丸写しを避ける |
| `docs/contributing/*` を Sprint 2 本体に含めている | 今回の source scan は利用者・UI・API/MCP 起点で、dev env / CI / branch policy を十分カバーしていない | Contributing は別の source set（`docs/development/*`, CI, setup scripts）で再棚卸ししてから #219 に統合する |
| admin surface と利用者 surface の境界が曖昧 | Filament resource 群は operator/admin 向けで、公開利用者 doc とは別に扱うべき | 公開 doc v2 は `Features` と `Admin/Operations` を分け、admin は reference 寄りに整理する |

## #219 用 public doc target list v2
### A. docs/README.md
- 公開ドキュメント index
- 日本語 / 英語方針
- 読者別導線（利用者 / API・MCP 利用者 / admin / contributor）

### B. docs/getting-started/*
- `overview.md`
- `tenant-context.md`
- `portal-and-navigation.md`

### C. docs/features/*
- `ledger-lifecycle.md`
- `workflow-and-rollback.md`
- `search-and-lookup.md`
- `attachments-and-file-inspector.md`
- `notifications-history-and-announcements.md`
- `folders-and-access.md`

### D. docs/admin/*
- `users-and-organizations.md`
- `roles-permissions-and-folder-access.md`
- `tags-synonyms-and-search-taxonomy.md`
- `admin-announcement-banner.md`

### E. docs/api/*
- `overview.md`
- `search-api.md`
- `ledger-api.md`
- `bootstrap-manifest-api.md`
- `mcp-client-guide.md`

### F. docs/architecture/*
- `multi-tenancy-boundaries.md`
- `permission-and-folder-access-model.md`
- `file-processing-pipeline.md`

### G. docs/contributing/*（#226 の範囲外として別 source set で再棚卸し）
- `development-setup.md`
- `testing-and-quality-gates.md`
- `branch-and-release-workflow.md`

## packet 化向け comment anchor candidate
- `ledger.lifecycle`: `routes/tenant.php:46-95`, `app/Livewire/Ledger/Show.php`, `app/Livewire/Ledger/Import.php`, `tests/Feature/Livewire/Ledger/ShowTest.php`, `tests/Feature/Exports/LedgerExportTest.php`
- `workflow.rollback`: `app/Livewire/Workflow/PendingList.php`, `app/Livewire/Ledger/WorkflowActionButtons.php`, `tests/Feature/Livewire/Workflow/PendingListTest.php`, `tests/Feature/Ledger/RollbackIntegrationTest.php`
- `search.lookup`: `routes/api.php:34-35`, `app/Mcp/Tools/SearchLedgersTool.php`, `tests/Feature/Api/SearchApiTest.php`, `tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php`
- `attachments.inspector`: `app/Livewire/AttachedFile/FileInspector.php`, `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`, `tests/Feature/Jobs/ProcessVlmExtractionTest.php`
- `portal.navigation`: `app/Livewire/MyPortal.php`, `tests/Feature/Livewire/MyPortalTest.php`
- `notifications.announcements`: `app/Livewire/Notifications/NotificationList.php`, `tests/Feature/Livewire/Notifications/NotificationListTest.php`, `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- `folders.access`: `app/Livewire/Folder/Tree.php`, `app/Filament/Resources/FolderResource.php`, `tests/Feature/Filament/FolderResourceTest.php`
- `admin.rbac`: `app/Filament/Resources/RoleResource.php`, `app/Filament/Resources/UserResource.php`, `tests/Feature/Filament/RoleResourceTest.php`, `tests/Feature/Filament/UserResourceTest.php`
- `api.rest`: `routes/api.php:27-61`, `tests/Feature/Api/LedgerReadUpdateApiTest.php`, `tests/Feature/Api/BootstrapManifestApiTest.php`
- `mcp.contract`: `app/Mcp/Servers/LedgerLeapServer.php`, `app/Mcp/Resources/BootstrapClientResource.php`, `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php`, `tests/Feature/Mcp/BootstrapClientResourceTest.php`

## スプリント分解
- [x] source scan 対象ディレクトリと信号種別を確定する
  - Evidence: `routes/tenant.php`, `routes/api.php`, `app/Livewire/*`, `app/Filament/*`, `app/Mcp/*`, `tests/Feature/*`, `lang/ja/ledger/*`, `docs/function/*`, `docs/api/*`, `docs/architecture/*`
- [x] feature inventory テーブルを作成する
  - Evidence: 上記 `source-derived feature inventory`
- [x] current doc coverage gap を整理する
  - Evidence: 上記 `current doc coverage gap`
- [x] revised public doc target list v2 を作成する
  - Evidence: 上記 `#219 用 public doc target list v2`
- [x] comment anchor candidate list を作成する
  - Evidence: 上記 `packet 化向け comment anchor candidate`

## エビデンス / 参照先
- `routes/tenant.php:43-136`
- `routes/api.php:27-71`
- `tests/Feature/Livewire/Ledger/ShowTest.php`
- `tests/Feature/Livewire/Workflow/PendingListTest.php`
- `tests/Feature/Api/SearchApiTest.php`
- `tests/Feature/Api/BootstrapManifestApiTest.php`
- `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php`
- `tests/Feature/Livewire/AttachedFile/FileInspectorTest.php`
- `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- `tests/Feature/Filament/RoleResourceTest.php`

## 完了条件
- [x] source-derived inventory が作られている
- [x] public/internal 判定と doc type 候補が feature family ごとに整理されている
- [x] #219 用 target doc list v2 が作成されている
- [x] comment anchor candidate が後続 sprint で使える形に整理されている

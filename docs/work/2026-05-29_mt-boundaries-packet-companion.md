# Packet Companion: multi-tenancy-boundaries

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `arch-multi-tenancy-boundaries` |
| `feature_family` | Identity / RBAC / tenant administration |
| `doc_area` | architecture |
| `target_slug` | multi-tenancy-boundaries |
| `target_path` | `docs/architecture/multi-tenancy-boundaries.md` |
| `public_classification` | public |
| `source_status` | confirmed |
| `audience` | 利用者 / 管理者 / API 利用者 |
| `doc_type` | explanation |
| `doc_format_profile` | explanation |
| `comment_sync_policy` | optional (deferred) |
| `tracking_record_location` | `docs/work/2026-05-29_mt-boundaries-packet-companion.md` |
| `external_evidence_urls` | https://stancl.dev/tenancy/docs |

### Source inputs

- `source_paths`: `docs/architecture/multi-tenancy.md`, `app/Services/TenantAccessService.php`, `app/Models/Tenant.php`, `app/Livewire/Traits/InitializesTenantContext.php`, `routes/tenant.php`, `app/Providers/AppServiceProvider.php`
- `code_anchors`: `app/Services/TenantAccessService.php:17-35`, `app/Livewire/Traits/InitializesTenantContext.php:14-51`, `app/Providers/AppServiceProvider.php:95-102`
- `test_anchors`: `tests/Feature/Filament/TenantResourceTest.php`, `tests/Feature/Livewire/TenantSwitcherTest.php` (if exists)
- `comment_anchors`: `app/Services/TenantAccessService`, `app/Livewire/Traits/InitializesTenantContext`, `app/Models/Tenant`
- `must_exclude`: `docs/work/*`, `docs/architecture/multi-tenancy.md` の内部実装詳細（マルチDB試行の失敗経緯など）

## Comment sync triage

### Step 8a — Direct source classes

| Class | DocBlock status (before sync) | DocBlock status (after sync) |
|---|---|---|
| `App\Services\TenantAccessService` | **missing class-level** — 3/4 methods had summary but lacked `@param`/`@return` | ✅ class-level added, `@param`/`@return` completed |
| `App\Models\Tenant` | **missing class-level** — only had orphaned `$fillable` property doc | ✅ class-level summary added |
| `App\Livewire\Traits\InitializesTenantContext` | **fully undocumented** — trait + all 3 methods had zero PHPDoc | ✅ trait-level + resolveTenantId + initializeTenantContext + bootInitializesTenantContext added |
| `App\Filament\Resources\TenantResource` | Filament boilerplate (form/table schema only) — qualifies for exception per 8d | — (out of scope) |

### Step 8b — Indirect consumers (search: `TenantAccessService`)

| Class | Type | DocBlock status |
|---|---|---|
| `App\Livewire\TenantSwitcher` | Livewire | **missing** — no class-level summary |
| `App\Livewire\TenantSwitcherFilament` | Livewire | **missing** — no class-level summary |
| `App\Livewire\Folder\Tree` | Livewire | complete — class-level summary + method DocBlocks |
| `App\Http\Middleware\EnsureAuthenticatedUserHasCurrentTenantAccess` | Middleware | **missing** — no class-level summary |
| `App\Http\Controllers\GlobalMyPortalController` | Controller | **incomplete** — method-level present, no class-level |
| `App\Http\Controllers\Auth\AuthenticatedSessionController` | Controller | **incomplete** — method-level present, no class-level |
| `App\Observers\UserPermissionsObserver` | Observer | not checked (model event boilerplate) |
| `App\Observers\RoleFolderPermissionObserver` | Observer | not checked (model event boilerplate) |
| `App\Observers\FolderObserver` | Observer | not checked (model event boilerplate) |

### Step 8b — Indirect consumers (search: `InitializesTenantContext`)

22 Livewire components use this trait. Checked representative:
- `Folder/Tree` ✅ complete
- `Folder/FolderForm` — boilerplate form
- `Ledger/Show` — Ledger feature (not primary tenant boundary)
- Others — feature-specific Livewire, not primary tenant boundary

### Step 8c — Gate assessment

- Direct comment anchors all had **missing/incomplete** DocBlocks before sync.
- Indirect consumers (`TenantSwitcher`, `TenantSwitcherFilament`) also missing class-level DocBlocks.
- **Gate triggers → `comment_sync_policy` MUST be `optional` or `required`**.

### Decision: `optional` (partial deferral)

| Field | Value |
|---|---|
| `comment_sync_policy` | optional |
| `packet_comment_anchors_result` | ✅ Executed — all 3 direct comment anchors updated to meet PHPDoc minimum |
| `indirect_consumers_result` | ⏳ Deferred — `TenantSwitcher`, `TenantSwitcherFilament`, `EnsureAuthenticatedUserHasCurrentTenantAccess`, `GlobalMyPortalController`, `AuthenticatedSessionController` |
| `defer_reason` | Architecture explanation doc describing conceptual boundaries. The undocumented indirect consumers are UI/middleware components tangential to this doc's purpose. They should be addressed in a dedicated comment-sync run for `admin.rbac` family. |
| `deferred_to` | Dedicated comment-sync run for `admin.rbac` family |

## Comment sync execution log

| File | Symbol | Change |
|---|---|---|
| `app/Services/TenantAccessService.php` | `TenantAccessService` (class) | Added class-level summary (`@`ユーザーのテナントアクセス権限を管理するサービス`) |
| `app/Services/TenantAccessService.php` | `getAccessibleTenants()` | Added `@param User $user`, `@return Collection<int, Tenant>` |
| `app/Services/TenantAccessService.php` | `clearUserCache()` | Added `@param User $user` |
| `app/Services/TenantAccessService.php` | `getCacheKey()` | Added `@param User $user`, `@return string` |
| `app/Livewire/Traits/InitializesTenantContext.php` | `InitializesTenantContext` (trait) | Added trait-level summary |
| `app/Livewire/Traits/InitializesTenantContext.php` | `resolveTenantId()` | Added summary + `@param` + `@return` |
| `app/Livewire/Traits/InitializesTenantContext.php` | `initializeTenantContext()` | Added summary + `@param Tenancy $tenancy` |
| `app/Livewire/Traits/InitializesTenantContext.php` | `bootInitializesTenantContext()` | Added summary + `@param Tenancy $tenancy` |
| `app/Models/Tenant.php` | `Tenant` (class) | Added class-level summary; removed orphaned `$fillable` property doc |

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ | explanation profile: summary, problem, context, decision, tradeoffs, related_links |
| public target updated | ✅ | `docs/architecture/multi-tenancy-boundaries.md` created |
| source-derived scope respected | ✅ | Based on #226 inventory: public-facing boundary summary, no internal implementation history |
| evidence fields captured | ✅ | See this companion record |
| code / test anchors reflected | ✅ | TenantAccessService, InitializesTenantContext, routes, AppServiceProvider |
| comment sync handled | ✅ | optional — 3 direct comment anchors updated to PHPDoc minimum (9 symbol changes across 3 files). Indirect consumers deferred to `admin.rbac` dedicated run |
| traceability split captured | ✅ | Public body: no internal refs. Private: this file |
| unresolved risks recorded | ✅ | Comment sync on TenantSwitcher, TenantSwitcherFilament, EnsureAuthenticatedUserHasCurrentTenantAccess pending |

## Next backlog candidate

`docs/architecture/permission-and-folder-access-model.md` (priority 5 architecture, second unwritten target). Source: `docs/architecture/permission-system.md` (internal bug-fix report) needs public-facing rewrite.

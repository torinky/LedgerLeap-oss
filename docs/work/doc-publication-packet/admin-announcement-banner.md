# Packet: admin-announcement-banner

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `admin-announcement-banner` |
| `feature_family` | Notifications / history / access / announcements |
| `doc_area` | `docs/admin/` |
| `target_slug` | `admin-announcement-banner` |
| `target_path` | `docs/admin/admin-announcement-banner.md` |
| `public_classification` | public |
| `source_status` | `confirmed` |
| `audience` | 管理者 (system administrators and operators) |
| `doc_type` | `reference` |
| `doc_format_profile` | `reference` |
| `comment_sync_policy` | `optional` |
| `tracking_record_location` | `docs/work/doc-publication-packet/admin-announcement-banner.md` |
| `external_evidence_urls` | (none — source-derived from repo) |
| `last_confirmed_at` | 2026-05-29 |
| `recheck_after` | `90d` |

### Source inputs

- `source_paths`:
  - `app/Filament/Resources/AdminAnnouncementResource.php` — main Filament CRUD resource (table, form, pages)
  - `app/Filament/Pages/AdminAnnouncementBannerIndex.php` — custom legacy index page
  - `app/Filament/Pages/AdminAnnouncementBannerSettings.php` — custom legacy settings page with preview
  - `app/Services/AdminAnnouncementService.php` — core service (normalization, active check, config fallback)
  - `app/Models/AdminAnnouncement.php` — database model (lifecycle, revision, casts)
  - `resources/views/components/admin/announcement-banner.blade.php` — banner Blade component
  - `resources/views/filament/pages/admin-announcement-banner-settings.blade.php`
  - `resources/views/filament/pages/admin-announcement-banner-index.blade.php`
- `code_anchors`:
  - `app/Filament/Resources/AdminAnnouncementResource.php` — table columns, form schema, status display helpers, scope options, permission gates, CTA link formatting
  - `app/Filament/Pages/AdminAnnouncementBannerSettings.php` — form schema, header actions, lifecycle methods, validation
  - `app/Filament/Pages/AdminAnnouncementBannerIndex.php` — index page, create action, view data
  - `app/Services/AdminAnnouncementService.php` — announcement normalization, active check, config fallback
  - `app/Models/AdminAnnouncement.php` — status lifecycle, revision hash, scope/links casts
- `test_anchors`:
  - `tests/Feature/Views/AdminAnnouncementBannerTest.php`
- `comment_anchors`:
  - `app/Services/AdminAnnouncementService.php` — class + `currentAnnouncement()` + `notificationCenterAnnouncements()` lack DocBlocks
  - `app/Models/AdminAnnouncement.php` — class + `isCurrentlyVisible()` + `displayStatusKey()` + `refreshRevision()` lack DocBlocks
  - `app/Filament/Pages/AdminAnnouncementBannerSettings.php` — class + `saveDraft()` / `publishAnnouncement()` / `archiveAnnouncement()` lack DocBlocks
  - `app/Filament/Resources/AdminAnnouncementResource.php` — class + `toLinksPayload()` / `canViewAny()` lack DocBlocks
  - (indirect: `NotificationList::refreshAnnouncements()` missing one; `Icon::refreshCounts()` and `NotificationController::index()` already acceptable)
- `must_exclude`: `docs/work/*`, private issue numbers, internal tracking metadata
- `done_when`: public doc written, companion record complete, format profile followed

## Packet handoff

- Packet: `admin-announcement-banner`
- Goal: Document the admin-managed announcement banner lifecycle (draft → published → archived), form fields, rendering behavior, and constraints for system administrators
- Publish target: `docs/admin/admin-announcement-banner.md`
- Reader + doc_type: administrators / reference
- Format profile: reference
- Required sections: `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources`
- Optional sections: `examples`, `failure_modes`, `change_history`
- Source summary:
  - Main Filament resource: AdminAnnouncementResource (table + form + CRUD pages with status display helpers, scope options, permission gates)
  - Two legacy Filament pages: AdminAnnouncementBannerIndex (list + create entry) and AdminAnnouncementBannerSettings (form + preview)
  - AdminAnnouncementService normalizes announcements from database model or config fallback, with time-window active check
  - AdminAnnouncement model has casts for scope/links arrays, datetime fields, auto-generated revision hash, and display status derivation
  - Blade banner component renders in app layout with Alpine.js dismissal via localStorage
  - Indirect consumers: NotificationList, Icon, NotificationController all call AdminAnnouncementService for banner counts
  - Three status states: draft, published, archived; three levels: info, warning, critical
- External evidence URLs:
  - (none — source-derived from LedgerLeap repo)
- Freshness:
  - `last_confirmed_at`: 2026-05-29
  - `recheck_after`: 90d
- Traceability split:
  - `tracking_record_location`: `docs/work/doc-publication-packet/admin-announcement-banner.md`
  - `private_reference_map`: #226 (source inventory), #219 (public doc umbrella)
  - `public_reference_targets`: `docs/admin/admin-announcement-banner.md`
- Required anchors:
  - code: AdminAnnouncementBannerSettings, AdminAnnouncementBannerIndex, AdminAnnouncementService, AdminAnnouncement
  - test: AdminAnnouncementBannerTest
  - comment: (not applicable)
- Style guardrails:
  - Match existing admin doc style (English headings, table-first surface description, Effects/Constraints pattern)
  - active voice, admin-audience terminology
  - Do not repeat features/notifications-history-and-announcements.md; link instead
- Comment sync scope:
  - `optional` — core service (`AdminAnnouncementService`) and model (`AdminAnnouncement`) have undocumented public methods directly described in the doc (lifecycle, active check, announcement normalization). Filament Resource/Page classes are boilerplate rendering methods and lower priority. Indirect consumers (`NotificationList`, `Icon`, `NotificationController`) are in acceptable shape.
- PHPDoc minimum:
  - `AdminAnnouncementService`: class-level short summary, `@return` for `currentAnnouncement()` and `notificationCenterAnnouncements()`
  - `AdminAnnouncement`: class-level short summary, `@return string` for `displayStatusKey()`, `@return bool` for `isCurrentlyVisible()`
  - `AdminAnnouncementBannerSettings`: short summaries on `saveDraft()`, `publishAnnouncement()`, `archiveAnnouncement()`
- Must exclude:
  - `docs/work/*` references
  - private issue numbers
  - packet tracking metadata
  - internal service method details beyond what the admin needs to know
- Internal-only references removed from public body:
  - (none present)
- Open questions:
  - (none)
- Unresolved risks:
  - (none)
- Done when:
  - [x] `docs/admin/admin-announcement-banner.md` written
  - [x] Format profile (reference) applied
  - [x] Required sections present
  - [x] Companion record written
  - [x] Source-derived scope respected

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ | `reference` profile: summary, contract_or_surface (Admin Surface with index/settings pages, field table, header actions table), parameters_or_fields (detailed field descriptions), responses_or_effects (Lifecycle diagram, Banner Rendering, Dismissal Behavior), constraints (7 constraints), related_sources |
| public target updated | ✅ | `docs/admin/admin-announcement-banner.md` created (previously missing) |
| source-derived scope respected | ✅ | Two Filament pages, one service, one model, Blade components, one test file — all confirmed in #226 inventory |
| evidence fields captured | ✅ | `last_confirmed_at`, `recheck_after`, `external_evidence_urls`, `source_anchor`, `comment_sync_decision`, `tracking_record_location` all present |
| code / test anchors reflected | ✅ | Page classes, service, model and AdminAnnouncementBannerTest referenced in source inputs |
| comment sync handled | ✅ | `optional` → executed. Added class-level + method DocBlocks across 4 files (see table below) |
| traceability split captured | ✅ | companion record at `docs/work/doc-publication-packet/admin-announcement-banner.md` |
| unresolved risks recorded | ✅ | none identified |

- Done when:
  - [x] packet target が更新済み
  - [x] `doc_format_profile` と required sections が handoff / acceptance に残っている
  - [x] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` が残っている
  - [x] `tracking_record_location` / traceability split が handoff / acceptance に残っている
  - [x] acceptance table が埋まっている
  - [x] comment sync 判定が残っている
  - [x] comment sync 実行済み (2026-05-29)

### Comment sync execution log

| File | Symbols changed | What was done |
|---|---|---|
| `app/Services/AdminAnnouncementService.php` | class, `currentAnnouncement()`, `notificationCenterAnnouncements()` | Added class PHPDoc; added `@return` to both public methods |
| `app/Models/AdminAnnouncement.php` | class, `isCurrentlyVisible()`, `displayStatusKey()`, `refreshRevision()` | Added class PHPDoc; added `@return` to all three public methods |
| `app/Filament/Pages/AdminAnnouncementBannerSettings.php` | class, `saveDraft()`, `publishAnnouncement()`, `archiveAnnouncement()` | Added class PHPDoc; added `@return void` to three lifecycle methods |
| `app/Filament/Resources/AdminAnnouncementResource.php` | class, `toLinksPayload()` | Added class PHPDoc; added `@param`/`@return` to `toLinksPayload()` |
| `app/Livewire/Notifications/Icon.php` | skipped | `refreshCounts()` already has a DocBlock |
| `app/Livewire/Notifications/NotificationList.php` | skipped | Class-level DocBlock exists; `refreshAnnouncements()` is simple delegation |
| `app/Http/Controllers/NotificationController.php` | skipped | `index()` already has a DocBlock |
  - [x] 次 sprint が迷わない handoff が残っている

## Next backlog candidate

After `admin-announcement-banner`, all admin targets are complete. Remaining missing targets from #226 v2:
1. `docs/architecture/multi-tenancy-boundaries.md` (priority 5)
2. `docs/architecture/permission-and-folder-access-model.md` (priority 5)
3. `docs/architecture/file-processing-pipeline.md` (priority 5)

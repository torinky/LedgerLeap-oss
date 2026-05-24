# Packet record — portal-and-navigation

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `portal-and-navigation` |
| `feature_family` | `my-portal-navigation` |
| `doc_area` | `docs/getting-started` |
| `target_slug` | `portal-and-navigation` |
| `target_path` | `docs/getting-started/portal-and-navigation.md` |
| `public_classification` | `public` |
| `source_status` | `confirmed` |
| `audience` | `end-user` |
| `doc_type` | `tutorial` |
| `doc_format_profile` | `tutorial` |
| `comment_sync_policy` | `not_applicable` |
| `external_evidence_urls` | `https://diataxis.fr/`, `https://kubernetes.io/docs/contribute/style/page-templates/` |
| `last_confirmed_at` | `2026-05-24` |
| `recheck_after` | `90d` |

### Source inputs

- `source_paths`
  - `routes/web.php`
  - `routes/tenant.php`
  - `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - `app/Http/Controllers/GlobalMyPortalController.php`
  - `resources/views/my-portal.blade.php`
  - `resources/views/livewire/my-portal.blade.php`
- `code_anchors`
  - `Route::get('/my-portal', [GlobalMyPortalController::class, 'index'])`
  - `Route::get('/my-portal', MyPortal::class)->name('my-portal')`
  - `AuthenticatedSessionController::store()`
  - `GlobalMyPortalController::index()`
- `test_anchors`
  - `tests/Feature/Livewire/MyPortalTest.php`
- `comment_anchors`
  - none
- `must_exclude`
  - Livewire internal state preparation details
  - permission repository implementation details
  - retrospective and work-note references
- `done_when`
  - public tutorial exists
  - tenant selection and portal handoff are documented
  - packet evidence is captured

## Packet handoff

- Packet: `portal-and-navigation`
- Goal: ログイン直後の導線とフォルダから台帳一覧への基本遷移を、利用者向けに短く説明する
- Publish target: `docs/getting-started/portal-and-navigation.md`
- Reader + doc_type: `end-user` + `tutorial`
- Format profile: `tutorial`
- Required sections:
  - `summary`
  - `goal`
  - `prerequisites`
  - `steps`
  - `verification`
  - `next_steps`
- Optional sections:
  - `troubleshooting`
  - `related_links`
- Source summary:
  - ログイン後は `RouteServiceProvider::HOME` と `AuthenticatedSessionController::store()` により My Portal が既定遷移先になる
  - 1 テナント所属なら自動遷移、複数所属なら `resources/views/my-portal.blade.php` の選択画面を表示する
  - tenant 内の `resources/views/livewire/my-portal.blade.php` で通知、承認待ち、所属、フォルダ handoff を確認できる
- External evidence URLs:
  - `https://diataxis.fr/`
  - `https://kubernetes.io/docs/contribute/style/page-templates/`
- Freshness:
  - `last_confirmed_at`: `2026-05-24`
  - `recheck_after`: `90d`
- Required anchors:
  - code: `routes/web.php`, `routes/tenant.php`, `AuthenticatedSessionController::store()`, `GlobalMyPortalController::index()`
  - test: `tests/Feature/Livewire/MyPortalTest.php`
  - comment: none
- Style guardrails:
  - learner-first
  - action verbs
  - no internal class names in the user-facing body
- Comment sync scope:
  - `not_applicable`
  - defer reason: この packet は WebUI 上の利用導線を説明するもので、source-of-truth は既存 route/view/test anchor で十分に追跡できる
- PHPDoc minimum:
  - none
- Must exclude:
  - Livewire property 名
  - docs/work history
  - permission cache の内部説明
- Open questions:
  - none
- Unresolved risks:
  - tenant switcher 自体の操作説明は別 packet に切り出す余地がある
- Done when:
  - [x] target tutorial が追加されている
  - [x] tenant selection と folder handoff が説明されている
  - [x] packet evidence が残っている

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ | `tutorial` profile、required sections を handoff に記録 |
| public target updated | ✅ | `docs/getting-started/portal-and-navigation.md` |
| source-derived scope respected | ✅ | route/controller/view/test anchor のみ使用 |
| evidence fields captured | ✅ | manifest / handoff に `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, `source_anchor` を記録 |
| code / test anchors reflected | ✅ | `routes/web.php`, `routes/tenant.php`, `AuthenticatedSessionController`, `GlobalMyPortalController`, `tests/Feature/Livewire/MyPortalTest.php` |
| comment sync handled | ✅ | `not_applicable` と defer reason を記録 |
| unresolved risks recorded | ✅ | tenant switcher 詳細は別 packet 候補として明記 |

- Done when:
  - [x] packet target が更新済み
  - [x] `doc_format_profile` と required sections が handoff / acceptance に残っている
  - [x] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` が残っている
  - [x] acceptance table が埋まっている
  - [x] comment sync 判定が残っている
  - [x] 次 sprint が迷わない handoff が残っている

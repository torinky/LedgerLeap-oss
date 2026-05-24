# Packet record — search-api

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `search-api` |
| `feature_family` | `rest-search-contract` |
| `doc_area` | `docs/api` |
| `target_slug` | `search-api` |
| `target_path` | `docs/api/search-api.md` |
| `public_classification` | `public` |
| `source_status` | `confirmed` |
| `audience` | `developer` |
| `doc_type` | `reference` |
| `doc_format_profile` | `reference` |
| `comment_sync_policy` | `required` |
| `external_evidence_urls` | `https://diataxis.fr/`, `https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/`, `https://docs.phpdoc.org/guide/guides/docblocks.html` |
| `last_confirmed_at` | `2026-05-24` |
| `recheck_after` | `90d` |

### Source inputs

- `source_paths`
  - `routes/api.php`
  - `app/Http/Controllers/Api/V1/SearchController.php`
  - `app/Http/Requests/Api/V1/SearchRequest.php`
  - `docs/api/JAPANESE_SEARCH_GUIDE.md`
- `code_anchors`
  - `GET /api/v1/search`
  - `POST /api/v1/search`
  - `SearchController::search()`
  - `SearchRequest::rules()`
- `test_anchors`
  - `tests/Feature/Api/SearchApiTest.php`
  - `tests/Feature/Search/SearchControllerAdditionalTest.php`
- `comment_anchors`
  - `app/Http/Controllers/Api/V1/SearchController.php`
  - `app/Http/Requests/Api/V1/SearchRequest.php`
- `must_exclude`
  - MCP response envelope details
  - internal debug log implementation
  - service-layer optimization details
- `done_when`
  - REST search reference exists
  - REST/MCP boundary is explicit
  - bounded comment sync is applied to source anchors

## Packet handoff

- Packet: `search-api`
- Goal: `/api/v1/search` の REST 契約を、MCP 検索ツールと混ぜずに参照できる形へ分離する
- Publish target: `docs/api/search-api.md`
- Reader + doc_type: `developer` + `reference`
- Format profile: `reference`
- Required sections:
  - `summary`
  - `contract_or_surface`
  - `parameters_or_fields`
  - `responses_or_effects`
  - `constraints`
  - `related_sources`
- Optional sections:
  - `examples`
  - `failure_modes`
- Source summary:
  - REST Search API は `routes/api.php` で `GET` と `POST` の両方を公開する
  - `SearchController::search()` が検索結果と count mode を返す
  - `SearchRequest` が REST 側の入力パラメータを検証する
  - 日本語・マルチバイト文字は `POST` と `docs/api/JAPANESE_SEARCH_GUIDE.md` を優先導線にする
- External evidence URLs:
  - `https://diataxis.fr/`
  - `https://docs.djangoproject.com/en/stable/internals/contributing/writing-documentation/`
  - `https://docs.phpdoc.org/guide/guides/docblocks.html`
- Freshness:
  - `last_confirmed_at`: `2026-05-24`
  - `recheck_after`: `90d`
- Required anchors:
  - code: `routes/api.php`, `SearchController::search()`, `SearchRequest::rules()`
  - test: `tests/Feature/Api/SearchApiTest.php`, `tests/Feature/Search/SearchControllerAdditionalTest.php`
  - comment: class docblocks on `SearchController` and `SearchRequest`
- Style guardrails:
  - contract-first
  - REST と MCP を混ぜない
  - examples は現実的な curl のみに留める
- Comment sync scope:
  - `required`
  - source anchors only: `SearchController`, `SearchRequest`
  - explicit exclude: `SearchLedgersTool` の MCP envelope 詳細
- PHPDoc minimum:
  - class summary on `SearchController`
  - class summary on `SearchRequest`
- Must exclude:
  - MCP attachment payload contract
  - debug log timing
  - search internals below request/controller boundary
- Open questions:
  - none
- Unresolved risks:
  - search の完全な response schema は引き続き `openapi.json` を正本とする
- Done when:
  - [x] target reference が追加されている
  - [x] REST/MCP 境界が明文化されている
  - [x] bounded comment sync が source anchor に限定されている

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ | `reference` profile、required sections を handoff に記録 |
| public target updated | ✅ | `docs/api/search-api.md` |
| source-derived scope respected | ✅ | route/controller/request/test anchor のみ使用 |
| evidence fields captured | ✅ | manifest / handoff に `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, `source_anchor` を記録 |
| code / test anchors reflected | ✅ | `routes/api.php`, `SearchController`, `SearchRequest`, `tests/Feature/Api/SearchApiTest.php`, `tests/Feature/Search/SearchControllerAdditionalTest.php` |
| comment sync handled | ✅ | `SearchController` と `SearchRequest` に bounded class docblock を追加 |
| unresolved risks recorded | ✅ | schema 正本は `openapi.json` と明記 |

- Done when:
  - [x] packet target が更新済み
  - [x] `doc_format_profile` と required sections が handoff / acceptance に残っている
  - [x] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` が残っている
  - [x] acceptance table が埋まっている
  - [x] comment sync 判定が残っている
  - [x] 次 sprint が迷わない handoff が残っている

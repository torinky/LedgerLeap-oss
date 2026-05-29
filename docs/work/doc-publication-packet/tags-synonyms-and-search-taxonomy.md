# Packet: tags-synonyms-and-search-taxonomy

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `admin-tags-synonyms-search-taxonomy` |
| `feature_family` | Search / lookup / taxonomy |
| `doc_area` | `docs/admin/` |
| `target_slug` | `tags-synonyms-and-search-taxonomy` |
| `target_path` | `docs/admin/tags-synonyms-and-search-taxonomy.md` |
| `public_classification` | public |
| `source_status` | `confirmed` |
| `audience` | 管理者 (system administrators and operators) |
| `doc_type` | `reference` |
| `doc_format_profile` | `reference` |
| `comment_sync_policy` | `not_applicable` |
| `tracking_record_location` | `docs/work/doc-publication-packet/tags-synonyms-and-search-taxonomy.md` |
| `external_evidence_urls` | (none — source-derived from repo) |
| `last_confirmed_at` | 2026-05-29 |
| `recheck_after` | `90d` |

### Source inputs

- `source_paths`:
  - `app/Filament/Resources/TagResource.php`
  - `app/Filament/Resources/Synonym/WordResource.php`
  - `app/Filament/Resources/Synonym/TansiResource.php`
  - `app/Filament/Resources/Synonym/TechnicalTermGroupResource.php`
  - `app/Models/Tag.php`
  - `app/Models/Synonym/Word.php`
  - `app/Models/Synonym/Tansi.php`
  - `app/Models/Synonym/TechnicalTermGroup.php`
- `code_anchors`:
  - `app/Filament/Resources/TagResource.php` — table/form/relation definition
  - `app/Filament/Resources/Synonym/*Resource.php` — synonym resource definitions
  - `app/Models/Synonym/Tansi.php` — tansi connection and table
  - `app/Models/Synonym/Word.php` — wordnet connection and synonyms relationship
  - `app/Models/Synonym/TechnicalTermGroup.php` — JSON cast synonyms and boot events
  - `app/Models/Tag.php` — BelongsToTenant, ledger define relationship
- `test_anchors`:
  - `tests/Feature/Filament/TagResourceTest.php`
- `comment_anchors`: (none — comment sync not applicable)
- `must_exclude`: `docs/work/*`, private issue numbers, internal tracking metadata
- `done_when`: public doc written, companion record complete, format profile followed

## Packet handoff

- Packet: `admin-tags-synonyms-search-taxonomy`
- Goal: Document the admin-managed search taxonomy surface (tags, WordNet synonyms, Tansi synonyms, technical term groups) for system administrators
- Publish target: `docs/admin/tags-synonyms-and-search-taxonomy.md`
- Reader + doc_type: administrators / reference
- Format profile: reference
- Required sections: `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources`
- Optional sections: `examples`, `failure_modes`, `change_history`
- Source summary:
  - Four Filament resources: TagResource, WordResource (WordNet), TansiResource, TechnicalTermGroupResource
  - Tag is tenant-scoped via BelongsToTenant; Tansi/WordNet are separate DB connections
  - Technical term groups store JSON synonym arrays via AsJson cast
  - All three layers (tags, synonyms, technical terms) feed into the search system
- External evidence URLs:
  - (none — source-derived from LedgerLeap repo)
- Freshness:
  - `last_confirmed_at`: 2026-05-29
  - `recheck_after`: 90d
- Traceability split:
  - `tracking_record_location`: `docs/work/doc-publication-packet/tags-synonyms-and-search-taxonomy.md`
  - `private_reference_map`: #226 (source inventory), #219 (public doc umbrella)
  - `public_reference_targets`: `docs/admin/tags-synonyms-and-search-taxonomy.md`
- Required anchors:
  - code: TagResource, WordResource, TansiResource, TechnicalTermGroupResource
  - test: TagResourceTest
  - comment: (not applicable)
- Style guardrails:
  - Match existing admin doc style (English headings, table-first surface description, Effects/Constraints pattern)
  - active voice, admin-audience terminology
  - Do not repeat architecture/tag-design.md or features/search-and-lookup.md; link instead
- Comment sync scope:
  - `not_applicable` — Filament Resource classes are boilerplate rendering methods; model classes follow Laravel conventions and don't benefit from additional PHPDoc for public doc purposes
- PHPDoc minimum:
  - (not applicable)
- Must exclude:
  - `docs/work/*` references
  - private issue numbers
  - packet tracking metadata
  - internal DB connection details beyond what the admin needs to know
- Internal-only references removed from public body:
  - (none present)
- Open questions:
  - (none)
- Unresolved risks:
  - (none)
- Done when:
  - [x] `docs/admin/tags-synonyms-and-search-taxonomy.md` written
  - [x] Format profile (reference) applied
  - [x] Required sections present
  - [x] Companion record written
  - [x] Source-derived scope respected

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ | `reference` profile: summary, contract_or_surface (Admin Surface + resource tables), parameters_or_fields (field details per resource), responses_or_effects (Effects section), constraints (Constraints section), related_sources |
| public target updated | ✅ | `docs/admin/tags-synonyms-and-search-taxonomy.md` created (previously missing) |
| source-derived scope respected | ✅ | Four Filament resources, four model classes, one test file — all confirmed in #226 inventory |
| evidence fields captured | ✅ | `last_confirmed_at`, `recheck_after`, `external_evidence_urls`, `source_anchor`, `comment_sync_decision`, `tracking_record_location` all present |
| code / test anchors reflected | ✅ | Resource definitions and TagResourceTest referenced in source inputs |
| comment sync handled | ✅ | `not_applicable` — Filament boilerplate and Laravel convention models |
| traceability split captured | ✅ | companion record at `docs/work/doc-publication-packet/tags-synonyms-and-search-taxonomy.md` |
| unresolved risks recorded | ✅ | none identified |

- Done when:
  - [x] packet target が更新済み
  - [x] `doc_format_profile` と required sections が handoff / acceptance に残っている
  - [x] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` が残っている
  - [x] `tracking_record_location` / traceability split が handoff / acceptance に残っている
  - [x] acceptance table が埋まっている
  - [x] comment sync 判定が残っている
  - [x] 次 sprint が迷わない handoff が残っている

## Next backlog candidate

After `tags-synonyms-and-search-taxonomy`, the remaining missing targets from #226 v2 are:
1. `docs/admin/admin-announcement-banner.md` (priority 4)
2. `docs/architecture/multi-tenancy-boundaries.md` (priority 5)
3. `docs/architecture/permission-and-folder-access-model.md` (priority 5)
4. `docs/architecture/file-processing-pipeline.md` (priority 5)

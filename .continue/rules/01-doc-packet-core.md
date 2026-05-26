---
name: Doc Packet Core
globs:
  - "docs/**/*.md"
  - "docs/work/**/*.md"
  - ".github/**/*.md"
description: LedgerLeap publication packet guardrails for inventory refresh and one-packet rewrite work.
alwaysApply: false
---

- Treat one packet as one target public file.
- Start from #226 inventory and #227 packet contract before changing packet scope.

## Mandatory Pre-Flight Packet Contract Gate (from #227)

**BEFORE any prose is written**, confirm ALL of these fields are populated in the packet handoff. If any field is missing, STOP — do not proceed to writing.

| Field | Gate |
|---|---|
| `packet_id` | `<doc_area>.<target_slug>` |
| `feature_family` | From #226 normalized families |
| `doc_area` | One of 5 default areas |
| `target_path` | Derived from `<doc_area>/<target_slug>.md` |
| `public_classification` | One of 6 allowed values |
| `source_status` | `confirmed` or `provisional` |
| `audience` | Single primary reader |
| `doc_type` | `tutorial` / `how-to` / `reference` / `explanation` |
| `doc_format_profile` | Matches doc_type; required/optional sections copied |
| `source_paths` | 1–5 paths |
| `code_anchors` | 2–8 in `path:line` format |
| `test_anchors` | 1–5 in `path:line` format |
| `comment_anchors` | 0–5; empty only if `not_applicable` |
| `comment_sync_policy` | `required` / `optional` / `not_applicable` |
| `must_exclude` | 1–6 items |
| `external_evidence_urls` | At least 1 URL |
| `last_confirmed_at` | Date |
| `recheck_after` | Duration (default `90d`) |
| `done_when` | 3–5 checkable items |

### Gemma4 26B budget limits

`source_paths` ≤ 5, `code_anchors` ≤ 8, `test_anchors` ≤ 5, `comment_anchors` ≤ 5, `must_exclude` ≤ 6, `done_when` ≤ 5. Summary ≤ 600 chars or 8 bullets.

## Post-Gate Writing Rules

- Fix one `doc_format_profile` before writing and use the shared required/optional section matrix from `docs/templates/doc-publication-packet-template.md`.
- Capture packet evidence in a stable shape: `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, `source_anchor`, and `comment_sync_decision`.
- Record `style_guardrails` from the selected profile so examples and wording stay realistic.
- Keep REST API and MCP contract as separate packet lanes.
- Keep `docs/contributing/*` provisional unless a dedicated source set proves it.
- Return summary-first handoff data and avoid raw source dumps in the main conversation.
- When rewriting, stay with one writer and one target file.
- Do not embed packet tracking metadata in the public doc body — keep it in the handoff record.
- Use `docs/templates/doc-publication-packet-template.md` for handoff and acceptance.

## Post-Generation Validation

Before claiming done, verify: section compliance per format profile, code_anchors resolve to existing files, test_anchors resolve to existing files, must_exclude terms absent from doc body, freshness dates within window, comment sync decision recorded.

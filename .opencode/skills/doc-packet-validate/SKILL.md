---
name: doc-packet-validate
description: Validates a generated public doc against the #227 publication packet schema. Checks section structure, anchor presence, must_exclude compliance, freshness, and comment sync policy. Use after any doc-publication-audit rewrite or when reviewing existing docs for packet readiness.
compatibility: LedgerLeap
metadata:
  audience: maintainers
  workflow: doc-publication
---

# doc-packet-validate

## When to use

- A public doc was just generated or rewritten and you need to verify #227 contract compliance.
- You are reviewing an existing doc for packet readiness before a sprint pilot.
- A `doc-publication-audit` run completed and you need an independent compliance check.

## Routing Boundary

- This skill is **read-only validation** — it does not modify any files.
- Run this after a `doc-publication-audit` rewrite to confirm the output meets the packet contract.
- If validation fails, hand back to `doc-publication-audit` with the specific missing fields.
- If the packet baseline itself is stale, hand back to `doc-source-inventory`.

## Reference Templates

- `docs/templates/doc-publication-packet-template.md` — authoritative manifest, handoff, and acceptance shape.
- `docs/runbooks/doc-publication-packet-playbook.md` — operator flow with format profile matrix.
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md` — #227 canonical packet schema.

## Validation Checklist

Run every check below against the target doc. Report each result as `PASS`, `FAIL`, or `SKIP (reason)`.

### 1. Section compliance (from `doc_format_profile`)

For the doc's declared `doc_format_profile`, verify ALL required sections are present as top-level headings:

| Profile | Required sections |
|---|---|
| `tutorial` | `summary`, `goal`, `prerequisites`, `steps`, `verification`, `next_steps` |
| `how-to` | `summary`, `goal`, `prerequisites`, `procedure`, `verification` |
| `reference` | `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources` |
| `explanation` | `summary`, `problem`, `context`, `decision`, `tradeoffs`, `related_links` |

Section names are stable tokens — headings in the doc may use audience-appropriate wording but must map 1:1 to these tokens.

### 2. Anchor resolution

- `code_anchors`: Each `path:line` must resolve to an existing file. Verify at least 2 are present.
- `test_anchors`: Each `path:line` must resolve to an existing file. Verify at least 1 is present.
- `source_paths`: Each path must resolve to an existing file or directory. Verify 1–5 are present.

### 3. Must-exclude compliance

- Search the doc body for every `must_exclude` term or phrase.
- Report any occurrence as a violation.
- Checks are case-insensitive substring matches.

### 4. Freshness

- `last_confirmed_at` must be a parseable date within the `recheck_after` window.
- `external_evidence_urls` must have at least 1 reachable URL.

### 5. Comment sync policy

- If `comment_sync_policy` is `required`: `comment_anchors` must be non-empty and at least 1 anchor must be synced.
- If `comment_sync_policy` is `optional`: record the defer reason or confirm sync status.
- If `comment_sync_policy` is `not_applicable`: `comment_anchors` may be empty; reason must be recorded.

### 6. Gemma4 budget check

- `source_paths` ≤ 5, `code_anchors` ≤ 8, `test_anchors` ≤ 5
- `comment_anchors` ≤ 5, `must_exclude` ≤ 6, `done_when` ≤ 5

## Output Format

Return a structured validation report:

```markdown
## Packet validation: `<packet_id>`

| Check | Result | Detail |
|---|---|---|
| Section compliance | PASS / FAIL | Missing: `...` |
| Code anchors (≥2) | PASS / FAIL | Resolved: N, Unresolved: `path:line` |
| Test anchors (≥1) | PASS / FAIL | Resolved: N |
| Must-exclude compliance | PASS / FAIL | Violations found: `...` |
| Freshness | PASS / FAIL | `last_confirmed_at` is ..., window: ... |
| Comment sync | PASS / FAIL / SKIP | Policy: ..., Status: ... |
| Gemma4 budget | PASS / FAIL | Over-budget fields: `...` |

Overall: PASS / FAIL (N checks passed, M failed)
```

## Core Rules

- Do not modify any files. This skill is read-only.
- If the packet record (handoff/acceptance/manifest) is missing, flag it and stop — do not guess field values from the doc body alone.
- If `doc_format_profile` was not declared before writing, flag it as a contract violation regardless of section coverage.
- Report exactly which fields are missing; do not silently fill gaps.

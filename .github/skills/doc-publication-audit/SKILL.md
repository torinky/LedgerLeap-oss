---
name: doc-publication-audit
description: Audits mixed internal/public documentation and rewrites one file at a time into stable public-facing docs. Use when docs/work, development notes, or issue plans need to become external docs with a consistent feature-to-doc mapping.
compatibility: LedgerLeap (`docs/`, `docs/work/`, `.github/skills/`, `.github/prompts/`)
---

# doc-publication-audit

## When to use

- A packet handoff already exists and one target file must be rewritten with bounded comment sync.
- Existing docs mix internal work notes and public-facing content.
- A manual, file-by-file rewrite is required because the source cannot be converted mechanically.

## Routing Boundary

- This skill is the **single-packet executor**, not the router.
- If lane selection or packet handoff is still unclear, start with [doc-publication-packet](../../prompts/doc-publication-packet.prompt.md).
- If #226 baseline / packet readiness needs re-check, hand back to [doc-source-inventory](../doc-source-inventory/SKILL.md).
- Use this skill only after `packet_id`, `target_path`, `doc_format_profile`, and `comment_sync_policy` are fixed.

## Reference Templates

- Use [User-facing template](references/public-doc-user-template.md) for setup guides, feature guides, troubleshooting.
- Use [Developer-facing template](references/public-doc-developer-template.md) for architecture notes, config references, API/MCP pages.
- Use [Placement guide](references/public-doc-placement-guide.md) to decide where content belongs.
- Use [Packet execution asset summary](references/packet-execution-assets.md) for OpenCode / Continue / LM Studio adapter facts.
- Use [Public/private traceability guidance](references/public-private-traceability.md) when internal worklogs or issue IDs must stay private.
- Use [Issue #230 evidence](../../../docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md) for `doc_format_profile`, evidence-field, PHPDoc minimum rules.
- Use [Doc publication packet template](../../../docs/templates/doc-publication-packet-template.md) for packet manifest, handoff, and acceptance.
- Use [Doc publication packet playbook](../../../docs/runbooks/doc-publication-packet-playbook.md) for the operator flow and adapter entrypoints.

## Mandatory Pre-Flight Packet Contract Gate (from #227)

**BEFORE any prose is written**, read the packet handoff and confirm ALL mandatory fields are populated. If any field is missing or `???`, **STOP** — do not proceed to writing until the field is resolved from the packet handoff, upstream inventory, or the user.

### Gate checklist (every field must be non-empty before writing begins)

| Field | Gate |
|---|---|
| `packet_id` | Must match `<doc_area>.<target_slug>` |
| `feature_family` | Must be one of #226's normalized families |
| `doc_area` | Must be one of the 5 default areas (+ `contributing` if provisional) |
| `target_path` | Must derive from `<doc_area>/<target_slug>.md` |
| `public_classification` | Must be one of the 6 allowed values |
| `source_status` | Must be `confirmed` or `provisional` |
| `audience` | Must be a single primary reader |
| `doc_type` | Must be `tutorial` / `how-to` / `reference` / `explanation` |
| `doc_format_profile` | Must match `doc_type`; required/optional sections copied from template |
| `source_paths` | Must have 1–5 paths from #226 inventory or existing docs |
| `code_anchors` | Must have 2–8 items in `path:line` format |
| `test_anchors` | Must have 1–5 items in `path:line` format |
| `comment_anchors` | Must have 0–5 items; empty only if `comment_sync_policy: not_applicable` |
| `comment_sync_policy` | Must be `required` / `optional` / `not_applicable` |
| `must_exclude` | Must have 1–6 items listing internal-only detail to suppress |
| `external_evidence_urls` | Must have at least 1 major OSS or official doc reference URL |
| `last_confirmed_at` | Must be a date (freshness checkpoint) |
| `recheck_after` | Must be a duration string (default `90d`) |
| `done_when` | Must have 3–5 checkable acceptance items |

### Gemma4 26B field budget (from #227)

- `source_paths` ≤ 5, `code_anchors` ≤ 8, `test_anchors` ≤ 5
- `comment_anchors` ≤ 5, `must_exclude` ≤ 6, `done_when` ≤ 5
- Summary ≤ 600 chars or 8 bullets; open questions ≤ 3; risks ≤ 3

## Core Rules

- Treat `docs/work/` as rationale and decision history, not public documentation.
- Treat `docs/` as the public-facing source of truth for users and contributors.
- Remove work-path references, implementation detours, and internal-only prose from public docs.
- Remove private issue numbers, canonical-body references, and packet-tracking metadata from public doc bodies.
- Keep one file focused on one audience and one feature area.
- If a doc mixes multiple audiences, split the content before publishing.
- Keep REST API and MCP contract pages in separate packets even when both live under `docs/api/*`.
- Preserve traceability in the packet manifest / handoff / issue record, not in the public doc body.
- Prefer `packet_id` + `target_path` as the stable trace key; issue numbers stay in the companion record.
- If a public cross-link is needed, create or use a sanitized public issue / ADR / changelog entry first.
- Respect `comment_sync_policy`: `required`, `optional`, and `not_applicable` must stay explicit.
- Comment sync must stay phpDocumentor-compatible: summary first, `@param` for complex inputs, `@return` for non-void or structured outputs, `@throws` for observable failure modes, `@api` only for stable public contract surfaces.
- The public doc body stays clean of tracking metadata; packet manifest, handoff, and acceptance live in the companion issue or a separate issue-body packet record.

## File-by-File Flow

1. **Gate check**: Run the mandatory pre-flight packet contract gate. Halt if any field is missing.
2. Collect `source_paths`, `code_anchors`, `test_anchors` from the packet handoff.
3. Read source files and test files at the anchor locations to confirm the behavior.
4. Classify the audience and confirm the `doc_format_profile`. Copy required/optional sections from the template.
5. Record `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, and `style_guardrails` before drafting.
6. Compare with at least one mature OSS example to normalize structure.
7. Rewrite the file using ONLY the selected profile's required sections and the `must_exclude` exclusion list.
8. If `comment_sync_policy` is `required` or `optional`, sync comment anchors per the PHPDoc minimum rule. Record defer reason if `optional` and skipped.
9. Remove internal references, temporary notes, and historical discussion.
10. Validate links, headings, and any rendered output that could break navigation.
11. Fill the packet acceptance table and `done_when` checklist in the packet handoff record (not in the public doc body).

## Publication Guardrails

- Do not copy `docs/work/` text into public docs.
- Do not keep old and new wording side by side when the user has chosen one direction.
- Do not publish demo secrets, tokens, or production-only settings.
- If a file contains uncertain content, stop and flag it before publishing.

## Common External Patterns to Mirror

- Keep the root `README` short and action-oriented.
- Put contribution, conduct, and security guidance in dedicated sections or files.
- Organize docs by audience and feature rather than by implementation chronology.
- For setup and demo docs, include recovery guidance rather than only happy-path steps.
- For technical reference docs, make failure modes and validation steps explicit.

## Validation

- Confirm all gate fields are recorded before claiming done.
- Check the rendered doc or at least the link targets for the touched file.
- Confirm that any public examples do not leak real credentials.
- Confirm the acceptance section records comment sync status for the packet.
- Confirm the acceptance section records `doc_format_profile`, external evidence, and freshness.
- If the rewrite exposes a reusable pattern, route the learning through `/skill-maintenance`.

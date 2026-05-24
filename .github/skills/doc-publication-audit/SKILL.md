---
name: doc-publication-audit
description: Audits mixed internal/public documentation and rewrites one file at a time into stable public-facing docs. Use when docs/work, development notes, or issue plans need to become external docs with a consistent feature-to-doc mapping.
compatibility: LedgerLeap (`docs/`, `docs/work/`, `.github/skills/`, `.github/prompts/`)
---

# doc-publication-audit

## When to use

- Existing docs mix internal work notes, implementation history, and public-facing content.
- You need to prepare docs for publication before a release or public repo cutover.
- A manual, file-by-file rewrite is required because the source cannot be converted mechanically.
- A packet handoff already exists and one target file must be rewritten with bounded comment sync.

## Routing Boundary

- This skill is the **single-packet executor**, not the router.
- If lane selection or packet handoff is still unclear, start with [doc-publication-packet](../../prompts/doc-publication-packet.prompt.md).
- If #226 baseline / packet readiness needs re-check, hand back to [doc-source-inventory](../doc-source-inventory/SKILL.md).
- Use this skill only after `packet_id`, `target_path`, `doc_format_profile`, and `comment_sync_policy` are fixed.

## Reference Templates

- Use [User-facing template](references/public-doc-user-template.md) for setup guides, feature guides, troubleshooting, and onboarding pages.
- Use [Developer-facing template](references/public-doc-developer-template.md) for architecture notes, configuration references, testing guides, and API/MCP pages.
- Use [Placement guide](references/public-doc-placement-guide.md) to decide whether content belongs in root files, `docs/`, `CONTRIBUTING.md`, `SECURITY.md`, or private `docs/work/`.
- Use [Packet execution asset summary](references/packet-execution-assets.md) for the OpenCode / Continue / LM Studio adapter facts confirmed in #228.
- Use [Issue #230 evidence](../../../docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md) for `doc_format_profile`, evidence-field, and PHPDoc minimum rules.
- Use [Doc publication packet template](../../../docs/templates/doc-publication-packet-template.md) for packet manifest, handoff, and acceptance.
- Use [Doc publication packet playbook](../../../docs/runbooks/doc-publication-packet-playbook.md) for the operator flow and adapter entrypoints.
- If a page teaches setup or recovery, extend the user-facing template with a short troubleshooting section.
- If a page describes configuration or internals, extend the developer-facing template with edge cases, caveats, and validation notes.

## Core Rules

- Treat `docs/work/` as rationale and decision history, not public documentation.
- Treat `docs/` as the public-facing source of truth for users and contributors.
- Remove work-path references, implementation detours, and internal-only prose from public docs.
- Keep one file focused on one audience and one feature area.
- If a doc mixes multiple audiences, split the content before publishing.
- If a packet handoff already exists, treat `packet_id`, `target_path`, `doc_type`, `doc_format_profile`, `comment_sync_policy`, and `must_exclude` as fixed contract inputs.
- If the packet baseline is stale, stop and hand back to [doc-source-inventory](../doc-source-inventory/SKILL.md) instead of widening the rewrite.
- Keep REST API and MCP contract pages in separate packets even when both live under `docs/api/*`.
- Respect `comment_sync_policy`: `required`, `optional`, and `not_applicable` must stay explicit.
- Fix `doc_format_profile` before writing and derive required/optional sections from the shared packet template instead of inventing a custom shape.
- Packet evidence must retain `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, `source_anchor`, and `comment_sync_decision`.
- Comment sync must stay phpDocumentor-compatible: summary first, `@param` for complex inputs, `@return` for non-void or structured outputs, `@throws` for observable failure modes, `@api` only for stable public contract surfaces.

## File-by-File Flow

1. Identify the packet handoff, source file set, and target public file.
2. Classify the audience and fix one `doc_format_profile`.
3. Find the nearest code, config, or test anchor that proves the behavior.
4. Record external evidence URLs, freshness, and style guardrails before drafting.
5. Compare with at least one mature OSS example to normalize structure.
6. Rewrite the file using the selected profile's required sections.
7. Sync comment anchors or record the explicit defer reason from the packet policy.
8. Remove internal references, temporary notes, and historical discussion.
9. Validate links, headings, and any rendered output that could break navigation.

## Public Doc Template

Use the smallest template that still tells the reader what they can do:

- Purpose: what the feature is for.
- Audience: who should read this page.
- Behavior: what the user can observe.
- Setup or usage: how to start using it.
- Constraints: what is not supported or what must be avoided.
- Evidence: code, tests, config, or runbook anchors.

For public publication work, prefer these default shapes:

- User-facing pages: purpose, who it is for, what the user sees, how to use it, common mistakes, and related links.
- Developer-facing pages: purpose, architecture or config scope, implementation details, test coverage, constraints, and evidence.
- Issue bodies for sprint work: what the sprint creates, how that creation relates to the plan, why the sprint exists, scope, progress checklist, completion criteria, and tracked evidence.
- Packet rewrite work: selected `doc_format_profile`, required sections, external evidence URLs, freshness, and comment-sync decision.

## Publication Guardrails

- Do not copy `docs/work/` text into public docs.
- Do not keep old and new wording side by side when the user has chosen one direction.
- Do not publish demo secrets, tokens, or production-only settings.
- Do not assume a whole documentation set can be rewritten by search-and-replace.
- If a file contains uncertain content, stop and flag it before publishing.

## Common External Patterns to Mirror

- Keep the root `README` short and action-oriented.
- Put contribution, conduct, and security guidance in dedicated sections or files.
- Organize docs by audience and feature rather than by implementation chronology.
- Keep security reporting separate from general support or issues.
- For setup and demo docs, include recovery guidance rather than only happy-path steps.
- For technical reference docs, make failure modes and validation steps explicit.

## Validation

- Check the rendered doc or at least the link targets for the touched file.
- Confirm that any public examples do not leak real credentials.
- Confirm the acceptance section records comment sync status for the packet.
- Confirm the acceptance section records `doc_format_profile`, external evidence, and freshness.
- If the rewrite exposes a reusable pattern, route the learning through `/skill-maintenance`.

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
- Fix one `doc_format_profile` before writing and use the shared required/optional section matrix from `docs/templates/doc-publication-packet-template.md`.
- Capture packet evidence in a stable shape: `external_evidence_urls`, `last_confirmed_at`, `recheck_after`, `source_anchor`, and `comment_sync_decision`.
- Record `style_guardrails` from the selected profile so examples and wording stay realistic.
- Keep REST API and MCP contract as separate packet lanes.
- Keep `docs/contributing/*` provisional unless a dedicated source set proves it.
- Return summary-first handoff data and avoid raw source dumps in the main conversation.
- When rewriting, stay with one writer and one target file.
- Use `docs/templates/doc-publication-packet-template.md` for handoff and acceptance.

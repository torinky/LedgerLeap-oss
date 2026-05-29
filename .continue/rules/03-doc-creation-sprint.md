---
name: Doc Creation Sprint
globs:
  - "docs/**/*.md"
  - ".github/**/*.md"
description: Find the highest-priority unwritten user/developer doc from the #226 backlog and create it in one bounded execution.
alwaysApply: false
---

- Read `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md` for the authoritative backlog.
- Compare target list v2 against existing files under `docs/getting-started/`, `docs/features/`, `docs/api/`, `docs/admin/`, `docs/architecture/`.
- Apply priority order: getting-started > features > api > admin > architecture.
- Pick the top missing target. If none, stop and report fully covered.
- Generate a packet handoff from `docs/templates/doc-publication-packet-template.md` with fields from the inventory.
- Run the mandatory pre-flight gate before writing (all handoff fields must be populated).
- Follow `doc-publication-audit` file-by-file flow for the actual writing.
- Apply the selected `doc_format_profile` required sections; do not invent new sections.
- Remove `docs/work/` references, private issue numbers, and packet tracking metadata from the public body.
- Record acceptance in the handoff record, not in the public doc body.
- One execution = one new doc. Report the created file and the next backlog candidate.

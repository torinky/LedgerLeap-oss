---
description: Find the highest-priority unwritten doc from the #226 backlog and create it in one bounded execution
agent: build
---

Create one user-facing or developer-facing doc from the #226 backlog.

1. Load `@docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md` and extract target list v2.
2. Scan existing files under `docs/getting-started/`, `docs/features/`, `docs/api/`, `docs/admin/`, `docs/architecture/`.
3. Find the top missing target by priority: getting-started > features > api > admin > architecture.
4. Follow `@docs/runbooks/doc-publication-packet-playbook.md` and `@docs/templates/doc-publication-packet-template.md`.
5. Load `@docs/../../.github/skills/doc-creation-sprint/SKILL.md` for the full flow.
6. Write exactly one file. Do not chain multiple creations.
7. Report the created file, the applied profile, comment sync decision, and the next backlog candidate.

---
description: Rewrite one LedgerLeap publication packet with a single writer
agent: build
---

Rewrite one LedgerLeap publication packet for `$ARGUMENTS`.

- Use `@docs/templates/doc-publication-packet-template.md`
- Follow `@docs/runbooks/doc-publication-packet-playbook.md`
- Confirm `doc_format_profile`, required sections, evidence fields, and comment sync scope before drafting
- Keep PHPDoc comment sync compatible with the playbook's minimum rule
- Stay in a single-writer flow
- If you need inventory refresh, stop and hand back to `/packet-plan` instead of widening the rewrite

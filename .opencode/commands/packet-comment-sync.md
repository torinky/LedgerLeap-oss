---
description: Sync only the comment anchors for one LedgerLeap publication packet
agent: build
---

Sync comment anchors only for `$ARGUMENTS`.

- Respect `comment_sync_policy`
- Use the acceptance section from `@docs/templates/doc-publication-packet-template.md`
- Apply only the packet's selected source/comment anchors and the playbook's PHPDoc minimum rule
- Do not rewrite unrelated packet content
- Record explicit defer reasons when comment sync is `optional` or `not_applicable`

---
name: Doc Packet Comment Sync
globs:
  - "docs/**/*.md"
  - "app/**/*.php"
  - "routes/**/*.php"
description: Comment-sync rules for LedgerLeap publication packets.
alwaysApply: false
---

- Read `comment_sync_policy` before editing comment anchors.
- `required` means comment sync must be completed or explicitly blocked with evidence.
- `optional` means comment sync can be deferred, but the defer reason must be recorded in acceptance.
- `not_applicable` means do not invent comment work; record the reason and stop there.
- Comment sync should stay bounded to the selected packet's anchors only.
- Prioritize public-source anchors only: controller actions, Livewire public methods, service methods, MCP/API tools, or stable DTO/value objects referenced by the packet.
- Use phpDocumentor order: `summary -> description -> tags`.
- Minimum tags are conditional: `@param` for complex inputs, `@return` for non-void or structured outputs, `@throws` for observable failure modes, `@api` only for stable public contract surfaces.

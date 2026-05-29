---
name: doc-creation-sprint
description: Find the highest-priority unwritten user/developer doc from the #226 backlog and create it in one bounded execution. Scans existing docs/ areas, selects the top missing target, generates a packet handoff, and writes the file. One execution = one new doc.
compatibility: LedgerLeap (`docs/`, `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`)
---

# doc-creation-sprint

## When to use

- You want to create one new user-facing or developer-facing doc and let the agent discover the target automatically.
- The #226 backlog has unwritten items and you need the next most impactful one.
- You do not want to hand-craft a packet handoff manually.

## Routing Boundary

- This skill **discovers + selects + executes** one packet in a single run. It is the entrypoint for doc creation, not the deep executor.
- If a packet handoff is already fixed, skip this skill and go directly to `doc-publication-audit`.
- If inventory refresh alone is needed (no writing), use `doc-source-inventory`.
- If the backlog is fully up to date and a specific packet is already defined, use `/packet-rewrite` directly.

## Decision Tree

```text
Want to create a doc?
├─ Packet handoff already exists? → doc-publication-audit
├─ Only need to check backlog? → doc-source-inventory
└─ Need to discover + create one doc?
   └─ Run this skill (doc-creation-sprint)
```

## Discovery Phase

1. Read `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md` to get the `#219 用 public doc target list v2`.
2. Scan the existing file tree under `docs/getting-started/`, `docs/features/`, `docs/api/`, `docs/admin/`, `docs/architecture/`.
3. Compare the existing files against the target list to identify unwritten packets.
4. Rank by priority: getting-started > features > api > admin > architecture (user-facing first, developer-facing second).
5. Pick the top unwritten target. If all targets in priority 1 are done, move to the next priority.

## Handoff Generation Phase

6. Build a packet handoff for the selected target using `docs/templates/doc-publication-packet-template.md`.
7. Populate mandatory fields from #226 inventory data (feature family, source anchors, comment anchors, test anchors, audience, doc_type).
8. Select `doc_format_profile` matching the doc_type.
9. Record `external_evidence_urls`, `last_confirmed_at`, `recheck_after`.
10. Record `must_exclude` based on the inventory's public/internal split and `docs/work/` avoidance rule.

## Execution Phase

11. Follow the `doc-publication-audit` file-by-file flow: gate check → read source anchors → confirm behavior → draft prose → remove internal refs → validate links.
12. Follow the profile's required sections; do not invent new sections.
13. Write the public doc body only. Do not embed packet tracking metadata in the public file.
14. If `comment_sync_policy` is `required` or `optional`, apply the PHPDoc minimum rule per the playbook.
15. Fill the packet acceptance table in the handoff record (not in the public doc body).

## Post-Execution

16. Report the created file, the applied profile, the comment sync decision, the evidence fields captured, and the next candidate in the backlog.
17. Do not continue to create a second doc — one execution = one file.
18. If the creation exposes a reusable pattern, route the learning through `/skill-maintenance`.

## Priority Order

| Priority | doc_area | Examples |
|---|---|---|
| 1 | getting-started | overview.md, tenant-context.md |
| 2 | features | ledger-lifecycle.md, workflow-and-rollback.md, search-and-lookup.md, attachments-and-file-inspector.md, notifications-history-and-announcements.md, folders-and-access.md |
| 3 | api | overview.md, ledger-api.md |
| 4 | admin | users-and-organizations.md, roles-permissions-and-folder-access.md, tags-synonyms-and-search-taxonomy.md, admin-announcement-banner.md |
| 5 | architecture | multi-tenancy-boundaries.md, permission-and-folder-access-model.md, file-processing-pipeline.md |

## Validation

- Confirm the target did not exist before execution and exists after.
- Confirm `doc_format_profile` and required sections match the template.
- Confirm no `docs/work/` references, no private issue numbers, no packet tracking metadata in the public body.
- Confirm comment sync decision is recorded.

## Evidence

- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `docs/templates/doc-publication-packet-template.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `.github/skills/doc-publication-audit/SKILL.md`

## Freshness

- status: confirmed-repo
- last_confirmed_at: 2026-05-27
- recheck_after: 180d
- recheck_trigger: #226 superseded, doc area layout changes, new priority scheme introduced

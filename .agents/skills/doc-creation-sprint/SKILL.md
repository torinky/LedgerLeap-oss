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
7. Populate mandatory fields from #226 inventory data (feature family, source anchors, test anchors, audience, doc_type).
8. **Comment sync triage** — run BEFORE finalizing `comment_sync_policy`. Do not assume `not_applicable` without evidence.
   a. For every PHP file in `source_paths` that is NOT a Blade template or migration, check whether the class has a DocBlock (`get_symbols_overview`). Classify each as: complete, incomplete, or missing.
   b. Use `search_in_files_by_text` with the key service class name to discover **indirect consumers** (Livewire components, controllers, etc.) that call the service. Add undocumented consumers to `comment_anchors`.
   c. **Gate**: if ANY service class, model with public business methods, or Livewire component has a missing or incomplete DocBlock, `comment_sync_policy` MUST be `optional` or `required` — never `not_applicable`.
   d. `not_applicable` is valid ONLY when every source class already has a complete class-level DocBlock AND every described public method already has a correct DocBlock. Pure Filament boilerplate (form/table schema only, no described behavior) may qualify.
   e. Record the triage result in the companion record under `comment_sync_triage` (which classes checked, which found undocumented).
9. Select `doc_format_profile` matching the doc_type.
10. Record `external_evidence_urls`, `last_confirmed_at`, `recheck_after`.
11. Record `must_exclude` based on the inventory's public/internal split and `docs/work/` avoidance rule.

## Execution Phase

12. Follow the `doc-publication-audit` file-by-file flow: gate check → read source anchors → confirm behavior → draft prose → remove internal refs → validate links.
13. Follow the profile's required sections; do not invent new sections.
14. Write the public doc body only. Do not embed packet tracking metadata in the public file.
15. **Run comment-sync** if policy is `required` or `optional`. Do not skip silently — the companion record MUST show execution evidence or explicit deferral with reason.
    - `required`: full checklist on every class/method in `comment_anchors` (class-level summary, `@param`/`@return`/`@throws`).
    - `optional`: apply only to undocumented classes. Skip classes that already have a complete class-level summary + method-level DocBlocks.
    - Follow `.github/skills/comment-sync/SKILL.md` and `references/phpdoc-inspection-checklist.md`.
    - Record execution log (file, symbols changed, what was done) in the companion record.
16. Fill the packet acceptance table in the handoff record (not in the public doc body).

## Post-Execution

17. Report the created file, the applied profile, the comment sync decision, the evidence fields captured, and the next candidate in the backlog.
18. Do not continue to create a second doc — one execution = one file.
19. If the creation exposes a reusable pattern, route the learning through `/skill-maintenance`.

## Anti-Patterns

- **Assuming `not_applicable` without evidence**: Filament Resource ≠ no DocBlock needed. Check actual DocBlock presence via step 8 before setting the policy.
- **Skipping indirect consumers**: A service used by 4+ Livewire components/controllers is in scope. Use `search_in_files_by_text` to trace the call graph (step 8b).
- **Silently skipping comment-sync**: When policy is `required` or `optional`, step 15 MUST produce execution evidence (log of files changed) or an explicit deferral reason in the companion record.

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
- Confirm `comment_sync_triage` is recorded (step 8e) — policy was evidence-driven, not assumed.
- Confirm `comment_sync_policy` is recorded and any `required`/`optional` policy produced execution evidence (step 15).

## Evidence

- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `docs/templates/doc-publication-packet-template.md`
- `docs/runbooks/doc-publication-packet-playbook.md`
- `.github/skills/doc-publication-audit/SKILL.md`

## Freshness

- status: confirmed-repo
- last_confirmed_at: 2026-05-29
- recheck_after: 180d
- recheck_trigger: #226 superseded, doc area layout changes, new priority scheme introduced, or comment-sync audit workflow changes

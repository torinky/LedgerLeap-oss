---
name: doc-source-inventory
description: Refreshes LedgerLeap's source-derived public doc inventory and packet backlog without rerunning the initial #226 scan from scratch. Use when feature-family deltas, anchor changes, or packet readiness need a bounded refresh before packet execution.
compatibility: LedgerLeap (`routes/`, `app/Livewire`, `app/Filament`, `app/Mcp`, `tests/Feature`, `lang/ja`, `docs/`, `docs/work/`)
---

# doc-source-inventory

## Decision Tree

```text
Need to refresh public doc packet scope?
├─ No → use doc-publication-audit for one packet rewrite.
└─ Yes
   ├─ Is #226 still the authoritative baseline?
   │  ├─ No → stop and open a new inventory issue; do not silently replace the baseline.
   │  └─ Yes
   ├─ Did routes / UI / API / MCP / tests / lang / docs change in one family?
   │  ├─ Yes → record only that family's delta.
   │  └─ No → compare packet readiness and backlog ordering only.
   ├─ Are REST API and MCP both touched?
   │  ├─ Yes → keep them as separate packet families.
   │  └─ No → keep one lane.
   └─ Is `docs/contributing/*` involved?
      ├─ Yes → keep it provisional unless a new source set proves it.
      └─ No → update confirmed families only.
```

## Core Rules

- Start from #226 feature families, target doc list v2, and comment anchor candidates; refresh deltas instead of regenerating the first inventory.
- Treat the packet unit as **1 target public file**.
- Compare routes, Livewire, Filament, API / MCP, tests, translations, and existing docs before changing packet readiness.
- Keep `REST API` and `MCP contract` separate even under `docs/api/*`.
- Record whether the delta changes `feature_family`, `doc_area`, `comment_anchor_group`, or only packet priority.
- Keep `docs/contributing/*` in a provisional queue unless a dedicated source set proves it.
- Output summary-first: family delta, affected packet IDs, anchor delta, backlog effect, and open questions.

## Output Checklist

- [ ] #226 baseline and current issue body were read first
- [ ] affected family or packet IDs are listed
- [ ] route / UI / API / test / lang / docs signals were compared
- [ ] REST vs MCP split is preserved
- [ ] provisional items are isolated
- [ ] downstream issue or packet handoff was updated

## Evidence

- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md`
- `docs/runbooks/doc-publication-packet-playbook.md`

## Freshness

- status: confirmed-repo
- last_confirmed_at: 2026-05-24
- recheck_after: 180d
- recheck_trigger: target doc list v2 changes, a new public doc area is proposed, #226 is superseded, or a later sprint tries to merge REST and MCP lanes

---
name: browser-har-analysis
description: Analyze browser HAR files for LedgerLeap to compare repeated requests, Livewire update payloads, and before/after bottlenecks. Use when HAR files are being compared, repeated scripts should be standardized, or the same network capture is reviewed more than once.
compatibility: LedgerLeap (.github/prompts/browser-har-analysis.prompt.md, docs/runbooks/browser-har-analysis-playbook.md, docs/harnesses/browser-har-analysis/README.md)
---

# browser-har-analysis

## Decision Tree

```text
Browser HAR analysis needed?
├─ Need to compare before/after captures? → summarize per file and diff by request type
├─ Need to isolate repeated Livewire requests? → count livewire/update, compare payload size, inspect component names
├─ Need to separate app cost from debug noise? → check debugbar/_boost/static assets first
├─ Need a repeatable command recipe? → use the harness script in docs/harnesses/browser-har-analysis/
└─ Need to report a reusable finding? → attach evidence in docs/work and sync via /skill-maintenance
```

## What to Inspect

- Initial `document` request: status, wait/TTFB, body size
- `livewire/update` requests: count, total time, payload size, repeated component sets
- Static assets: `app-*.js/css`, `livewire.js`, Vite dev assets, debugbar assets
- Repeated request patterns: same route, same component set, same response size
- Noise sources: debugbar, browser logs, overlay telemetry, dev server refreshes

## Standard Command Recipe

Use the shared script for recurring inspection:

```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py storage/logs/localhost4.har storage/logs/localhost5.har
```

Typical output should answer:
- Which request type dominates?
- How many `livewire/update` requests are large?
- Which components are repeated in the payload?
- Did debug noise disappear between captures?

## Output Contract

When reporting results, include:

1. **Capture context**
   - HAR filename
   - debug mode on/off
   - browser / page flow if known

2. **Top-level metrics**
   - total requests
   - largest `document`
   - `livewire/update` count and sizes
   - obvious static asset outliers

3. **Component breakdown**
   - repeated Livewire components
   - response sizes per component
   - whether the same heavy component appears more than once

4. **Comparison summary**
   - before / after deltas
   - what disappeared
   - what remains

5. **Next action**
   - whether to keep investigating network/DOM/UI layering
   - whether the bottleneck has moved to HTML, assets, or rerenders

## Guardrails

- Separate **debug noise** from app cost.
- Do not assume a slow page is a single SQL issue; confirm whether the same content is being re-requested.
- If the same script is used repeatedly, move it into the harness script instead of retyping it.
- Keep repo proof in `docs/work/*` and reference it from this skill.

## Evidence

- [`docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md`](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-03-20
- recheck_after: 2026-06-20
- recheck_trigger: HAR schema changes, Livewire network payload format changes, or repeated scripts being updated


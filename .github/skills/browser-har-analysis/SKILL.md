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
├─ Need to measure #[Lazy] interactive vs content-complete time? → use har_lazy_analysis.py
├─ Need to separate app cost from debug noise? → check debugbar/_boost/static assets first
├─ Need a repeatable command recipe? → use the harness script in docs/harnesses/browser-har-analysis/
└─ Need to report a reusable finding? → attach evidence in docs/work and sync via /skill-maintenance
```

## LedgerLeap-Specific Traps

### Livewire URL Pattern
LedgerLeap uses `livewire-HASH/update`. Matching only `livewire/update` will miss all requests.

```python
# ❌ Old pattern (does not match)
if 'livewire/update' in url:

# ✅ Correct pattern
import re
if re.search(r'livewire[^/]*/update', url):
```

### #[Lazy] Component HAR Signature

| State | 1st req | 2nd req |
|------|-----------|-----------|
| No Lazy | `index-manager + records-table` (500-900KB, 5-15s) | none |
| With Lazy | `index-manager + records-table(placeholder)` (~164KB, ~790ms) | `records-table` alone (460-860KB, 5-13s) |

- **1st body < 300KB** → placeholder bundle (real RecordsTable not included)
- **interactive time** = 1st req time_ms (user sees skeleton UI)
- **content complete time** = 1st + 2nd req time_ms (real content ready)

## What to Inspect

- Initial `document` request: status, wait/TTFB, body size
- `livewire/update` requests: count, total time, payload size, repeated component sets
- **Folder-switch sequences**: IndexManager / RecordsTable separation (Lazy effect check)
- **interactive time vs content-complete time**: required for `#[Lazy]` before/after comparison
- Static assets: `app-*.js/css`, `livewire.js`, Vite dev assets, debugbar assets
- Repeated request patterns: same route, same component set, same response size
- Noise sources: debugbar, browser logs, overlay telemetry, dev server refreshes

## Scripts & Commands

See [references/har-scripts.md](references/har-scripts.md) for full command recipes including:
- `har_summary.py` (legacy, basic summary)
- `har_lazy_analysis.py` (recommended, lazy analysis & comparison)
- `analyze_perf_log.py` (performance log aggregation)

## Output Contract

See [references/output-contract.md](references/output-contract.md) for the 7-section report template.

## Guardrails

- Separate **debug noise** from app cost.
- Do not assume a slow page is a single SQL issue; confirm whether the same content is being re-requested.
- If the same script is used repeatedly, move it into the harness script instead of retyping it.
- Keep repo proof in `docs/work/*` and reference it from this skill.
- If `har_summary.py` livewire/update count is 0, suspect URL pattern mismatch and use `har_lazy_analysis.py`.

## Advanced Patterns

See [references/advanced-diagnosis.md](references/advanced-diagnosis.md) for:
- `lazyLoaded` field interpretation
- `$commit` NO UPDATES diagnosis
- `wire:key` dynamic change + `#[Lazy]` forced remount
- Alpine.js `x-data` + `IntersectionObserver` interaction
- Blade component render spike pattern
- `Cache::remember()` pitfall and request-level cache

## Evidence

- [`docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md`](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)
- [`docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md`](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md)
- [`docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-localhost4-har-perf-analysis.md`](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-localhost4-har-perf-analysis.md)
- [`docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md`](../../../docs/work/performance/2026-05-05_issue-205-autolink-spike-retrospective.md)
- [Issue #200: State-based cache for derived results](https://github.com/torinky/LedgerLeap/issues/200)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-05
- recheck_after: 2026-08-05
- recheck_trigger: HAR schema changes, Livewire network payload format changes, livewire URL hash pattern changes, `har_lazy_analysis.py` updates, `#[Lazy]` lifecycle changes, Alpine.js / Livewire dirty-check behavior changes, new Blade component render spikes in `column_html_show_ms`, or Cache driver changes

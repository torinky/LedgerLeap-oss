---
name: bug-investigation
description: Investigates LedgerLeap bugs before implementation by collecting evidence, comparing hypotheses, checking LedgerLeap-specific traps, and proposing response options. Use when a bug, exception, UI regression, CI failure, or unexpected behavior needs root-cause analysis before changing code.
compatibility: LedgerLeap (logs, browser-logs, tests, .github/prompts/bug-investigation.prompt.md, docs/runbooks/bug-response-playbook.md)
---

# bug-investigation

## Decision Tree

```text
Unexpected behavior or error?
├─ Missing reproduction / expected vs actual? → define that first
├─ No internal evidence yet? → collect logs, stack traces, browser logs, test failures
├─ Code path still unclear? → inspect related code, callers, recent changes, existing tests
├─ LedgerLeap-specific trap plausible?
│  ├─ tenancy / #[Lazy] / tenant_id fallback
│  ├─ permission caches not cleared
│  ├─ Mroonga single-column MATCH() AGAINST()
│  ├─ Livewire public state not plain arrays
│  └─ external service dependency in tests
├─ Internal evidence insufficient? → search repo docs / prior fixes / similar implementations
└─ Only then expand to official docs → GitHub issues/discussions → similar OSS → trusted articles
```

## Investigation Contract

- Do investigation before code changes.
- Compare multiple hypotheses; do not lock on the first plausible cause.
- Keep negative results and disproven hypotheses.
- Distinguish internal evidence from external guidance.
- End with response options, verification, and rollback.

## Minimum Deliverable

- Problem summary: expected vs actual, reproduction, impact
- Evidence checked: logs, code, tests, docs, recent changes
- Similar cases: repo-internal and external
- Hypothesis table with evidence for/against and confidence
- Recommended option with risk, verification, and rollback

## LedgerLeap Trap Checklist

- [ ] Tenant initialized in tests and tenant-aware renders have fallback
- [ ] Permission and tenant access caches handled when ACL changed
- [ ] Mroonga query respects single-column full-text rule
- [ ] Livewire state remains plain arrays
- [ ] Tests isolate Embedding / OCR / LDAP / external dependencies
- [ ] New stable pattern scheduled for `/skill-maintenance`

See [evidence-order](./references/evidence-order.md) for the evidence collection order and LedgerLeap checks.
See [external-research](./references/external-research.md) for external source order and reporting format.

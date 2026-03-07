---
name: bug-execution
description: Executes a selected LedgerLeap bug fix with minimal changes, regression prevention, validation, and rollback awareness. Use when the investigation is complete and an implementation approach has been selected.
compatibility: LedgerLeap (.github/prompts/bug-execution.prompt.md, docs/runbooks/bug-response-playbook.md, sail pint, sail test)
---

# bug-execution

## Decision Tree

```text
Fix approach already selected?
├─ NO  → return to bug-investigation first
├─ YES → define change contract: inputs, outputs, scope, rollback
├─ Public behavior change involved?
│  └─ state impact and affected surfaces explicitly
├─ Can the bug be reproduced by a test?
│  └─ add or update regression coverage
├─ LedgerLeap-specific validation needed?
│  ├─ Tailwind utility added → npm build
│  ├─ ACL changed → cache reflection check
│  ├─ tenant-sensitive code → tenant init + tenant_id fallback check
│  └─ external dependency in tests → isolate or fake service
└─ Reusable pattern discovered after the fix? → run /skill-maintenance
```

## Execution Contract

- Implement the smallest change that addresses the chosen root cause.
- Avoid unrelated refactors while fixing the bug.
- Preserve existing naming, UI, and public APIs unless the fix requires change.
- Add regression protection when practical.
- End with verification, residual risk, and rollback.

## Minimum Deliverable

- Implementation summary and changed files
- Added or updated tests
- Validation results: lint / test / build / smoke
- Remaining risks and follow-up

## Validation Checklist

- [ ] `./vendor/bin/sail pint` for PHP/Laravel edits
- [ ] `./vendor/bin/sail test` or targeted tests for behavior changes
- [ ] Browser or UI smoke for Livewire / frontend issues
- [ ] `./vendor/bin/sail npm run build` after new Tailwind utility classes
- [ ] Cache / tenancy / Mroonga / Livewire serialization traps checked where relevant
- [ ] Reusable learnings handed to `/skill-maintenance`

See [minimal-change](/.github/skills/bug-execution/references/minimal-change.md) for scope control and regression guidance.
See [validation](/.github/skills/bug-execution/references/validation.md) for LedgerLeap-specific validation order.

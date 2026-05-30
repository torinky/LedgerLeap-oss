---
name: workflow-status-machine
description: Implements and tests LedgerLeap's WorkflowStatus state machine (NONE→DRAFT→PENDING_INSPECTION→PENDING_APPROVAL→APPROVED). Use when workflow status is not transitioning, latestDiff() returns null, inspector/approver modal won't open, or workflow-related tests fail.
compatibility: LedgerLeap (WorkflowStatus enum, WorkflowService, LedgerDiff, Ledger.latest_diff_id)
---

# workflow-status-machine

## State Transition Map

```
NONE ──────────────────────────────── (workflow_enabled=false)
DRAFT ──submit()──► PENDING_INSPECTION
PENDING_INSPECTION ──inspect()──► PENDING_APPROVAL
                   ──reject()───► DRAFT
PENDING_APPROVAL   ──approve()──► APPROVED (or PENDING_APPROVAL if multi-approver)
                   ──reject()───► DRAFT
APPROVED           ──rollback()─► DRAFT
```

## Decision Tree

```
latestDiff() returns null?
├─ LedgerDiff was created but latest_diff_id not set on Ledger?
│   FIX: $ledger->update(['latest_diff_id' => $diff->id]); $ledger->fresh();
│   WorkflowService always sets this — factory does NOT.
│
Modal won't open (inspect/approve button has no effect)?
├─ latestDiff()->inspector_id !== Auth::id() (for inspection)
│   OR latestDiff()->approver_id !== Auth::id() (for approval)
│   FIX: set inspector_id/approver_id to match the acting user's id
│
Status not transitioning?
├─ workflow_enabled=false on LedgerDefine → status stays NONE
├─ Calling WorkflowService from wrong tenant context?
│   FIX: tenancy()->initialize($tenant) before any WorkflowService call in tests
│
canBeRejected() returns false unexpectedly?
   Requires status ≠ DRAFT AND status ≠ APPROVED
   AND latestDiff->inspector_id|approver_id === Auth::id()
```

## latest_diff_id — The Most Common Bug

`Ledger::latestDiff()` is `belongsTo(LedgerDiff, 'latest_diff_id')`.  
`WorkflowService` always sets `latest_diff_id` in every transition.  
`LedgerDiff::factory()->create()` does NOT update `Ledger.latest_diff_id`.

```php
// ❌ latestDiff() returns null
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

// ✅ Required pattern in tests
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
$ledger->update(['latest_diff_id' => $diff->id]);
$ledger = $ledger->fresh();
```

## Checklist

- [ ] `workflow_enabled=true` on LedgerDefine before testing transitions
- [ ] `latest_diff_id` explicitly set after factory diff creation
- [ ] `$ledger->fresh()` called after `update()`
- [ ] `inspector_id` / `approver_id` match the acting user in modal tests
- [ ] `tenancy()->initialize($tenant)` in Feature test setUp()
- [ ] `areAllRequiredInspectionsCompleted()` checked before APPROVE transition

See [references/workflow-test-setup.md](references/workflow-test-setup.md) for test data helpers.
See [references/workflow-transitions.md](references/workflow-transitions.md) for WorkflowService API.


# WorkflowService API Reference

## Key methods in WorkflowService

| Method | From status | To status | latest_diff_id |
|---|---|---|---|
| `saveDraft()` | DRAFT | DRAFT | updated |
| `submitForInspection($ledger, $inspectorId)` | DRAFT | PENDING_INSPECTION | updated |
| `inspect($ledger, $approverId)` | PENDING_INSPECTION | PENDING_APPROVAL | updated |
| `reject($ledger)` | PENDING_* | DRAFT | updated |
| `approve($ledger)` | PENDING_APPROVAL | APPROVED (or PENDING_APPROVAL) | updated |
| `rollback($ledger, $user)` | APPROVED | DRAFT | updated |
| `claim($ledger, $claimer)` | PENDING_* | same | updated (reassigns handler) |

**Every method that creates a `LedgerDiff` also sets `Ledger.latest_diff_id`.**

## canBeInspectedBy($ledger, $user) logic

```php
// Returns true if:
$ledger->status === PENDING_INSPECTION
    && $ledger->latestDiff?->inspector_id === $user->id
// OR
$ledger->status === PENDING_APPROVAL
    && $ledger->latestDiff?->approver_id === $user->id
```

## canBeRejectedBy($ledger, $user) logic

```php
// Returns true if:
!in_array($ledger->status, [DRAFT, APPROVED])
    && (inspector_id === $user->id || approver_id === $user->id)
```

## Multi-approver logic (areAllRequiredInspectionsCompleted)

When `LedgerDefine` has multiple required approver roles:
1. `latestDiff->completed_approver_role_ids[]` tracks which roles have approved
2. `approve()` adds the current user's role to the completed list
3. Only when ALL required roles appear → status advances to `APPROVED`

## WorkflowStatus enum values

```php
WorkflowStatus::NONE                // 'none'   — workflow disabled
WorkflowStatus::DRAFT               // 'draft'
WorkflowStatus::PENDING_INSPECTION  // 'pending_inspection'
WorkflowStatus::PENDING_APPROVAL    // 'pending_approval'
WorkflowStatus::APPROVED            // 'approved'
```

## Artisan command for notification batch

```bash
./vendor/bin/sail artisan workflow:send-summary
# Sends pending task summaries to users with pending_inspection_count > 0
# or pending_approval_count > 0
```


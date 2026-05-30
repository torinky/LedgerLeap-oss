---
applyTo: "app/**/*.php"
---

# PHP / Laravel Rules for LedgerLeap

## Data Access Rules

| Rule | Detail |
|---|---|
| `content[n]` access | `$ledger->content[0]` — **never** `data_get()` |
| Cast columns | Never call `json_encode()` on `files`, `chk`, or other cast-array columns |
| `content_attached` | Requires `[0 => []]` sentinel at index 0 |
| `latest_diff_id` | Must be set explicitly; factory does NOT cascade |
| Defensive Casting | Always check `is_array()` before `toArray()` on JSON columns |

```
toArray() on string?
├─ column_define or other JSON column returning string or null?
│   → FIX: use is_array() check or wrap in ArrayCast with type safety.
│   ```php
│   $data = is_array($value) ? $value : json_decode($value ?? '[]', true);
│   ```
```

```
content[n] returns null?
├─ Using data_get($ledger->content, '1')? → FIX: $ledger->content[1]
├─ Test data from factory without normalizeByColumnDefine? → Fill all indices 0..maxId
└─ latest_diff_id not set? → $ledger->update(['latest_diff_id' => $diff->id]); $ledger->fresh();
```

## Permission / ACL

```
403 or stale permission?
├─ Changed Role/Organization/User?
│   → flushAllUserPermissionsCache() + TenantAccessService::clearAllCache()
├─ Changed RoleFolderPermission?
│   → WritableFolderRepository::clearAllCache($user)
└─ Test: permission granted but still fails?
    → assignRole() / grantPermission() BEFORE actingAs() (not after)
```

Two-layer ACL:
- **Layer 1** — Spatie\Permission: `Cache::tags(['user_permissions'])`
- **Layer 2** — WritableFolderRepository: `Cache::tags(['tenant_access'])`

## Workflow Status Machine

```
DRAFT ──submit()──► PENDING_INSPECTION ──inspect()──► PENDING_APPROVAL ──approve()──► APPROVED
                                       ──reject()───► DRAFT              ──reject()───► DRAFT
APPROVED ──rollback()──► DRAFT
```

- `workflow_enabled=false` on LedgerDefine → status stays NONE
- `Ledger::latestDiff()` is `belongsTo(LedgerDiff, 'latest_diff_id')` — factory does NOT set it

## Mroonga Full-Text Search

- Single-column `MATCH() AGAINST()` only — composite indexes do NOT work
- Combine multiple columns with `OR` in separate MATCH calls

## Multi-Tenant Cache

- ALL `Cache::remember()` over tenant-scoped models MUST include tenant ID in key:
  ```php
  $cacheKey = "my_key:{$tenantId}";  // use tenant()?->id ?? 'global'
  ```

## Model Events

- Use `$model->update([...])` not `touch()` in event-driven tests

## Config Value Resolution

```
config value depends on env / shell / file?
├─ Inline in config PHP file?
│   → EXTRACT to dedicated helper/service for testability.
│   Reason: config files are parsed once at bootstrap; offsetUnset() does not
│   re-trigger evaluation. Unit-test the helper directly with putenv() / temp files.
├─ Multi-tier fallback needed?
│   → Priority: env override > committed file > shell command > hardcoded default.
│   → Each tier tested independently in the helper's unit test.
└─ Production env has no .git?
    → Add a CI step (e.g. release.yml) to commit a version/sentinel file at tag time.
```

**Example (from #232):**
```php
// ❌ inline in config — untestable after bootstrap
'version' => env('APP_VERSION') ?: shell_exec('git describe ...'),

// ✅ extracted helper — fully unit-testable
'version' => \App\Helpers\Version::resolve(),
```

**Reference:** `tests/Unit/Config/VersionResolutionTest.php` for the test pattern.

See `.github/skills/ledger-content-data-structure/SKILL.md` for content array details.
See `.github/skills/permission-model/SKILL.md` for ACL cache patterns.
See `.github/skills/workflow-status-machine/SKILL.md` for WorkflowService API.


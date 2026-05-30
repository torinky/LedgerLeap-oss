---
name: permission-model
description: Implements and debugs LedgerLeap's two-layer ACL (Spatie\Permission + WritableFolderRepository). Use when getting 403 errors, when Role/Organization/User changes are not reflected, when permission checks are unstable in tests, or when adding new folder-based permission checks.
compatibility: LedgerLeap (Spatie\Permission, WritableFolderRepository, TenantAccessService, UserService)
---

# permission-model

## Decision Tree

```
Permission check fails (403) or is stale?
├─ Changed Role, Organization, or User model?
│   YES → Both cache layers must be cleared:
│          - UserService::flushAllUserPermissionsCache()  ← Spatie cache
│          - TenantAccessService::clearAllCache()          ← folder cache
│          Observers handle this automatically in production.
│          In tests: clear manually BEFORE asserting (see references/cache-patterns.md)
│
├─ Changed RoleFolderPermission (folder ACL)?
│   YES → WritableFolderRepository::clearAllCache($user) needed.
│          RoleFolderPermissionObserver handles this automatically.
│          In tests: clear manually or re-bind the repository.
│
└─ Tests: permission granted but check still fails?
    → assignRole() / grantPermission() must be called BEFORE actingAs().
      After actingAs() the user snapshot is already taken.
```

## Two-Layer ACL Architecture

```
Layer 1 — Spatie\Permission (global roles)
  User → Role → Permission
  Cache: Cache::tags(['user_permissions'])
  Clear: UserService::flushAllUserPermissionsCache()
         → Cache::tags(['user_permissions'])->flush()
         → PermissionRegistrar::forgetCachedPermissions()

Layer 2 — WritableFolderRepository (folder-level)
  User → RoleFolderPermission → FolderPermissionType
  FolderPermissionType: READ, WRITE, INSPECT, APPROVE, ADMIN, NOTIFY_ON, NOTIFY_OFF
  Cache: Cache::tags(['tenant_access'])
  Clear: TenantAccessService::clearAllCache()
         WritableFolderRepository::clearAllCache($user)
```

## ACL Display Pattern

- When a permission summary shows inherited access, prefer the concrete granting folder path over a generic "inherited" label.
- Render the source folder as a breadcrumb-style path with folder icons and visible separators when the hierarchy matters.
- Keep the primary entity name visible in dense tables; reserve icon-only badges for secondary state markers such as direct / inherited provenance.
- Keep the role name as the main label on the row, and place inherited folder provenance on a secondary line below it.
- If the source folder is not known, fall back to a short inherited label instead of guessing.

## Evidence

- Repo proof: [docs/work/ui-ux/2026-04-25_permission-display-overview-retrospective.md](../../../docs/work/ui-ux/2026-04-25_permission-display-overview-retrospective.md)
- Repo proof: [resources/views/livewire/common/permission-display.blade.php](../../../resources/views/livewire/common/permission-display.blade.php)
- Repo proof: [tests/Feature/Livewire/Common/PermissionDisplayTest.php](../../../tests/Feature/Livewire/Common/PermissionDisplayTest.php)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-25
- recheck_after: 90d
- recheck_trigger:
  - permission summary rows start hiding the role name behind state icons again
  - inherited folder provenance stops being shown as a breadcrumb path
  - the breadcrumb separator or folder icon treatment changes in another ACL list

## Observer Auto-Clear Map

| Model changed | Observer | What is cleared |
|---|---|---|
| `User` updated | `UserPermissionsObserver` | user-scoped Spatie + folder cache |
| `Role` / `Organization` updated | `UserPermissionsObserver` | ALL Spatie + ALL folder cache |
| `RoleFolderPermission` created/updated/deleted | `RoleFolderPermissionObserver` | ALL folder cache |
| `Folder` created/updated | `FolderObserver` | ALL folder cache |

## Checklist

- [ ] `assignRole()` / `grantPermission()` called before `actingAs()` in tests
- [ ] After Role or Org change → both `flushAllUserPermissionsCache()` + `clearAllCache()`
- [ ] After `RoleFolderPermission` change → `WritableFolderRepository::clearAllCache($user)`
- [ ] `FolderPermissionType::ADMIN` includes all sub-permissions (no separate READ needed)
- [ ] `tenancy()->initialize($tenant)` called before any permission check in Feature tests
- [ ] In ACL summary UIs, inherited rows show the granting folder path as a breadcrumb when available

See [references/cache-patterns.md](references/cache-patterns.md) for code examples.
See [references/test-permission-setup.md](references/test-permission-setup.md) for test patterns.


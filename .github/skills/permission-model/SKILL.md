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

See [references/cache-patterns.md](references/cache-patterns.md) for code examples.
See [references/test-permission-setup.md](references/test-permission-setup.md) for test patterns.


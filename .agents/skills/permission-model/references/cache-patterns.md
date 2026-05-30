# Permission Cache Patterns

## Production: Automatic via Observers

```php
// ✅ In production, observers clear caches automatically.
// UserPermissionsObserver::updated() fires when User/Role/Organization is saved.
// RoleFolderPermissionObserver fires when RoleFolderPermission is saved.
// FolderObserver fires when Folder is saved.

// Manual trigger (e.g. bulk import, seeder):
app(UserService::class)->flushAllUserPermissionsCache();
app(TenantAccessService::class)->clearAllCache();
```

## Manual Cache Clear — Both Layers

```php
// Always clear BOTH layers when Role or Organization changes:
app(UserService::class)->flushAllUserPermissionsCache();
// → Cache::tags(['user_permissions'])->flush()
// → PermissionRegistrar::forgetCachedPermissions()

app(TenantAccessService::class)->clearAllCache();
// → Cache::tags(['tenant_access'])->flush()

// For a single user's folder cache:
app(WritableFolderRepository::class)->clearAllCache($user);
// → iterates FolderPermissionType::cases() and clears each key
```

## Cache Tag Structure

```php
// Spatie Permission cache
Cache::tags(['user_permissions'])->flush();

// Folder access cache (per user, per permission type)
// Key format: tenant_access:{tenant_id}:folder:{user_id}:{permission}
// e.g.: tenant_access:testa:folder:5:write
```

## refreshAllCache vs clearAllCache

```php
// clearAllCache($user)  — removes all cached folder IDs for $user
// refreshAllCache($user) — clears then immediately re-populates
//                          (warm cache, useful after bulk permission change)

app(WritableFolderRepository::class)->refreshAllCache($user);
```

## FolderPermissionType hierarchy

```
ADMIN       includes all access (read, write, inspect, approve, manage)
WRITE       can create/edit Ledger records
READ        can view Ledger records
INSPECT     can perform inspection workflow step
APPROVE     can perform approval workflow step
NOTIFY_ON   receives notifications
NOTIFY_OFF  overrides notification opt-in
```

`getManageableFolderIds()` uses `ADMIN` — use this for folder management operations.
`getWritableFolderIds()` uses `WRITE`.
`getReadableFolderIds()` uses `READ`.


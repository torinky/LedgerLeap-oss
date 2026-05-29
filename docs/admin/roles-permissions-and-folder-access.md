# Roles, Permissions, and Folder Access

## Summary

LedgerLeap uses a two-layer access control model built on Spatie's permission framework. System permissions control what a user can do globally (create ledgers, manage users), while folder permissions control which folders a user can access and at what level (read, write, manage). Roles tie these together: each role carries a set of system permissions and folder-level access grants.

This page is for administrators who configure the role–permission–folder access matrix.

## Admin Surface

All role and permission management is performed through the Filament admin panel.

### Roles

The role resource provides a table view of all roles with:

- **Name**: Role identifier (e.g., "Member", "Inspector", "Admin")
- **Guard**: Authentication guard (always `web` for standard users)
- **Permissions**: Count of associated system permissions
- **Users**: Count of assigned users

Each role edit page includes these relation managers:

| Relation Manager | Purpose |
|---|---|
| Users | Assign or remove users from the role |
| Organizations | Limit the role to specific organizations |
| Permissions | Grant or revoke system-level permissions |
| Folder Permissions | Grant folder-level read/write/manage access |
| Notification Settings | Configure notification preferences per role |

### System Permissions

System permissions are global capabilities that apply across all tenants. They are assigned to roles via Spatie's `permissions` table. Examples include:

- `create_ledgers` — Create new ledger records
- `update_ledgers` — Edit existing ledger records
- `create_ledger_defines` — Create ledger definition templates
- `create_folders` — Create new folders
- `manage_users` — Administer user accounts
- `view_activity_logs` — View activity history

A user's effective permissions are the union of all permissions from all their assigned roles. Direct permissions can also be granted to individual users from the User edit page.

### Folder Permissions

Folder permissions are the second access layer. They define which folders a role can access and at what level.

Each folder permission entry has three fields:

| Field | Description |
|---|---|
| Folder | The target folder (hierarchical tree selector) |
| Permission Type | `read`, `write`, or `manage` |
| Role | The role receiving the permission |

Permission type capabilities:

| Type | Capabilities |
|---|---|
| **Read** | View the folder and all ledgers within it |
| **Write** | Read + create, edit, and delete ledgers |
| **Manage** | Write + create/edit/delete subfolders and manage folder permissions |

Folder permissions are stored in the `role_folder_permissions` table and managed through the `WritableFolderRepository`.

### Permission Inheritance

Folder permissions are **inherited**: a role granted access to a parent folder automatically has the same access to all descendant folders, unless a more specific permission is set on a child folder.

Directly configured permissions on a child folder **override** inherited permissions from ancestors. For example, if a role has "manage" on `/Projects` but "read" on `/Projects/Confidential`, the effective permission on the confidential subfolder is read-only.

## Effects

### Role changes

- **Permission grant/revoke**: Takes effect immediately. Both `flushAllUserPermissionsCache()` (Spatie) and `TenantAccessService::clearAllCache()` (folder access) must be called after changes.
- **User assignment**: When a user is added to a role, they inherit all of that role's system permissions and folder access immediately.
- **Folder permission changes**: After changing the folder permission matrix, the `WritableFolderRepository` cache must be cleared for affected users.

### Cache management

Three caches are involved in the access control system:

| Cache | Scope | Clear Method |
|---|---|---|
| User permissions | Per-user system permissions | `flushAllUserPermissionsCache()` |
| Folder access | Per-user readable/writable/manageable folder IDs | `TenantAccessService::clearAllCache()` |
| Writable folder | Per-user writable folder IDs | `WritableFolderRepository::clearAllCache($user)` |

## Constraints

- Roles and system permissions are **system-level** (central tables), while folder permissions reference **tenant-level** folders. A role can grant folder access across multiple tenants.
- Deleting a role removes all associated folder permissions. Users who relied solely on that role will lose access.
- The `manage` folder permission is the highest tier and allows the holder to modify the folder's own permission settings.
- Direct user permissions (granted on the User edit page) apply in addition to role-based permissions but do not affect folder access.
- For MCP/API token authentication, the token's abilities must include `mcp:*` for MCP access; additionally, the user must have current-tenant access enforced by middleware.

## Related Resources

- [Users and Organizations](users-and-organizations.md) — User account and organization management
- [Folders and Access](../features/folders-and-access.md) — End-user view of folder permissions
- [Permission and Folder Access Model](../architecture/permission-system.md) — Technical architecture details
- [Getting Started Overview](../getting-started/overview.md) — End-user concepts

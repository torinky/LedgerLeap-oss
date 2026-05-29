# Users and Organizations

## Summary

The LedgerLeap admin panel (Filament) provides resources for managing user accounts, organizations (groups/departments), and API tokens. These are central identity objects shared across all tenants — users and organizations exist at the system level, not within individual tenants.

This page is intended for system administrators and operators who manage the identity layer of LedgerLeap.

## Admin Surface

All identity management is performed through the Filament admin panel, accessible to users with administrative privileges. The following resources are available:

| Resource | Purpose | Navigation |
|----------|---------|------------|
| Users | Create, edit, and manage user accounts | Admin panel sidebar |
| Organizations | Create and manage organizational hierarchy | Accessible from User edit page (not in main navigation) |

### Users

The user resource provides a table view of all registered users with the following columns:

- **Name**: Display name
- **Email**: Login email address
- **Email Verified**: Email verification timestamp
- **Roles**: Assigned roles (multiple)
- **Organizations**: Affiliated organizations (multiple)
- **Created At**: Registration date

Operations available:
- **Create**: Register a new user with name, email, password, and optionally assign roles and organizations
- **Edit**: Modify user details, roles, direct permissions, and organization affiliations
- **Delete**: Soft-delete a user (restorable)
- **Manage tokens**: Create and revoke API/MCP tokens for programmatic access

### Organizations

Organizations represent groups, departments, or teams within the system. They form a hierarchical parent-child structure.

- **Name**: Organization display name
- **Code (org_id)**: Unique organizational code for external system integration
- **Description**: Optional description
- **Parent**: Parent organization for hierarchy (self-referential tree)

Organizations are accessed from the User edit page via a relation manager, not from the main navigation. Users can belong to multiple organizations simultaneously.

### API Tokens

Each user can have multiple Sanctum API tokens for programmatic access. Tokens are managed from the User edit page via a token relation manager.

- **MCP tokens**: Tokens issued with the `mcp:*` ability grant access to the MCP server. These must be created explicitly (the standard token UI does not add `mcp:*` automatically).
- **API tokens**: Standard tokens for REST API access.

See the [API Overview README](../api/README.md#認証) for token generation methods and required abilities.

## Effects

### User changes

- **Role assignment**: When a role is assigned to or removed from a user, both the user permission cache and the tenant access cache must be cleared. The system automatically handles this.
- **Organization affiliation**: Changing a user's organization does not automatically change folder access. Folder access is granted through roles, not through organizations.
- **Password change**: Password changes take effect immediately and do not invalidate existing sessions.
- **Soft delete**: Deleted users retain their data for audit purposes. Hard-delete is available but should be used with caution.

### Organization changes

- **Hierarchy changes**: Moving an organization within the tree structure affects display order but not access permissions.
- **Deletion**: Organizations with active users cannot be deleted until all user affiliations are removed.

## Constraints

- Users and organizations are **system-level** objects, not tenant-scoped. A single user account can access multiple tenants.
- The organization hierarchy is for grouping and display — it does not directly control folder permissions.
- Folder access is controlled via **roles**, not organizations. To grant a user access to a folder, assign a role with the appropriate folder permissions.
- After any role or organization change, both `flushAllUserPermissionsCache()` and `TenantAccessService::clearAllCache()` must be called. The admin panel handles this automatically for standard edits.
- MCP tokens require the `mcp:*` ability. The standard Filament token UI does not grant this ability automatically — use `demo:generate-mcp-token` Artisan command or create tokens via Tinker for MCP access.

## Related Resources

- [Roles, Permissions, and Folder Access](roles-permissions-and-folder-access.md) — Role and permission management
- [API Overview README](../api/README.md) — Authentication and token generation
- [Multi-tenancy Architecture](../architecture/multi-tenancy.md) — Tenant-level data separation
- [Getting Started Overview](../getting-started/overview.md) — End-user concepts

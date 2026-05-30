# Admin Announcement Banner

## Summary

The admin announcement banner system lets administrators publish site-wide notifications that appear at the top of every page in the LedgerLeap application. Banners support three urgency levels, scheduled visibility windows, per-tenant or cross-tenant scope, sticky positioning, and optional call-to-action links. Users can dismiss non-critical banners, and critical banners remain visible until archived by an administrator.

This page is for administrators who manage the announcement banner lifecycle.

## Admin Surface

Banner management is performed through the Filament admin panel, accessed from a navigation item registered in the admin panel provider. Both the main resource and legacy pages use `shouldRegisterNavigation = false` — they are reached through the navigation group mechanism, not the main sidebar.

### Admin Announcement Resource (Primary)

The `AdminAnnouncementResource` provides full CRUD management of announcements with a table index, create form, and edit form. The resource requires one of three permissions: `create_admin_announcements`, `update_admin_announcements`, or `delete_admin_announcements`.

**Table columns:**

| Column | Description |
|---|---|
| **Status** | Display status badge with color and icon: `published` (green), `scheduled` (blue), `draft` (gray), `ended` (secondary), `archived` (warning) |
| **Title** | Searchable and sortable banner title |
| **Level** | Badge: `info`, `warning`, or `critical` |
| **Scope** | Badge: current tenant name or `all_tenants` |
| **Starts At** | Scheduled visibility start (sortable datetime) |
| **Ends At** | Scheduled visibility end (sortable datetime) |
| **Updated At** | Last modification (sortable datetime) |
| **Creator** | User who created the banner |
| **Modifier** | User who last modified the banner |

**Record actions:**
- **Edit** — Available for users with `update_admin_announcements` permission
- **Duplicate (Replicate)** — Creates a copy with status reset to `draft` and clears `published_at`
- **Delete** — Available for users with `delete_admin_announcements` permission

**Bulk actions:** Delete bulk action for users with `delete_admin_announcements`.

### Legacy Pages

Two standalone Filament pages provide an alternative editing experience with live preview on the same page.

**Banner Index** (`AdminAnnouncementBannerIndex`, slug `admin-announcement-banners`): Lists all announcements and provides a **Create** action linking to the settings form.

**Banner Settings** (`AdminAnnouncementBannerSettings`, slug `admin-announcement-banner-settings`): Two-column form with live preview. The left column contains all editable fields; the right column renders the banner as it would appear to end users.

#### Fields

| Field | Type | Required | Description |
|---|---|---|---|
| **Status** | Select (disabled) | Read-only | Current lifecycle status: `draft`, `published`, or `archived` |
| **Title** | TextInput | Yes | Banner heading text. Maximum 120 characters. |
| **Body** | Textarea | Yes | Banner message body. Supports concise text. |
| **Level** | Select | Yes | Urgency level: `info` (blue), `warning` (amber), `critical` (red). Critical banners force sticky and non-dismissible behavior. |
| **Scope** | Select | Yes | Visibility scope: `current_tenant` (visible only in the tenant where the banner was created) or `all_tenants` (visible across all tenants). |
| **Sticky** | Toggle | No | When enabled, the banner uses fixed positioning at the top of the viewport and remains visible during scroll. Disabled (and forced on) when level is `critical`. |
| **Starts At** | DateTimePicker | Yes | Visibility window start. The banner will not appear before this time. |
| **Ends At** | DateTimePicker | Yes | Visibility window end. The banner will not appear after this time. Must be after `starts_at`. |
| **CTA Label** | TextInput | No | Call-to-action button label. Maximum 80 characters. |
| **CTA URL** | TextInput | No | Call-to-action destination URL. Must be a valid URL. CTA only renders when both label and URL are filled. |

#### Header Actions

| Action | Icon | Description |
|---|---|---|
| **Back to List** | Arrow-uturn-left | Return to the banner index page |
| **Save Draft** | Pencil-square | Save the current form state as a draft without publishing |
| **Publish** | Megaphone | Validate the draft and publish the banner (sets status to `published`) |
| **Archive** | Archive-box | Archive the banner (sets status to `archived`). Requires confirmation. |

## Effects

### Lifecycle

Banners progress through three statuses:

```
draft ──publishAnnouncement()──► published ──archiveAnnouncement()──► archived
draft ──archiveAnnouncement()──► archived
```

- **Draft**: The banner is saved but not yet visible to end users. Can be edited and previewed freely.
- **Published**: The banner is live and appears to end users within the configured scope and time window. The `published_at` timestamp is automatically set on the first publish. The `revision` hash is recalculated on every save to track content changes, ensuring users see updated banners even after dismissal.
- **Archived**: The banner is hidden from end users. Cannot be re-published (must create a new banner).

### Banner Rendering

Published banners render via the `components.admin.announcement-banner` Blade component, which is included in the application layout (`layouts.app`). The component supports:

- **Level-based styling**: `info` uses neutral coloring, `warning` uses amber/alert styling, `critical` uses error/red styling (`bg-error/20`).
- **Dismissal**: Non-critical banners show a close button. Dismissal is persisted to the user's browser via a `dismiss_storage_key` derived from the banner content revision. When the banner content changes, the key changes and previously dismissed users see the updated banner again.
- **Sticky positioning**: When `sticky` is enabled (always true for critical), the banner uses `fixed top-0 z-50` positioning. The drawer layout offsets its header to accommodate the banner height.
- **Multiple banners**: When multiple published announcements exist, they stack vertically in the feed, ordered by priority (descending) then by start date.

### Dismissal Behavior

| Level | Sticky | Dismissible | Notes |
|---|---|---|---|
| `info` | Optional | Yes | Standard informational banner |
| `warning` | Optional | Yes | Caution-level banner |
| `critical` | Always forced | No | Dismiss button hidden; must be archived to remove |

The banner component uses Alpine.js with `x-cloak` for progressive rendering and `localStorage` for dismiss persistence.

## Constraints

- **Both `shouldRegisterNavigation = false`**: The index and settings pages are not in the main sidebar. Access them through the navigation group mechanism or direct URL.
- **Critical banners cannot be dismissed**: Users cannot close critical-level banners; only an administrator can archive them.
- **Time window enforcement**: The `AdminAnnouncementService::isAnnouncementActive()` method checks that the current time is between `starts_at` and `ends_at`. Banners outside their window are not rendered regardless of status.
- **Config fallback**: When no database-published announcements exist, the system falls back to `ledgerleap.announcement_banner.current` (single banner) or `ledgerleap.announcement_banner.feed` (array of banners) from the application configuration. This is useful for environment-level overrides or testing.
- **Preview reset**: The preview panel uses a `previewResetNonce` counter to force re-rendering and reset dismissal state during editing.
- **Validation on publish**: The publish action validates all required fields (title, body, level, scope, starts_at, ends_at) and enforces that `ends_at` is after or equal to `starts_at`. CTA requires both label and URL to be present together.
- **Revision hash**: Content changes automatically regenerate the `revision` hash, which changes the `dismiss_storage_key`. Users who dismissed a previous version of the same banner will see it again after an update.

## Related Resources

- [Notifications, History, and Announcements](../features/notifications-history-and-announcements.md) — End-user notification and activity features
- [Users and Organizations](users-and-organizations.md) — User management
- [Getting Started Overview](../getting-started/overview.md) — End-user concept overview

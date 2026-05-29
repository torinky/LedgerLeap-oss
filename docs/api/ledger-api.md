# Ledger API

## Summary

The Ledger API provides REST endpoints for creating, listing, viewing, and updating ledger records through HTTP. Use these endpoints for programmatic ledger management without the web UI.

For search-only use cases, use the [Search API](search-api.md) instead, which offers richer query options and synonym-aware keyword matching.

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/v1/ledgers` | List and search ledgers |
| `POST` | `/api/v1/ledgers` | Create a new ledger |
| `GET` | `/api/v1/ledgers/{ledger}` | Get a single ledger with full detail |
| `PATCH` | `/api/v1/ledgers/{ledger}` | Partially update a ledger |

All endpoints require a Sanctum Bearer token:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

See the [API Overview README](README.md#認証) for token generation methods.

## Parameters & Fields

### `GET /api/v1/ledgers` — List & search

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `q` | `string` | No | Full-text search keyword (Mroonga `MATCH() AGAINST()`) |
| `creator_id` | `integer` | No | Filter by creator user ID |
| `created_from` | `date` | No | Creation date start (`YYYY-MM-DD`) |
| `created_to` | `date` | No | Creation date end (`YYYY-MM-DD`) |
| `created_between` | `string` | No | Creation date range (`from,to`) |
| `tags` | `string` | No | Comma-separated tag names |
| `ledger_define_id` | `integer` | No | Filter by ledger definition ID |
| `folder_id` | `integer` | No | Filter by folder ID (includes subfolders) |

Filter parameters can also be passed under a `filter` key:
```
GET /api/v1/ledgers?filter[creator_id]=1&filter[created_between]=2025-01-18,2025-01-19
```

### `POST /api/v1/ledgers` — Create

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ledger_define_id` | `integer` | Yes | Ledger definition ID to use as template |
| `folder_id` | `integer` | Yes | Folder to place the ledger in |
| `content` | `object` | Yes | Key-value map of column IDs to values |
| `tags` | `string[]` | No | Array of tag names to attach |
| `comment` | `string` | No | Comment for the creation |

Example request body:

```json
{
    "ledger_define_id": 52,
    "folder_id": 5,
    "content": {
        "1": "Daily report title",
        "2": "2025-01-18",
        "3": "Report body text here"
    },
    "tags": ["営業", "日報"]
}
```

### `GET /api/v1/ledgers/{ledger}` — Get single

Takes a single path parameter:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ledger` | `integer` | Yes | Ledger ID in the URL path |

### `PATCH /api/v1/ledgers/{ledger}` — Update

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ledger` | `integer` | Yes | Ledger ID in the URL path |
| `content_patch` | `object` | Yes | Partial content update (only the column IDs to change) |
| `comment` | `string` | No | Comment for the update |

Example request body:

```json
{
    "content_patch": {
        "3": "Updated report body text"
    },
    "comment": "Corrected report details"
}
```

Only the column IDs present in `content_patch` are updated. Unlisted columns retain their current values.

## Responses

### `GET /api/v1/ledgers` — List response (200)

```json
{
    "ledgers": [
        {
            "id": 58,
            "define": { "id": 52, "name": "[DEMO] 営業日報" },
            "created_at": "2025-01-18T10:00:00Z"
        }
    ],
    "total": 7
}
```

### `POST /api/v1/ledgers` — Create response (201)

Returns the created ledger as a `LedgerResource` with its definition, folder, and tags preloaded.

### `GET /api/v1/ledgers/{ledger}` — Single ledger response (200)

Returns a `LedgerDetailResource` containing:
- Current column values with their definitions
- Workflow status
- Folder and tenant context
- Attachment counts

Use this endpoint to confirm the latest state before applying a PATCH update.

### `PATCH /api/v1/ledgers/{ledger}` — Update response (200)

Returns a `LedgerDetailResource` with additional `meta`:

```json
{
    "data": { "..." },
    "meta": {
        "previous_status": "draft",
        "current_status": "draft",
        "status_changed": false,
        "returned_to_draft": false
    }
}
```

## Constraints

- **Folder access control**: All ledger endpoints enforce folder-level permissions. Attempts to access or modify ledgers in non-accessible folders return `403`.
- **Workflow status lock**: Ledgers with `APPROVED` status cannot be updated via this API. Attempting to update an approved ledger returns `409`.
- **Pending inspection/approval**: Updating a ledger in `PENDING_INSPECTION` or `PENDING_APPROVAL` status returns it to `DRAFT`. The response `meta` confirms whether the status changed.
- **Update path**: Only `PATCH` (partial update) is supported as the initial public contract. `PUT` (full replacement) is reserved for future expansion.
- **Tags in updates**: Tag operations (`tag_operation` + `tag_values`) are not yet supported by the REST update contract. Only `content_patch` and `comment` are accepted.
- **Response shape**: The `data` key wraps single-resource responses; list responses use `ledgers` + `total`. Clients should parse these top-level keys accordingly.

## Related Resources

- [API Overview README](README.md) — Authentication, common request/response formats, and full endpoint table
- [Search API](search-api.md) — Dedicated search endpoint with synonym-aware keyword matching
- [Bootstrap Manifest API](bootstrap-manifest-api.md) — AI client discovery contract
- [MCP Client Guide](mcp-client-guide.md) — MCP-based ledger operations
- [OpenAPI Specification](openapi.json) — Full contract reference

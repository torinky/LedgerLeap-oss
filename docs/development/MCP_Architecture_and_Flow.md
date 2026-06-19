# MCP Architecture & Flow

**Purpose:** Developer-facing technical reference for LedgerLeap's Model Context Protocol (MCP) implementation — server architecture, tool contracts, authentication, data structures, and transport layer.

**Audience:** LedgerLeap developers and technical contributors.

---

## Scope

This document covers the MCP server (`LedgerLeapServer`), all registered MCP tools, the dual transport layer (STDIO + HTTP), authentication and authorization flow, response optimization conventions, content data structure alignment, and known constraints.

It does **not** cover roadmap items, planning artifacts, or work-in-progress notes. Those live in `docs/work/`.

---

## Architecture

### System Diagram

```
┌─────────────────┐    MCP Protocol    ┌─────────────────┐
│   LLM Client      │ ←─────────────────→ │ LedgerLeap      │
│  (Claude/GPT etc.)│                    │ MCP Server       │
│                   │                    │                   │
│  • Natural language│                    │  • Ledger tools   │
│  • Tool invocation │                    │  • Database       │
│  • Response gen    │                    │  • Auth / ACL     │
└─────────────────┘                    └─────────────────┘
       │                                        │
       │                                        │
    User dialogue                          Laravel application
```

### Components

| Component | Role |
|---|---|
| `LedgerLeapServer` (`app/Mcp/Servers/LedgerLeapServer.php`) | Registers tools, declares `instructions` (LLM system prompt), exports server metadata. |
| Tool classes (`app/Mcp/Tools/`) | Each tool implements one user-facing operation (search, create, update, etc.). |
| `AuthenticatedMcpTool` trait (`app/Mcp/Traits/`) | Shared authentication + folder-permission enforcement for all tools. |
| `ResponseHelper` / `TranslationHelper` | Shared response formatting and translation-key resolution. |
| Laravel MCP library | Protocol wire format, STDIO transport, tool routing. |

### `instructions` Property

`LedgerLeapServer::$instructions` is a Markdown string sent to the LLM at connection time as part of the `initialize` handshake. It provides:

- Semantic role definition for the assistant.
- Translation-key and `__summary__` / `__display_fields__` usage guidance.
- Natural-language query → tool parameter mapping examples (e.g., "昨日" → date filter).

The property is **not** evaluated per-request — it is fixed at server bootstrap.

---

## MCP Tools

### Tool Inventory

| Tool | Purpose |
|---|---|
| `GetClientBootstrapManifestTool` | Resolve bootstrap manifest (capabilities, resources, prompts) for an authenticated MCP client. |
| `GetLedgerDefinesTool` | List accessible ledger definitions; supports title-fragment lookup and folder scoping. |
| `GetLedgerDetailTool` | Fetch single-ledger detail with column definitions, workflow state, and editability. |
| `GetRelatedLedgersTool` | Return related ledger candidates by identifier match, semantic similarity, or both. |
| `SearchLedgersTool` | Full-text / date / creator / folder / tag search with synonym-aware query expansion. |
| `CreateLedgerTool` | Create a new ledger under a given folder and ledger definition. |
| `UpdateLedgerTool` | Apply a partial content patch (column-ID keyed) to an existing ledger. Supports `dry_run`. |
| `GetPendingApprovalsTool` | List pending inspection and approval tasks assigned to the authenticated user. |
| `GetWorkflowHistoryTool` | Fetch workflow history; supports two-version diff comparison (`base_diff_id` / `target_diff_id` or `compare_latest_vs_previous`). |
| `ExecuteApprovalTool` | Execute approval actions (approve, return to draft). |
| `ClaimWorkflowTaskTool` | Claim a workflow task (assign to self). |

### Standard Update Workflow

The recommended MCP update flow enforces a read-before-write pattern:

```
SearchLedgersTool → GetLedgerDetailTool → GetLedgerDefinesTool
  → UpdateLedgerTool(dry_run=true) → UpdateLedgerTool
```

`dry_run` returns a minimal column-level diff (`changed_columns`) without persisting.

### Related Ledger Investigation

`GetRelatedLedgersTool` returns candidates with a `reason` field (`identifier` / `semantic` / `both`), a `score`, and enough identifying information for follow-up detail queries.

### Workflow History Comparison

`GetWorkflowHistoryTool` returns:
- `changed_fields`, `changed_by`, `changed_at`
- Version identifiers for both sides of the comparison
- Minimal `next_actions` hints

The internal diff engine is not exposed in the response.

### Bootstrap Manifest Tool

`GetClientBootstrapManifestTool` accepts `client_type`, `role_profile`, `model_profile`, and `language`, and reuses `BootstrapManifestService::resolve()` to return a client-facing bundle: `recommended_capabilities`, `resources`, `prompts`, `files`, `placement_instructions`, `warnings`.

---

## Authentication

### Token-Based Auth

```
Request → auth:sanctum middleware → AuthenticatedMcpTool::authenticateUser()
```

Two paths are supported:

1. **HTTP transport**: `auth:sanctum` middleware resolves the user from the `Authorization: Bearer` header.
2. **Local CLI / fallback**: reads `MCP_AUTH_TOKEN` env var, resolves `PersonalAccessToken`, and calls `Auth::setUser()`.

### Tenant Access Enforcement

Remote HTTP routes include `EnsureAuthenticatedUserHasCurrentTenantAccess` middleware — the authenticated user must have active access to the current tenant, regardless of token abilities.

### Folder Permission Checks

Tools that reference a `folder_id` call `AuthenticatedMcpTool::checkFolderPermissionOrError()` which gates on the user's `READ`/`WRITE` permission for that folder via `WritableFolderRepository`.

---

## Data Flow

### Request → Response

```
LLM Client                    MCP Server
    │                              │
    │ ─── initialize ──────────→   │  sends server name, version, instructions, tool list
    │ ←── server_info ──────────   │
    │                              │
    │ ─── tools/call ──────────→   │  Tool::handle() → Service → DB
    │ ←── result ───────────────   │  JSON-RPC response with structured payload
```

### Response Conventions

Every tool response that targets LLM consumption uses two underscore-prefixed keys:

| Key | Purpose |
|---|---|
| `__summary__` | Natural-language summary the LLM should present first. |
| `__display_fields__` | Map of Japanese display label → value, for LLM-driven table/bullet rendering. |

#### Example (SearchLedgersTool, `format=summary`)

```json
{
  "ledgers": [
    {
      "id": 112,
      "__display_fields__": {
        "件名": "2025年1月18日営業日報",
        "ステータス": "承認待ち",
        "更新日時": "2025年1月18日 18:30"
      }
    }
  ],
  "total": 2,
  "__summary__": "あなたが昨日作成した台帳は2件です。"
}
```

The raw machine-readable fields remain in the response alongside `__display_fields__` and `__summary__`.

### Translation Keys

All user-visible strings in MCP responses must use `trans()` / `__()` with keys from `lang/ja/ledger.php`. Hardcoded Japanese strings are not permitted. The existing translation system already covers workflow states, activity log columns, mail bodies, and common UI labels — reuse them before adding new keys.

---

## Data Structures

### Ledger `content` Array

`Ledger::$content` is a **numeric-indexed array**, not an associative array. Each index corresponds to a `column_define` entry ID.

```php
// Column definition (from LedgerDefine)
$columnDefine = [
    ['id' => 0, 'name' => 'title',  'type' => 'text'],
    ['id' => 1, 'name' => 'amount', 'type' => 'number'],
    ['id' => 2, 'name' => 'memo',   'type' => 'textarea'],
];

// Corresponding content
$content = [0 => 'タイトル', 1 => 1000, 2 => '備考'];

// Access
$title  = $content[0];
$amount = $content[1];
$memo   = $content[2];
```

**Rules:**
- Never use `data_get()` on content — use direct array access.
- Never `json_encode()` cast-array columns (`files`, `chk`, etc.).
- `content_attached` requires the `[0 => []]` sentinel at index 0.
- `latest_diff_id` must be set explicitly; the factory does not cascade it.

### Column-Define → Display Mapping

The `RecordsTable` Blade component and summary-formatted MCP responses both derive display logic from `column_define`. Title extraction uses column ID 0 or the first column whose name matches a title heuristic.

---

## Configuration

### Routes (HTTP Transport)

```php
// Path-based (recommended)
Mcp::web('/{tenant}/mcp/ledgerleap', LedgerLeapServer::class)
    ->middleware([
        InitializeTenancyByPath::class,
        'auth:sanctum',
        EnsureAuthenticatedUserHasCurrentTenantAccess::class,
    ]);

// Subdomain-based (compatibility)
Mcp::web('/mcp/ledgerleap', LedgerLeapServer::class)
    ->middleware([
        InitializeTenancyByDomain::class,
        'auth:sanctum',
        EnsureAuthenticatedUserHasCurrentTenantAccess::class,
    ]);
```

### Commands

```bash
php artisan mcp:start ledgerleap:mcp      # Start STDIO server
php artisan mcp:inspector ledgerleap:mcp  # Browser-based debugger at localhost:6274
```

### Token Generation

```php
// Tinker
$user = User::find(1);
$token = $user->createToken('MCP Access');
echo $token->plainTextToken;
```

---

## Constraints & Edge Cases

### Mroonga Full-Text Search

Mroonga does **not** support composite `MATCH() AGAINST()` across multiple columns. For multi-column searches, combine with `OR`:

```sql
-- ❌ Does not work
SELECT * FROM ledgers WHERE MATCH(content, content_attached) AGAINST('keyword');

-- ✅ Works
SELECT * FROM ledgers WHERE
  MATCH(content) AGAINST('keyword') OR
  MATCH(content_attached) AGAINST('keyword');
```

### N+1 Prevention

Eager-load relations before iterating search results:

```php
$ledgers = Ledger::query()
    ->withNeededRelations()
    ->with(['define.folder'])
    ->get();
```

### Error Handling

Tools return typed error responses:

```php
// Auth
Response::error('Authentication token not provided.', 401);
Response::error('Invalid authentication token.', 401);

// Permission
Response::error('Insufficient permissions.', 403);

// Server
Response::error('Internal server error.', 500);
```

Exceptions that reach the MCP library layer are logged via `Log::error()` before the error response is emitted.

### Debugging

```php
Log::info('MCP SearchLedgers called', [
    'user_id' => $user->id,
    'parameters' => $parameters,
    'results_count' => $results['total'],
]);
```

Use `php artisan mcp:inspector ledgerleap:mcp` for interactive protocol inspection.

### Transport

| Transport | Use Case |
|---|---|
| STDIO | Local CLI / IDE integration |
| HTTP | Remote AI client (Claude, ChatGPT, etc.) |

HTTP transport requires a valid Sanctum token. Path-based tenant URLs are preferred over subdomain-based.

---

## Evidence

- Public API contract: `docs/api/README.md`, `docs/api/openapi.json`
- Translation keys: `lang/ja/ledger.php`
- MCP prompt guidelines: `docs/development/MCP_Prompt_Guidelines.md`
- Scoring system: `docs/development/scoring-system.md`
- Bootstrap manifest: `app/Services/BootstrapManifestService.php`

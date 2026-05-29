# Bootstrap Manifest API

## Summary

The Bootstrap Manifest API provides REST endpoints for AI clients to discover LedgerLeap's capabilities, available resources, prompt templates, and placement instructions before connecting to the MCP server. This is the minimal HTTP contract that an AI client uses to bootstrap itself for the first time.

These endpoints are **tenant-agnostic** — they do not require tenant context in the URL, unlike all other LedgerLeap API routes.

For MCP resource–based bootstrap discovery, see the [MCP Client Guide](mcp-client-guide.md).

## Contract & Surface

Two endpoints are available:

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/v1/ai/bootstrap-manifest` | Bootstrap resolution via query parameters |
| `POST` | `/api/v1/ai/bootstrap-manifest/resolve` | Bootstrap resolution via JSON body |

Both endpoints are authenticated with a Sanctum Bearer token (`Authorization: Bearer <token>`).

### Authentication

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

Tokens must be valid Sanctum tokens. No `mcp:*` ability is required — standard API tokens suffice for these endpoints, unlike the MCP server.

See the [API Overview README](README.md#%E8%AA%8D%E8%A8%BC) for token generation methods.

## Parameters & Fields

### GET `/api/v1/ai/bootstrap-manifest`

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `client_type` | `string` | Yes | — | Client identifier (e.g. `claude`, `chatgpt`, `generic`). Must be one of the supported client types. |
| `role_profile` | `string` | Yes | — | Role profile identifier. Defines which capabilities and resources to include. |
| `model_profile` | `string` | No | `general-local` | Model capability profile. Use `general-local` for local/on-prem models with limited context, `general-cloud` for cloud models. |
| `language` | `string` | No | `ja` | Response language. Defaults to Japanese. |

### POST `/api/v1/ai/bootstrap-manifest/resolve`

Accepts the same fields as JSON body:

```json
{
    "client_type": "claude",
    "role_profile": "general-user",
    "model_profile": "general-local",
    "language": "ja"
}
```

## Responses & Effects

### Success (200)

Both endpoints return a `BootstrapManifestResource` JSON envelope:

```json
{
    "data": {
        "recommended_capabilities": [
            "search_ledgers",
            "get_ledger_detail",
            "create_ledger",
            "update_ledger"
        ],
        "resources": [
            {
                "uri": "ledgerleap://bootstrap/{client}",
                "description": "Client-specific bootstrap manifest"
            }
        ],
        "prompts": [
            {
                "name": "bootstrap-client-skills",
                "description": "Initial onboarding prompts"
            }
        ],
        "files": [],
        "placement_instructions": "...",
        "warnings": []
    }
}
```

The full response schema is defined in the project's [OpenAPI specification](openapi.json).

### Validation Error (422)

```json
{
    "message": "The client_type field is required. (and 2 more errors)",
    "errors": {
        "client_type": ["The client_type field is required."],
        "role_profile": ["The role_profile field is required."]
    }
}
```

### Unauthenticated (401)

Returned when the Bearer token is missing or invalid.

## Examples

### Minimal GET request

```bash
curl -H "Authorization: Bearer <API_TOKEN>" \
     -H "Accept: application/json" \
     "http://localhost/api/v1/ai/bootstrap-manifest?client_type=claude&role_profile=general-user&model_profile=general-local&language=ja"
```

### POST request with structured body

```bash
curl -X POST \
     -H "Authorization: Bearer <API_TOKEN>" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"client_type":"claude","role_profile":"general-user","model_profile":"general-local","language":"ja"}' \
     "http://localhost/api/v1/ai/bootstrap-manifest/resolve"
```

## Constraints

- **Tenant-agnostic**: These endpoints do not initialize tenant context. The returned manifest is the same regardless of the tenant the client will later connect to.
- **REST only**: This document covers only the HTTP REST surface. For MCP resource–based bootstrap discovery (`ledgerleap://bootstrap/{client}`), see the [MCP Client Guide](mcp-client-guide.md).
- **No MCP ability required**: Standard API tokens without `mcp:*` scope work for these endpoints.
- **Stable contract**: The top-level response structure (`recommended_capabilities`, `resources`, `prompts`, `files`, `placement_instructions`, `warnings`) is stable. Individual capability names and resource URIs may evolve.

## Related Sources

- [API Overview (README)](README.md) — token generation, common patterns, endpoint index
- [MCP Client Guide](mcp-client-guide.md) — MCP resource–based bootstrap and tool usage
- [OpenAPI Specification (JSON)](openapi.json) — full machine-readable schema
- Source: `routes/api.php`, `BootstrapManifestController`, `ResolveBootstrapManifestRequest`
- Test: `tests/Feature/Api/BootstrapManifestApiTest.php`

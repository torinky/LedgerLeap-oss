# MCP Client Guide

## Summary
The Model Context Protocol (MCP) implementation in LedgerLeap enables LLM clients (such as Claude, ChatGPT, or local models via LM Studio/Ollama) to interact directly with the ledger management system. Through a set of standardized tools, prompts, and resources, an AI agent can perform complex business workflows—including searching ledgers, retrieving detailed content, managing workflow statuses, and accessing attachment metadata—using natural language instructions.

## Contract & Surface
The MCP server is exposed via an HTTP endpoint that follows the standard LedgerLeap tenant-aware routing pattern.

### Endpoint Structure
Clients should connect to the following URL format:
- **Path-based (Recommended):** `https://{domain}/{tenant}/mcp/ledgerleap`
- **Subdomain-based:** `https://{tenant}.{domain}/mcp/ledgerleap`

The server implementation is defined in `app/Mcp/Servers/LedgerLeapServer.php`.

### Authentication
Access requires a Laravel Sanctum Bearer token with the appropriate `mcp:*` abilities. 
**Note:** Standard Filament-generated tokens may lack the required `mcp:*` scope. Use the following command to generate a compatible token for testing:
```bash
./vendor/bin/sail artisan demo:generate-mcp-token
```

**Required Headers:**
```http
Authorization: Bearer <MCP_TOKEN>
Accept: application/json
Content-Type: application/json
```

## Parameters & Fields
The MCP server provides a "Bootstrap Manifest" to help clients discover available capabilities and optimize their context window. This is implemented via `app/Mcp/Resources/BootstrapClientResource.php`.

### Bootstrap Manifest Structure
When a client requests the bootstrap resource, it receives a structured bundle containing:
- **`recommended_capabilities`**: A list of high-level tasks the agent can perform.
- **`resources`**: URI templates for accessing static or dynamic data (e.g., `ledgerleap://bootstrap/{client}`).
- **`prompts`**: Pre-defined prompt templates to initiate specific workflows.
- **`tools`**: Definitions for the executable tools registered in `LedgerLeapServer`.
- **`placement_instructions`**: Guidance on how to present data within the UI/Chat interface.

### Verification
The integrity of the bootstrap manifest and its resource resolution is verified by:
- `tests/Feature/Mcp/BootstrapClientResourceTest.php` (Resource logic)
- `tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php` (Prompt logic)

See also:
- `app/Mcp/Resources/BootstrapClientResource.php` — resource resolution implementation
- `app/Mcp/Resources/BootstrapClientSkillsResource.php` — skill prompt resource

## Responses & Effects
MCP tools return structured JSON responses designed for both machine parsing and human readability.

### Tool Output Patterns
- **Summary Field (`__summary__`)**: Tools often include a high-level summary. Agents should prioritize this text to provide immediate context in the chat interface.
- **Display Fields (`__display_fields__`)**: For list-based tools, the response includes metadata mapping raw keys to user-friendly Japanese labels. 
- **Error Shapes**: Errors are returned as standard JSON error objects:
  ```json
  {
    "error": "Description of the failure",
    "code": "ERROR_CODE"
  }
  ```

### Verification
The correctness of tool execution and response shapes is validated by:
- `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php` (HTTP routing and error handling)
- `tests/Feature/Mcp/SearchLedgersTool...Test.php` (Specific tool logic)

See also:
- `app/Mcp/Servers/LedgerLeapServer.php` — server registration and tool definitions
- `app/Mcp/Tools/SearchLedgersTool.php` — search tool implementation

## Constraints

### Authentication & Authorization
- **Token Scope**: The token must possess the `mcp:*` ability.
- **Tenant Isolation**: Every request is strictly scoped to the tenant identified in the URL path or host. A token valid for Tenant A cannot be used to access data in Tenant B. Access control is enforced via `EnsureAuthenticatedUserHasCurrentTenantAccess`.

### Performance & Context Management
To prevent context window overflow (especially for local models), clients should adhere to these constraints:
- **Search Optimization**: When using `SearchLedgersTool`, prefer `include_content=false` and `include_meta=false` for initial discovery.
- **Pagination**: Use `limit` and `offset` parameters to manage large result sets.
- **On-Demand Detail**: Use `GetLedgerDetailTool` only after identifying the specific record of interest via a search tool.

## Related Sources
- [API Overview](../README.md)
- [MCP Architecture & Flow](../development/MCP_Architecture_and_Flow.md)

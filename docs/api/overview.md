# LedgerLeap API Overview

This document provides a high-level overview of the LedgerLeap API, its purpose, and how it fits into the ecosystem. For detailed endpoint specifications, please refer to the [OpenAPI Specification (JSON)](openapi.json).

## Purpose and Scope

The LedgerLeap API is a RESTful interface designed to enable seamless integration between the core ledger system and external applications, including:

- **Custom Client Applications**: Web or mobile frontends that require direct data access.
- **Automated Workflows**: Scripts and background processes for automated data entry or reporting.
- **LLM Agents (via MCP)**: AI agents that interact with the ledger through the Model Context Protocol.

The API focuses on providing a stable, secure, and predictable contract for managing ledger records, folder structures, and discovery processes.

## Core Concepts

### 1. Resource-Oriented Design
The API is organized around key resources:
- **Ledger Definitions**: Templates that define the structure and validation rules for ledgers.
- **Ledgers**: The primary data records containing content, status, and audit history.
- **Folders**: Organizational structures for managing access and grouping ledgers.

### 2. Authentication & Security
Security is handled via **Laravel Sanctum**:
- **API Tokens**: For third-party applications and scripts, users can generate scoped tokens.
- **Session-based Auth**: For first-party SPA applications.
- **Tenant Isolation**: Every API request is scoped to a specific tenant. Access is strictly enforced based on the authenticated user's permissions within that tenant context.

### 3. The Bootstrap Discovery Workflow
A unique feature of the LedgerLeap API is its support for "Bootstrap Discovery." This allows new clients (especially AI agents) to:
1. **Discover Capabilities**: Query the API to understand what resources, prompts, and tools are available for a specific user/role.
2. **Initialize Context**: Receive a minimal "bundle" of configuration needed to start interacting with the ledger immediately.

## Relationship with MCP (Model Context Protocol)

While the REST API provides a standard interface for programmatic access, the **MCP** layer acts as a specialized bridge for LLM-based clients.

| Feature | REST API | MCP (via API) |
| :--- | :--- | :--- |
| **Primary User** | Developers / Automated Systems | LLM Agents (ChatGPT, Claude, etc.) |
| **Interaction Style** | Request-Response (Standard HTTP) | Tool-calling / Resource-access (Agentic) |
| **Discovery** | Manual or via OpenAPI | Automated via `bootstrap-manifest` |

The REST API serves as the foundation upon which MCP capabilities are built.

## Getting Started

To begin using the API:

1. **Obtain an API Token**: Generate a token through the LedgerLeap UI or via administrative commands.
2. **Set the Base URL**: Use your instance's domain (e.g., `https://api.yourdomain.com/api/v1/`).
3. **Test Connectivity**: Try a simple `GET` request to an endpoint like `/api/v1/ledger-defines`.

### Example: First Request (cURL)

```bash
curl -X GET "https://your-ledgerleap-domain.com/api/v1/ledger-defines" \
     -H "Authorization: Bearer <YOUR_API_TOKEN>" \
     -H "Accept: application/json"
```

## Reference Links

- [OpenAPI Specification (JSON)](openapi.json) - The complete technical contract.
- [Search API](search-api.md) - Detailed documentation on advanced search capabilities.
- [Bootstrap Manifest API](bootstrap-manifest-api.md) - Documentation on the discovery workflow.
- [MCP Architecture and Flow](../development/MCP_Architecture_and_Flow.md) - Technical deep-dive into MCP integration.

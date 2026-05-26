# Packet handoff: docs/api.mcp-client-guide

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | `docs/api.mcp-client-guide` |
| `feature_family` | MCP contract |
| `doc_area` | `docs/api` |
| `target_slug` | `mcp-client-guide` |
| `target_path` | `docs/api/mcp-client-guide.md` |
| `public_classification` | `mcp-client` |
| `source_status` | `confirmed` |
| `audience` | MCP client integrators |
| `doc_type` | `reference` |
| `doc_format_profile` | `reference` |
| `comment_sync_policy` | `not_applicable` |
| `external_evidence_urls` | https://spec.modelcontextprotocol.io/specification/ |
| `last_confirmed_at` | 2026-05-26 |
| `recheck_after` | 90d |

### Source inputs

- `source_paths`:
  - `app/Mcp/Servers/LedgerLeapServer.php`
  - `app/Mcp/Resources/BootstrapClientResource.php`
  - `tests/Feature/Mcp/`
  - `docs/development/MCP_Architecture_and_Flow.md`
- `code_anchors`:
  - `app/Mcp/Servers/LedgerLeapServer.php:1` (server registration)
  - `app/Mcp/Resources/BootstrapClientResource.php:1` (bootstrap resource)
  - `routes/mcp.php:1` (MCP route definitions)
- `test_anchors`:
  - `tests/Feature/Mcp/BootstrapClientResourceTest.php:1`
  - `tests/Feature/Mcp/RemoteMcpHttpRouteTest.php:1`
- `comment_anchors`: [] (empty — `comment_sync_policy: not_applicable`)
- `must_exclude`:
  - Internal MCP implementation history from `docs/work/`
  - `docker-compose.prod.yml` references
  - Demo token plaintext values
  - Internal server deployment details
  - Specific tenant slug examples
  - Production-only configuration
- `done_when`:
  - [x] Required `reference` sections present: summary, contract_or_surface, parameters_or_fields, responses_or_effects, constraints, related_sources
  - [x] Endpoint structure and authentication documented
  - [x] Bootstrap manifest structure explained
  - [x] Context management constraints documented
  - [x] No internal work-note references in public body

### Style guardrails

- English only (`docs/api/*` is English per #219 language policy)
- Contract-first organization (reference profile)
- Realistic header examples (Bearer token, Content-Type)
- Command examples use `./vendor/bin/sail` prefix
- No inline `path:line` anchors in public body — code references use descriptive filenames

### Comment sync scope

- `comment_sync_policy: not_applicable` — the public doc is an integrator-facing reference that describes HTTP contract and tool surface, not individual PHP symbols. PHPDoc sync for MCP server classes is deferred to a dedicated MCP developer doc packet.

## Packet acceptance

| Observation | Decision | Evidence |
|---|---|---|
| format profile applied | ✅ | `reference` profile sections present |
| public target updated | ✅ | `docs/api/mcp-client-guide.md` created |
| source-derived scope respected | ✅ | `feature_family: MCP contract`, `must_exclude` honored |
| evidence fields captured | ✅ | external_evidence_urls, last_confirmed_at, recheck_after, source_anchor |
| code / test anchors reflected | ✅ | 3 code_anchors, 2 test_anchors |
| comment sync handled | ✅ | `not_applicable` — integrator-facing reference, not symbol-level doc |
| unresolved risks recorded | ✅ | None (fresh doc, no outstanding questions) |

- Done when:
  - [x] packet target `docs/api/mcp-client-guide.md` is created
  - [x] `doc_format_profile` (`reference`) and required sections recorded
  - [x] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` recorded
  - [x] acceptance table filled
  - [x] comment sync decision recorded (`not_applicable`)
  - [x] next sprint has clear handoff (no pending questions)

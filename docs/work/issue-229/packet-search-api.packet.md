---
packet_id: api.search-api
feature_family: api
doc_area: api
target_slug: search-api
target_path: docs/api/search-api.md
public_classification: public
source_status: confirmed
audience: product user
doc_type: reference
doc_format_profile: reference
comment_sync_policy: not_applicable
tracking_record_location: docs/work/issue-229/packet-search-api-record.md
external_evidence_urls:
  - openapi.json
last_confirmed_at: 2026-05-27
recheck_after: 90d
source_paths:
  - docs/api/search-api.md
  - app/Http/Controllers/Api/V1/SearchController.php
  - app/Http/Requests/Api/V1/SearchRequest.php
  - tests/Feature/Api/SearchApiTest.php
code_anchors:
  - app/Http/Controllers/Api/V1/SearchController.php:1
  - app/Http/Requests/Api/V1/SearchRequest.php:1
test_anchors:
  - tests/Feature/Api/SearchApiTest.php:1
must_exclude:
  - docs/work/**
  - internal issue numbers
  - MCP/internal implementation details
done_when:
  - Companion tracking record created at docs/work/issue-229/packet-search-api-record.md
  - Public-facing sections only (summary, contract, parameters, responses, constraints)
  - Internal details removed or moved to companion record
  - Links and examples validated (openapi.json referenced)
  - Syntax checks passed for controller/request/test files
---

# Packet notes

Style guardrails: follow the `User-Facing Public Doc Template` for sections and keep examples minimal. Companion tracking and internal evidence should live in `tracking_record_location`.

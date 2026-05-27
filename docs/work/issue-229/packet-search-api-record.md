---
packet_id: api.search-api
created_at: 2026-05-27T21:50:52+09:00
created_by: Copilot
source_paths:
  - docs/api/search-api.md
  - app/Http/Controllers/Api/V1/SearchController.php
  - app/Http/Requests/Api/V1/SearchRequest.php
  - tests/Feature/Api/SearchApiTest.php
code_anchors:
  - app/Http/Controllers/Api/V1/SearchController.php:1-40
  - app/Http/Requests/Api/V1/SearchRequest.php:30-46
test_anchors:
  - tests/Feature/Api/SearchApiTest.php:96-108
external_evidence_urls:
  - openapi.json
last_confirmed_at: 2026-05-27T21:50:52+09:00
recheck_after: 90d

summary: "Companion tracking record for the Search API publication packet. Records anchors, verification outputs, and acceptance checklist evidence."

---

# Evidence excerpts

## Controller (SearchController.php) — excerpt

```php
// from app/Http/Controllers/Api/V1/SearchController.php
public function search(SearchRequest $request, LedgerService $ledgerService)
{
    $validatedParams = $request->validated();
    $result = $ledgerService->searchLedgersForApi($request->user(), $validatedParams);
    return $response;
}
```

## Request validation (SearchRequest.php) — excerpt

```php
// from app/Http/Requests/Api/V1/SearchRequest.php
return [
    'q' => ['nullable', 'string', 'max:255'],
    'tags' => ['nullable', 'string', 'max:255'],
    'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
    'ledger_define_id' => ['nullable', 'integer', 'exists:ledger_defines,id'],
];
```

## Test anchor (SearchApiTest.php) — excerpt

```php
// from tests/Feature/Api/SearchApiTest.php
public function test_admin_can_search_all_ledgers()
{
    tenancy()->initialize(static::$sharedTenantForMigrationsOnce);
    $this->actingAs(self::$adminUser, 'sanctum')
        ->getJson('/api/v1/search')
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
}
```

# Verification

- PHP syntax checks (php -l):
  - app/Http/Controllers/Api/V1/SearchController.php: No syntax errors
  - app/Http/Requests/Api/V1/SearchRequest.php: No syntax errors
  - tests/Feature/Api/SearchApiTest.php: No syntax errors

- Examples and links present in docs/api/search-api.md (curl examples, test command)

# Acceptance evidence

- Companion record created at docs/work/issue-229/packet-search-api-record.md
- Internal details moved to this companion record
- Basic syntax checks passed for controller/request/test files

# Notes

- Test execution of SearchApiTest.php via Sail was not executed in this environment (requires Docker/Sail). The packet acceptance records the test anchor and recommends running `./vendor/bin/sail test tests/Feature/Api/SearchApiTest.php` in CI or a Sail-enabled developer environment as the final verification step.

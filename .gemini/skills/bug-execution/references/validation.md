# LedgerLeap Validation Order

Choose the relevant checks based on the bug you fixed.

## Core Checks
1. `./vendor/bin/sail pint`
2. `./vendor/bin/sail test` or targeted tests
3. Browser/UI smoke when the issue involved Livewire or frontend behavior

## Extra Checks by Change Type

### Frontend / Livewire
- New Tailwind utility classes require `./vendor/bin/sail npm run build`
- Confirm no Livewire public object state was introduced
- Confirm parent-child calls still use the expected pattern

### ACL / Permission
- Verify permission cache and tenant access cache behavior if roles/orgs/users changed
- Re-check effective access, not only DB state

### Tenancy
- Feature tests still initialize tenancy
- Tenant-aware render paths have `tenant_id` fallback where required

### Search / Mroonga / RAG
- Keep single-column `MATCH() AGAINST()` constraints intact
- Re-index only when configuration or model changes justify it

### External Service Isolation
- Tests should fake or isolate Embedding / OCR / LDAP / similar dependencies when relevant

## Output
Record the validation result as PASS / FAIL and note retries or known flakes.


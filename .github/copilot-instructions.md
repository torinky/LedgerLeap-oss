# LedgerLeap — Copilot Instructions
## Repository
- **owner**: `torinky` / **repo**: `LedgerLeap`
- **Stack**: PHP 8.4 / Laravel 12 / MySQL (Mroonga) / Livewire / Alpine.js / TailwindCSS (daisyUI, maryUI)
## Critical Constraints
- **Mroonga full-text search**: Single-column `MATCH() AGAINST()` only. Composite indexes do NOT work.
- **Tenant init in tests**: Every Feature test `setUp()` MUST call `tenancy()->initialize($tenant)`.
- **AsColumnArrayJson access**: `data_get()` does NOT work. Use direct array access: `$ledger->content[0]`.
- **No manual json_encode**: Never call `json_encode()` on `files`, `chk`, or other cast-array columns.
- **Livewire state**: Public properties must be plain arrays. Objects cause serialization errors.
- **Livewire parent calls**: Use `$parent.method()` for sort/filter; not `Livewire.dispatch()`.
- **Tailwind JIT**: After adding new utility classes, run `sail npm run build`.
- **Model events in Sail**: Use `$model->update([...])` not `touch()` in event-driven tests.
- **Permission cache**: Role/Org/User change requires both `flushAllUserPermissionsCache()` + `TenantAccessService::clearAllCache()`. See `permission-model` skill.
- **Full-text search tests**: Use `DatabaseMigrationsOnce` trait, not `RefreshDatabase`.
- **Git in Sail env**: Always use `bash -c "cd /path && git ..."`. See `git-commit` skill.
- **`#[Lazy]` + tenant**: In `render()`, fall back to `$model->tenant_id` — never rely solely on `tenant()?->id`.
## Architecture Patterns
- Business logic: `App\Services`
- Interactive UI: Livewire with single-source-of-truth state array
- ACL: `Spatie\Permission` + `WritableFolderRepository` (folder-level)
- Data access: always verify live data via MCP tools before reasoning from static files
## Skills — Load when triggered
| Trigger | Skill |
|---|---|
| `git commit` | `git-commit` |
| GitHub issue / PR | `github-issue-workflow` |
| CI failure / timeout | `ci-failure-investigation` |
| External service / `AttachedFile` test | `test-external-dependency-isolation` |
| Mroonga / `DatabaseMigrations` trait | `database-migrations-test-optimization` |
| `tenant()` null in Livewire | `livewire-tenant-context` |
| `content[n]` null / index shift | `ledger-content-data-structure` |
| `wire:loading` / `x-show` / sticky header | `livewire-loading-ui` |
| `#[Computed]` 0% / `#[Url]` / parent-child | `livewire-computed-properties` |
| 403 / permission cache stale | `permission-model` |
| Workflow status stuck / `latestDiff` null | `workflow-status-machine` |
| RAG search wrong / re-index / CI timeout | `rag-vector-search` |
| git silent / CSS stale / test DB broken | `sail-dev-workflow` |
| Sprint end / new skill | `skill-maintenance` |
## Workflow
1. **Lint**: `./vendor/bin/sail pint` before commit
2. **Error check**: `last-error` / `browser-logs` after every change
3. **Test**: `./vendor/bin/sail test` for regressions
4. **Commit**: load `git-commit` skill first
5. **After sprint**: load `skill-maintenance` skill

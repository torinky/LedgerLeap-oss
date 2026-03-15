# LedgerLeap — Copilot Instructions
- **Repo**: `torinky/LedgerLeap`
- **Stack**: PHP 8.4 / Laravel 12 / MySQL (Mroonga) / Livewire / Alpine.js / TailwindCSS (daisyUI, maryUI)
## Critical Constraints
- **Mroonga**: Single-column `MATCH() AGAINST()` only. Composite indexes do NOT work.
- **Tenant init in tests**: Every Feature test `setUp()` MUST call `tenancy()->initialize($tenant)`.
- **AsColumnArrayJson**: `data_get()` does NOT work. Use direct array access: `$ledger->content[0]`.
- **No manual json_encode**: Never call `json_encode()` on `files`, `chk`, or other cast-array columns.
- **Livewire state**: Public properties must be plain arrays. Objects cause serialization errors.
- **Livewire parent calls**: Use `$parent.method()` for sort/filter; not `Livewire.dispatch()`.
- **Tailwind JIT**: After adding new utility classes, run `sail npm run build`.
- **Model events in Sail**: Use `$model->update([...])` not `touch()` in event-driven tests.
- **Permission cache**: Role/Org/User change requires both `flushAllUserPermissionsCache()` + `TenantAccessService::clearAllCache()`.
- **Remote MCP tenant security**: `mcp:*` token ability alone is insufficient. Web MCP routes must also enforce current-tenant access (for example `EnsureAuthenticatedUserHasCurrentTenantAccess`), and path-based tenant MCP URLs should stay aligned with the app’s normal tenant URL style.
- **Tests run in Sail**: Run tests via `./vendor/bin/sail test` / `./vendor/bin/sail pest`. Host-side `php artisan test` / `./vendor/bin/pest` is unsupported.
- **FTS tests**: Use `DatabaseMigrationsOnce`, not `RefreshDatabase`.
- **Git after Sail**: Always use `bash -c "cd /path && git ..."`.
- **`#[Lazy]` + tenant**: In `render()`, fall back to `$model->tenant_id` — never rely only on `tenant()?->id`.
## Architecture Patterns
- Business logic: `App\Services`
- Interactive UI: Livewire with single-source-of-truth state array
- ACL: `Spatie\Permission` + `WritableFolderRepository` (folder-level)
- Data access: verify live data via MCP tools before reasoning from static files
- LLM docs audience split: client-facing docs must use WebUI-observable concepts and business workflows only; DB/Mroonga/Laravel details belong in developer-facing docs
## Prompt Shortcuts
- `/git-commit`, `/github-issue-workflow`, `/ci-failure-investigation`, `/rag-vector-search`, `/bug-investigation`, `/bug-execution`, `/skill-maintenance`
## Auto Context
- Path rules: `.github/instructions/livewire.instructions.md`, `.github/instructions/php-laravel.instructions.md`, `.github/instructions/tests.instructions.md`, `.github/instructions/ai-assets.instructions.md`
- Reusable deep knowledge: `.github/skills/*/SKILL.md`
- Agent-wide routing/meta: `AGENTS.md`
## Workflow
- `./vendor/bin/sail pint` → error check (`last-error` / `browser-logs`) → `./vendor/bin/sail test` → `/git-commit` → `/skill-maintenance`
## Bug Response Principles
- Investigate before changing code: define expected vs actual behavior, reproduction, impact scope, and rollback constraints.
- Evidence order: logs / stack traces → related code / tests / recent changes → repo docs / skills → external sources.
- External research order: official docs → package docs → GitHub Issues / Discussions → similar OSS implementations → trusted articles.
- Separate investigation from execution: use `/bug-investigation` first, `/bug-execution` after selecting an approach.
- Record negative results, confidence, verification plan, rollback plan, and LedgerLeap-specific traps.
- After solving a reusable pattern, sync `.github` assets with `/skill-maintenance`.

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
## Skills (Prompt Files — call with `/SKILLNAME` in chat)
| When you need to… | Call |
|---|---|
| Run `git commit` (encoding-safe, Sail-safe) | `/git-commit` |
| Work on a GitHub issue / PR | `/github-issue-workflow` |
| Investigate CI failure / timeout | `/ci-failure-investigation` |
| Debug RAG search / re-index embedding | `/rag-vector-search` |
| End of sprint / update skill files | `/skill-maintenance` |

> **Auto-loaded** (no action needed): Livewire rules → `.github/instructions/livewire.instructions.md` (applyTo: app/Livewire/**, resources/views/livewire/**), PHP/Laravel rules → `.github/instructions/php-laravel.instructions.md` (applyTo: app/**/*.php), Test rules → `.github/instructions/tests.instructions.md` (applyTo: tests/**)
>
> **Deep reference**: `.github/skills/*/SKILL.md` — read directly when the above instructions are insufficient.
## Workflow
1. **Lint**: `./vendor/bin/sail pint` before commit
2. **Error check**: `last-error` / `browser-logs` after every change
3. **Test**: `./vendor/bin/sail test` for regressions
4. **Commit**: call `/git-commit` prompt first
5. **After sprint**: call `/skill-maintenance` prompt

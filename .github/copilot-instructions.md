# LedgerLeap — Copilot Instructions

## Repository
- **owner**: `torinky` / **repo**: `LedgerLeap`
- **Stack**: PHP 8.4 / Laravel 12 / MySQL (Mroonga) / Livewire / Alpine.js / TailwindCSS (daisyUI, maryUI)

## Critical Constraints

- **Mroonga full-text search**: Composite indexes do NOT work. Use single-column `MATCH() AGAINST()`, combine multiple columns with `OR`.
- **Tenant init in tests**: Every Feature test `setUp()` MUST call `tenancy()->initialize($tenant)`. Missing it returns `null` relations.
- **AsColumnArrayJson access**: `data_get()` does NOT work. Use direct array access: `$ledger->content[0]`.
- **No manual json_encode**: Never call `json_encode()` before saving `files`, `chk`, or other cast-array columns. The cast handles serialization; doing it manually corrupts the DB.
- **Livewire state**: Public properties must be plain associative arrays. Objects cause serialization errors.
- **Livewire parent calls**: For frequent operations (sort/filter), use `$parent.method()` or `$wire.$parent.method()` instead of `Livewire.dispatch()`.
- **Model events in Sail**: `touch()` does not reliably fire `updated` events. Use `$model->update(['column' => 'value'])` in event-driven tests.
- **Permission cache**: When changing `Role`, `Organization`, or `User`, clear caches via `UserService` or call `flushAllUserPermissionsCache()`.
- **Full-text search tests**: Use `DatabaseMigrationsOnce` trait, not `RefreshDatabase`. See skill `database-migrations-test-optimization`.
- **Git in Sail env**: After `sail` commands, plain `cd && git` produces silent empty output. Always use `bash -c "cd /path && git ..."`. See `git-commit` skill.

## Architecture Patterns
- Business logic → `App\Services`
- Interactive UI → Livewire with single-source-of-truth state array
- ACL → `Spatie\Permission` + custom Folder-based permissions
- Data access → always verify live data via MCP tools before reasoning from static files

## Skills — When to Load

| Trigger | Skill path |
|---|---|
| `git commit` to run | `.github/skills/git-commit/SKILL.md` |
| GitHub issue operation | `.github/skills/github-issue-workflow/SKILL.md` |
| CI failure / timeout investigation | `.github/skills/ci-failure-investigation/SKILL.md` |
| Writing tests with `Ledger`, `AttachedFile`, external services | `.github/skills/test-external-dependency-isolation/SKILL.md` |
| Mroonga search tests / `DatabaseMigrations` trait | `.github/skills/database-migrations-test-optimization/SKILL.md` |
| End of sprint / creating or updating a skill | `.github/skills/skill-maintenance/SKILL.md` |

## Workflow
1. **Lint**: run `./vendor/bin/sail pint` before commit
2. **Error check**: use `laravel-boost` (`last-error`, `browser-logs`) after every change
3. **Test**: run `./vendor/bin/sail test` to check for regressions
4. **Commit**: follow Conventional Commits — load `git-commit` skill first
5. **After sprint**: load `skill-maintenance` skill — capture new patterns into skills

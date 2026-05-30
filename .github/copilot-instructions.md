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
- **MaryUI Toast**: `$this->success()` shortcut cannot be caught in Livewire tests. Use `$this->dispatch('mary-toast', ...)` explicitly.
- **Tailwind JIT**: After adding new utility classes, run `sail npm run build`.
- **Model events in Sail**: Use `$model->update([...])` not `touch()` in event-driven tests.
- **Permission cache**: Role/Org/User change requires both `flushAllUserPermissionsCache()` + `TenantAccessService::clearAllCache()`.
- **Translation Keys**: ALWAYS use translation keys (`__('ledger.xxx')`) for UI text. NEVER hardcode natural language strings. Manage via `lang/ja/ledger/` and sync with `artisan translations:compare --force`.
- **OSS mirror**: `torinky/LedgerLeap` is the private repo (default). `torinky/LedgerLeap-oss` is the public mirror — only operate on it when explicitly asked. Never apply destructive changes to the private repo when the task is scoped to OSS only.
- **Remote MCP tenant security**: `mcp:*` token ability alone is insufficient. Web MCP routes must also enforce current-tenant access (for example `EnsureAuthenticatedUserHasCurrentTenantAccess`), and path-based tenant MCP URLs should stay aligned with the app’s normal tenant URL style.
- **Tests run in Sail**: Run tests via `./vendor/bin/sail test` / `./vendor/bin/sail pest`. Host-side `php artisan test` / `./vendor/bin/pest` is unsupported.
- **FTS tests**: Use `DatabaseMigrationsOnce`, not `RefreshDatabase`.
- **Git after Sail**: Always use `bash -c "cd /path && git ..."`.
- **`#[Lazy]` + tenant**: Use the shared Livewire tenant resolver and fall back to `$model->tenant_id`; never rely only on `tenant()?->id`.
## Branch Strategy
- **Naming**: `feature/#xxx-<kebab-desc>` for features, `fix/#xxx-<kebab-desc>` for fixes, `chore/<kebab-desc>` for CI/docs/deps. Always include issue number when applicable. Always lowercase kebab-case.
- **Base branch**: Always branch from `develop`. Hotfixes branch from `main`.
- **Merge → Delete (MANDATORY)**: Delete the branch IMMEDIATELY after merging — both local (`git branch -d`) and remote (`git push origin --delete`). Merged branches are dead branches. Never keep them.
- **PR flow**: Create PR → CI pass → squash/rebase merge to `develop` → delete branch.
- **Release**: Merge `develop` → `main` → tag `vX.Y.Z`. `main` auto-syncs to OSS mirror `torinky/LedgerLeap-oss`.
- **Full reference**: `docs/runbooks/git-branch-workflow.md`
## Architecture Patterns
- Business logic: `App\Services`
- Interactive UI: Livewire with single-source-of-truth state array
- ACL: `Spatie\Permission` + `WritableFolderRepository` (folder-level)
- Data access: verify live data via MCP tools before reasoning from static files
- LLM docs audience split: client-facing docs must use WebUI-observable concepts and business workflows only; DB/Mroonga/Laravel details belong in developer-facing docs
## Prompt Shortcuts
- `/git-commit`, `/github-issue-workflow`, `/ci-failure-investigation`, `/rag-vector-search`, `/bug-investigation`, `/bug-execution`, `/skill-maintenance`, `/browser-har-analysis`, `/client-facing-contract-triage`, `/doc-creation-sprint`, `/doc-publication-packet`
## Auto Context
- Path rules: `.github/instructions/livewire.instructions.md`, `.github/instructions/php-laravel.instructions.md`, `.github/instructions/tests.instructions.md`, `.github/instructions/ai-assets.instructions.md`, `.github/instructions/design.instructions.md`
- Reusable deep knowledge: `.github/skills/*/SKILL.md` (keep each ≤ 120 lines; detail goes to `references/*.md`)
- Agent-wide routing/meta: `AGENTS.md`
## Workflow
- **Pre-flight Branch Health Check**: Before starting any work, run `git branch --merged develop | grep -v 'develop\|main'` and report stale merged branches. If found, offer to delete them. Do this proactively — the user may forget.
- `./vendor/bin/sail pint` → error check (`last-error` / `browser-logs`) → **Identify and run affected tests** (`./vendor/bin/sail test <path>`) → `/git-commit` → **post-merge branch cleanup** → `/skill-maintenance`
- **MANDATORY**: View changes (Blade) MUST be verified via rendering tests or browser interaction to detect broken `route()` calls or variable scope issues.
- **`gh` commands**: Always pass `--repo torinky/LedgerLeap` to `gh` CLI calls to avoid operating on the wrong repo.
- **Doc next-task selection**: Before proposing the next documentation task, always check existing backlogs first (`docs/README.md`, GitHub issues, `docs/work/*`). Never generate candidates without consulting the real backlog.
- **Branch cleanup trigger points** (agent MUST check at these moments):
  - Start of any session: `git branch --merged develop` → report + offer to delete
  - After `/git-commit` push: remind "マージしたらブランチ削除してください"
  - After merge to develop: immediately run `git branch -d <branch> && git push origin --delete <branch>`
  - When user says "done" / "完了" / "マージした": verify branch was deleted
## Bug Response Principles
- Investigate before changing code: define expected vs actual behavior, reproduction, impact scope, and rollback constraints.
- Evidence order: logs / stack traces → related code / tests / recent changes → repo docs / skills → external sources.
- External research order: official docs → package docs → GitHub Issues / Discussions → similar OSS implementations → trusted articles.
- Separate investigation from execution: use `/bug-investigation` first, `/bug-execution` after selecting an approach.
- Record negative results, confidence, verification plan, rollback plan, and LedgerLeap-specific traps.
- After solving a reusable pattern, sync `.github` assets with `/skill-maintenance`.

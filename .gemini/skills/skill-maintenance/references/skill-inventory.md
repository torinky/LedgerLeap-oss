# Skill Inventory and Anti-Patterns

## Anti-Patterns to Avoid

| Anti-pattern | Problem | Fix |
|---|---|---|
| Instructions in Japanese imperative ("〜すること") | Agent interprets as user-to-agent command, not system fact | Rewrite as third-person English fact |
| All details in SKILL.md body | Every token loaded on activation regardless of relevance | Move code examples to `references/` |
| `python3 -c` for commit messages | Shell encoding corrupts Japanese/special chars | `create_file` tool → `python3 script.py` |
| Nested reference chains (A→B→C) | Agent uses `head -100` preview and misses content | Keep all refs one level from SKILL.md |
| `git add -A` before commit | Stages `coverage-*/`, `wnjpn.db`, `.playwright-mcp/` | Explicit `git add <file>` only |
| Duplicate patterns across skills | Maintenance burden, inconsistency | Single source of truth; cross-link |
| `cd /path && git ...` after Sail commands | Silent empty output — git appears to do nothing | Use `bash -c "cd /path && git ..."` |
| `git commit -F /tmp/msg.txt` after Sail | File write succeeds but commit sees no changes | Include both write + commit in one `bash -c` |

---

## LedgerLeap Skill Inventory

| Category | Skill | Trigger |
|---|---|---|
| Git/CI | `git-commit` | any git commit |
| Git/CI | `ci-failure-investigation` | CI failure / timeout |
| GitHub | `github-issue-workflow` | issue / PR operations |
| Testing | `database-migrations-test-optimization` | Mroonga / slow CI |
| Testing | `test-external-dependency-isolation` | AttachedFile / external service |
| Debugging | `bug-investigation` | bug triage / logs / root-cause investigation |
| Debugging | `bug-execution` | selected fix implementation / verification / rollback |
| Livewire | `livewire-tenant-context` | tenant() null / #[Lazy] |
| Livewire | `livewire-loading-ui` | wire:loading / x-show / sticky |
| Livewire | `livewire-computed-properties` | #[Computed] 0% / #[Url] |
| Data | `ledger-content-data-structure` | content[n] null / index shift |
| ACL | `permission-model` | 403 / cache stale / Role change |
| Workflow | `workflow-status-machine` | status stuck / latestDiff null |
| Search | `rag-vector-search` | RAG wrong / re-index / CI timeout |
| DevEnv | `sail-dev-workflow` | git silent / CSS stale / test DB |
| Meta | `skill-maintenance` | proven learning / prompt-skill-instructions sync |

> `name` must equal the directory name (agentskills.io spec). No subdirectory nesting.

---

## Reference Docs (load when working on related areas)

| Area | Document |
|---|---|
| Livewire UI/UX, loading tiers, Alpine.js CSS | `docs/development/Livewire-Best-Practices.md` |
| Multi-tenancy migration, model setup, validation | `docs/development/multi-tenancy-guidelines.md` |
| Performance (Vite, Eloquent, cache, Mroonga index) | `docs/development/performance-optimization.md` |
| VLM/OCR engine setup, switching PaddleOCR version | `docs/development/vlm-ocr.md` |
| MCP tool architecture and data structure | `docs/development/MCP_Architecture_and_Flow.md` |
| Test fundamentals, DB trait selection, tenant setup | `docs/development/testing/` |
| Scoring system architecture and services | `docs/development/scoring-system.md` |

# LedgerLeap — Project Overview

## Stack
PHP 8.4 / Laravel 12 / MySQL+Mroonga / Livewire 3 / Alpine.js / TailwindCSS (DaisyUI, MaryUI) / Filament / Redis / Laravel Sail (Docker)

## Key Packages
- Multi-tenancy: `stancl/tenancy ^3.9`
- ACL: `spatie/laravel-permission ^6.9`
- Full-text: Mroonga (groonga/mroonga:mysql-8.0-latest)
- RAG/Embedding: custom embedding container (port 8000)
- VLM/OCR: PaddleOCR-VL (port 8080)
- Tests: Pest ^3 / PHPUnit ^11

## Architecture
```
Nginx → PHP-FPM (Laravel) → MySQL/Mroonga
                          → Redis (cache/queue)
                          → Queue Worker → Tika/OCR/VLM/Embedding
```

## Directory Map (LedgerLeap-specific)
| Path | Purpose |
|---|---|
| `app/Services/` | All business logic |
| `app/Livewire/` | Interactive UI components |
| `app/Mcp/` | LLM/MCP server tools |
| `app/Filament/` | Admin panel |
| `app/Policies/` | ACL policies |
| `app/Repositories/` | WritableFolderRepository etc. |
| `resources/views/components/ledger/` | Blade components incl. related-reason-badge |
| `resources/js/components/` | Alpine.js components incl. ledger-init-overlay, expandable-content |
| `docs/work/` | Sprint plans & work logs |
| `docs/function/` | Feature specs |
| `.github/skills/` | Agent skills (load on trigger) |

## Core Models
- `Ledger` — content is JSON array; access via `$ledger->content[0]` NOT `data_get()`
- `LedgerDefine` — column_define is `ColumnDefine[]`; `auto_number` columns drive AutoLink
- `Folder` — nested set; ACL base unit
- `AutoLink` — pattern-based auto-link rules
- `AutoNumberPatternService` — generates per-tenant regex patterns (cache key MUST include tenantId)

## Critical Constraints (load skill for details)
| Constraint | Skill |
|---|---|
| Mroonga single-column MATCH only | `database-migrations-test-optimization` |
| `tenancy()->initialize($tenant)` in every Feature setUp | `livewire-tenant-context` |
| `content[0]` not `data_get()` | `ledger-content-data-structure` |
| Cache::remember() key must include `tenant()?->id` | `sail-dev-workflow` → multitenant-cache-pitfalls.md |
| git after sail → `bash -c "cd /path && git ..."` | `sail-dev-workflow` |
| Permission cache flush: both methods needed | `permission-model` |
| `@livewire:navigated` in Blade → Blade directive conflict; use `x-on:livewire:navigated.window.once` | `livewire-loading-ui` → alpine-init-overlay.md |
| Alpine `x-data` inline method shorthand → PHP parse error; use `Alpine.data()` | `livewire-loading-ui` → alpine-init-overlay.md |

## URLs (dev)
- App: http://localhost
- Admin: http://localhost/admin
- Mailpit: http://localhost:8025

## Recent Sprints
- Issue #77 (✅ 完了): 台帳リスト Alpine.js 初期化オーバーレイ — `ledger-init-overlay.js` + `requestIdleCallback` 分散実行
- Issue #76 (✅ 完了): 関連台帳タブ + マルチテナントキャッシュキー修正

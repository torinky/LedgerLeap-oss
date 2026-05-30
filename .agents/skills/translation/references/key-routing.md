# Translation Key Routing and Sync Rules

## Evidence record

```yaml
claim: Ledger translation keys are maintained in PHP and compiled into lang/ja.json
status: confirmed-repo
last_confirmed_at: 2026-04-11
recheck_after: 180d
recheck_trigger:
  - app/Console/Commands/CompareTranslations.php changes
  - lang/ja/ledger.php or any file under lang/ja/ledger/ changes
  - lang/ja.json sync behavior changes
sources:
  - type: repo-proof
    path: app/Console/Commands/CompareTranslations.php
  - type: repo-proof
    path: lang/ja/ledger.php
  - type: repo-proof
    path: lang/ja/ledger/ui.php
  - type: repo-proof
    path: lang/ja/ledger/workflow.php
  - type: repo-proof
    path: resources/views/components/ledger/sticky-action-bar.blade.php
  - type: repo-proof
    path: lang/ja.json
notes: lang/ja/ledger.php is the aggregate source loaded by the compare command; the modular files under lang/ja/ledger/ are the editing units.
```

## Source-of-truth chain

- `lang/ja/ledger.php` is the aggregate PHP source.
- `lang/ja/ledger/*.php` are the modular source files edited by humans.
- `lang/ja.json` is the generated runtime output.
- Never hand-edit `ledger.*` entries in `lang/ja.json`.

## Key path rules

- Use dot notation for every ledger key.
- The root prefix is always `ledger`.
- The aggregate `lang/ja/ledger.php` merges modular files without adding a filename segment.
- Nested arrays continue as dot segments when present.

| Source file | PHP array shape | Blade / PHP key |
|---|---|---|
| `lang/ja/ledger/ui.php` | `['action_bar_open' => '...']` | `ledger.action_bar_open` |
| `lang/ja/ledger/ui.php` | `['action_bar_close' => '...']` | `ledger.action_bar_close` |
| `lang/ja/ledger/workflow.php` | `['workflow' => ['tooltip' => ['current_status_desc' => '...']]]` | `ledger.workflow.tooltip.current_status_desc` |
| `lang/ja/ledger/workflow.php` | `['workflow' => ['status' => ['draft' => '...']]]` | `ledger.workflow.status.draft` |

## Sync workflow

1. Add or update the key in the correct modular PHP file under `lang/ja/ledger/`.
2. Keep the array nesting aligned with the final key path.
3. Sync the generated JSON output.

```bash
# Via Sail (preferred)
./vendor/bin/sail artisan translations:compare --force

# Local fallback
php artisan translations:compare --force
```

4. Use `__('ledger.xxx')` in Blade or PHP.

## Quick checks

- If a key is missing in the UI, confirm the final key uses dots, not slashes.
- If `lang/ja.json` still shows stale values, rerun `translations:compare --force`.
- If the key family changes, update the modular PHP source first and keep JSON generated.

## Current example anchor

The sticky action bar uses `ledger.action_bar_open` and `ledger.action_bar_close` for its toggle labels.

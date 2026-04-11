# Translation and Localization Management

Use this skill when adding, updating, or reviewing LedgerLeap UI text and technical labels.

## What this skill protects

- Never hardcode natural-language UI text in Blade or PHP logic.
- Keep ledger translations in the PHP source tree, not in `lang/ja.json`.
- Use `__('ledger.xxx')` everywhere the app renders ledger text.

## Source of truth

- `lang/ja/ledger.php` is the aggregate PHP source used by the sync command.
- The modular files under `lang/ja/ledger/` are the editing units.
- `lang/ja.json` is generated output for the app runtime.
- Detailed key-routing rules live in [Key routing and sync rules](./references/key-routing.md).

## Workflow

1. Edit the appropriate modular file under `lang/ja/ledger/`.
2. Keep the array nesting aligned with the final key path.
3. Sync the generated JSON output.

```bash
# Via Sail (preferred)
./vendor/bin/sail artisan translations:compare --force

# Local fallback
php artisan translations:compare --force
```

4. Reference the key in Blade or PHP with `__('ledger.xxx')`.

## Quick checks

- Ledger keys use dots only; never use slash-separated keys.
- If a ledger label does not appear, rerun `translations:compare --force`.
- Do not hand-edit `ledger.*` entries in `lang/ja.json`.

## Evidence & freshness

- status: confirmed-repo
- last_confirmed_at: 2026-04-11
- recheck_after: 180d
- recheck_trigger:
  - `app/Console/Commands/CompareTranslations.php` changes
  - `lang/ja/ledger.php` or any file under `lang/ja/ledger/` changes
  - `lang/ja.json` sync behavior changes

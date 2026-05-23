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
- If the UI field is backed by persisted data rather than a static enum, verify whether each value is actually a translation key before wiring labels or filters.

## Common Pitfalls

- **`ja.json` usage**: Adding or editing `ledger.*` keys in `lang/ja.json` directly is a regression. The `ja.json` file is a generated output; any manual changes will be overwritten by the next `translations:compare --force` run. Always use the PHP source files.
- **Key Duplication in Blade**: Translation keys that include placeholders (e.g. `:count項目の変更`) should not have the value manually prepended in the Blade template (e.g., `{{ $count }} {{ __('ledger.diff.items_changed', ['count' => $count]) }}`) to avoid duplication in the rendered output.
- **Mixed historical labels**: When a field mixes translation keys and raw human-readable strings in persisted rows, do not treat the field as a pure translation-key source. Inspect live data first and normalize the values before exposing them in filters or tables. See [2026-04-23_activity-history-display-retrospective.md](../../../docs/work/ui-ux/2026-04-23_activity-history-display-retrospective.md).

## Evidence & freshness

- status: confirmed-repo
- last_confirmed_at: 2026-04-23
- recheck_after: 180d
- recheck_trigger:
  - `app/Console/Commands/CompareTranslations.php` changes
  - `lang/ja/ledger.php` or any file under `lang/ja/ledger/` changes
  - `lang/ja.json` sync behavior changes
  - activity-history description / filter label sources change

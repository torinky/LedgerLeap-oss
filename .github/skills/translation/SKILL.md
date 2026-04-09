# Translation and Localization Management

This skill provides the standard workflow for managing UI text and technical labels via translation keys in LedgerLeap.

## Core Constraint

**NEVER use hardcoded natural language text (Japanese, English, etc.) in Blade templates or PHP logic.**
Always define a translation key and use the `__()` helper.

## One-Way Sync Architecture

The project uses a monolithic `lang/ja.json` for general UI keys, but domain-specific keys (like `ledger.*`) are managed via PHP files for better maintainability.

- **Seihon (Original Source)**: `lang/ja/ledger/*.php` (Modularized PHP arrays)
- **Target (Compiled)**: `lang/ja.json` (Single JSON file for the app)

The transformation is **one-way**: PHP -> JSON. Manual changes to `lang/ja.json` for keys starting with `ledger.` will be overwritten.

## Workflow: Adding / Modifying a Label

1. **Locate the appropriate category** in `lang/ja/ledger/`:
   - `ui.php`: General UI elements (buttons, labels, etc.)
   - `workflow.php`: Workflow-specific states and messages
   - `columns.php`: Default column names and hints
   - `folders.php`: Folder-related labels
   - `access.php`: Permissions and role titles

2. **Add the key** to the PHP array. Use snake_case.
   ```php
   'my_new_action' => '新規アクション',
   ```

3. **Sync to JSON**:
   Run the comparison/sync command via Sail or local PHP.
   ```bash
   # Via Sail (Recommended)
   ./vendor/bin/sail artisan translations:compare --force

   # Local Host
   php artisan translations:compare --force
   ```

4. **Use in Code**:
   - **Blade**: `{{ __('ledger.my_new_action') }}`
   - **PHP**: `__('ledger.my_new_action')`

## Troubleshooting

- **Key not showing?**: Ensure you ran `translations:compare`.
- **JSON getting messy?**: The sync command only manage keys starting with `ledger.`. External library translations remain intact.
- **PHP found on host?**: If `sail` is down, use `which php` to find a local binary and run the command.

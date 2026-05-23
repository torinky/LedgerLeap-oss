# Laravel 13 Sprint 1 完了レポート

**status:** complete  
**last_updated_at:** 2026-04-04  
**related_issue:** `#130`  
**related_memo:** `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`

## 概要

Sprint 1 では、Laravel 13 へ向けた Composer 依存の更新と framework bump を完了した。
`composer update --with-all-dependencies` が成功し、`laravel/framework` を `v13.3.0` へ引き上げた。

## 実施内容

### 1. Root 制約の更新
- `laravel/framework` を `^13` に更新
- `laravel/boost` を `^2.0` に更新
- `laravel/tinker` を `^3` に更新
- `pestphp/pest` を `^4.4` に更新
- `pestphp/pest-plugin-laravel` を `^4.1` に更新
- `phpunit/phpunit` を `^12` に更新
- `sebastian/diff` を `^7.0` に更新
- `darkaonline/l5-swagger` を `^11.0` に更新
- `systemsdk/phpcpd` を `^8.3.0` に更新
- `barryvdh/laravel-debugbar` を `^4.2.3` に更新

### 2. Path package の更新
- `packages/15web/filament-tree/composer.json` の `illuminate/contracts` 制約を `^11.0 || ^12.0 || ^13.0` に拡張

### 3. 旧パッチの整理
- `laravel/boost` 向けの旧 patch は Laravel 13 / Boost 2.x では不要となったため、`composer.json` の patch 設定から削除

### 4. 更新結果
- `laravel/framework`: `v12.56.0` → `v13.3.0`
- `laravel/boost`: `v1.8.13` → `v2.4.1`
- `laravel/tinker`: `v2.11.1` → `v3.0.0`
- `darkaonline/l5-swagger`: `9.0.1` → `11.0.0`
- `pestphp/pest`: `v3.8.6` → `v4.4.5`
- `pestphp/pest-plugin-laravel`: `v3.2.0` → `v4.1.0`
- `phpunit/phpunit`: `11.5.50` → `12.5.16`
- `brianium/paratest`: `v7.8.5` → `v7.20.0`
- `systemsdk/phpcpd`: `v8.0.0` → `v8.3.0`
- `barryvdh/laravel-debugbar`: `v3.16.5` → `v4.2.3`
- `15web/filament-tree`: path package の参照先を Laravel 13 対応に更新

### 5. 検証
- `./vendor/bin/sail composer update laravel/framework laravel/boost laravel/tinker darkaonline/l5-swagger pestphp/pest pestphp/pest-plugin-laravel phpunit/phpunit systemsdk/phpcpd 15web/filament-tree barryvdh/laravel-debugbar --with-all-dependencies`
  - 成功
- `./vendor/bin/sail test tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php`
  - `6 passed (47 assertions)`

### 6. 付随更新
- `artisan package:discover` / `ide-helper:generate` / `filament:upgrade` / `lang:update` / `translations:compare` を更新フロー内で実行
- `lang/ja.json` と `lang/ja/ledger.php` が更新された

## 結論

Sprint 1 は完了。
次の Sprint では、`#131` で CSRF / bootstrap / middleware 互換対応へ進む。

## 参考

- `composer.json`
- `composer.lock`
- `packages/15web/filament-tree/composer.json`
- `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`
- `tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php`
- `tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php`


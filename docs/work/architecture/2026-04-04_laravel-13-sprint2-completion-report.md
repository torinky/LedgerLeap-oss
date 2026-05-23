# Laravel 13 Sprint 2 完了レポート

**status:** complete  
**last_updated_at:** 2026-04-04  
**related_issue:** `#131`  
**related_memo:** `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`

## 概要

Sprint 2 では、Laravel 13 の CSRF / bootstrap / middleware 互換を整理し、Web / Filament / Sanctum の起動経路を `PreventRequestForgery` 前提へ寄せた。
`bootstrap/app.php` は既存構成のままで差分不要と確認した。

## 実施内容

### 1. CSRF middleware の Laravel 13 化
- `app/Http/Kernel.php` の `web` middleware を `PreventRequestForgery::class` に変更
- `app/Providers/Filament/AdminPanelProvider.php` の panel middleware を `PreventRequestForgery::class` に変更
- `app/Http/Middleware/VerifyCsrfToken.php` の基底クラスを `PreventRequestForgery` に変更
- `config/sanctum.php` の CSRF middleware を `PreventRequestForgery::class` に変更

### 2. 起動経路の確認
- `bootstrap/app.php` を確認し、Laravel 13 で追加修正は不要と判断
- `config/cache.php` と `config/session.php` を確認し、既存の前提で起動経路が維持されることを確認

### 3. 検証
- `./vendor/bin/sail pint --test app/Http/Kernel.php app/Http/Middleware/VerifyCsrfToken.php app/Providers/Filament/AdminPanelProvider.php config/sanctum.php`
  - PASS
- `./vendor/bin/sail test tests/Feature/Filament/DashboardTest.php tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php tests/Feature/Mcp/RemoteMcpHttpRouteTest.php tests/Feature/Api/BootstrapManifestApiTest.php`
  - `20 passed (53 assertions)`

## 変更結果

- `app/Http/Kernel.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- `config/sanctum.php`

## 結論

Sprint 2 は完了。
次の Sprint では、`#132` で回帰テスト・検証・リリース準備へ進む。

## 参照

- `app/Http/Kernel.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- `config/sanctum.php`
- `bootstrap/app.php`
- `config/cache.php`
- `config/session.php`


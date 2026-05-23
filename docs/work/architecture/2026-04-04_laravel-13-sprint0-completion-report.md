# Laravel 13 Sprint 0 完了レポート

**status:** complete  
**last_updated_at:** 2026-04-04  
**related_issue:** `#129`  
**related_memo:** `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`

## 概要

Sprint 0 では、Laravel 13 への移行を止めている依存関係と、CSRF 直接参照の起点を確認した。  
結果として、Composer 解決上の主ブロッカーは `darkaonline/l5-swagger` と `15web/filament-tree` に確定した。

## 実施した確認

### 1. Composer 依存の棚卸し
- `composer.json` の Laravel 系依存を確認
- `composer.lock` のロック値と制約を確認

### 2. ブロッカー判定
- `darkaonline/l5-swagger 9.0.1`
  - `laravel/framework: ^12.0 || ^11.0`
  - Laravel 13 へ直進できない
- `15web/filament-tree 1.0.3`
  - `illuminate/contracts: ^11.0 || ^12.0`
  - Laravel 13 へ直進できない

### 3. 非ブロッカー候補の確認
- `codewithdennis/filament-select-tree v4.0.18`
  - `illuminate/contracts: ^10.0|^11.0|^12.0|^13.0`
  - `filament/forms: ^4.0|^5.0`
  - 現時点では Laravel 13 の初期ブロッカーではない

### 4. CSRF 直接参照の確認
- `app/Http/Kernel.php`
  - `VerifyCsrfToken::class` を `web` ミドルウェアに直接指定
- `app/Providers/Filament/AdminPanelProvider.php`
  - `VerifyCsrfToken::class` を Filament panel ミドルウェアに直接指定
- `app/Http/Middleware/VerifyCsrfToken.php`
  - 独自 middleware として存在

## 現在のベースライン

- `laravel/framework`: `v12.56.0`
- `laravel/boost`: `v1.8.13`
- `laravel/tinker`: `v2.11.1`
- `pestphp/pest`: `v3.8.6`
- `phpunit/phpunit`: `11.5.50`
- `15web/filament-tree`: `1.0.3`
- `darkaonline/l5-swagger`: `9.0.1`

## 結論

Sprint 0 は完了。  
次の Sprint では、Composer 更新と framework bump を扱う `#130` に進む。

## 参照

- `composer.json`
- `composer.lock`
- `app/Http/Kernel.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Http/Middleware/VerifyCsrfToken.php`
- `docs/work/architecture/2026-04-04_laravel-13-issue-drafts.md`

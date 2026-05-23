# Laravel 13 アップグレード計画メモ

**status:** complete  
**last_updated_at:** 2026-04-04  
**recheck_after:** 2026-04-18  
**recheck_trigger:** Laravel 13 公式アップグレードガイド / `composer.lock` / 直接依存パッケージ / CSRF・middleware・テスト周辺を更新したとき

## 目的

LedgerLeap を Laravel 12 から Laravel 13 へ上げるための、調査結果に基づくスプリント計画・リスク・イシュー草案をまとめる。
このメモは実装前の共通認識を作るための作業ファイルであり、完了後は検証記録やリリース判断の土台にも使う。

## 調査結果サマリー

### 現在のベースライン

- `composer.lock` の `laravel/framework` は `v12.56.0`
- `composer.json` の `laravel/framework` は `^12`
- `laravel/boost` は `v1.8.13`
- `laravel/tinker` は `v2.11.1`
- `phpunit/phpunit` は `11.5.50`
- `pestphp/pest` は `3.8.6`
- `filament/filament` は `v5.4.3`
- `livewire/livewire` は `v4.2.3`
- `stancl/tenancy` は `v3.10.0`
- `codewithdennis/filament-select-tree` は `v4.0.18`
- `15web/filament-tree` は `1.0.3`
- `darkaonline/l5-swagger` は `9.0.1`

### 公式 Laravel 13 アップグレード要点

参照:
- https://laravel.com/docs/13.x/upgrade
- https://laravel.com/docs/13.x/upgrade#upgrading-to-13-0-from-12-x

主要ポイント:
- 依存関係更新: `laravel/framework ^13.0`, `laravel/boost ^2.0`, `laravel/tinker ^3.0`, `phpunit/phpunit ^12.0`, `pestphp/pest ^4.0`
- CSRF middleware 名称変更: `VerifyCsrfToken` / `ValidateCsrfToken` の直接参照は `PreventRequestForgery` へ寄せる
- cache / session / routing / queue には起動経路や動作に影響する差分がある
- `laravel/laravel` の 12.x → 13.x 差分も確認対象

### 直接ブロッカー

#### 1. `darkaonline/l5-swagger`

`composer.lock` の require が `laravel/framework: ^12.0 || ^11.0` で固定されている。  
Laravel 13 へ上げるには、更新・代替・fork のいずれかが必要。

#### 2. `15web/filament-tree`

`composer.lock` の require が `illuminate/contracts: ^11.0 || ^12.0` で、Laravel 13 への対応が足りない。  
このパッケージは path package なので、必要ならローカル修正や置換方針を先に決める。

#### 3. 依存更新の追従

公式ガイドの更新対象に対して、現行 lock が古い。
- `laravel/boost` 1.x → 2.x
- `laravel/tinker` 2.x → 3.x
- `phpunit/phpunit` 11.x → 12.x
- `pestphp/pest` 3.x → 4.x

### すでに 13 対応レンジが見えているもの

以下は少なくとも lock 上では 13 対応の余地があるため、初期ブロッカーとしては扱わない。
- `filament/filament` `v5.4.3`
- `livewire/livewire` `v4.2.3`
- `stancl/tenancy` `v3.10.0`
- `codewithdennis/filament-select-tree` `v4.0.18`

### 実コードで確認できた差分候補

- `app/Http/Kernel.php` に `App\Http\Middleware\VerifyCsrfToken` の直接参照あり
- `app/Providers/Filament/AdminPanelProvider.php` に `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` の直接参照あり
- `app/Http/Middleware/VerifyCsrfToken.php` が独自 middleware として存在

Laravel 13 で CSRF middleware 名称・推奨 API が変わるため、この周辺はコード修正の対象として扱う。

## スプリント計画

### Sprint 0: 互換性マトリクスと影響範囲の確定

**目的**
- 何が本当に止めているかを確定する
- upgrade の順番を決める
- issue 分割の前提を固定する

**主な作業**
- `composer.json` / `composer.lock` の Laravel 系依存を棚卸し
- `l5-swagger` と `15web/filament-tree` の対応方針を決める
- Laravel 13 upgrade guide の差分を要約する
- CSRF / cache / routing / queue / tests の影響有無を確認する
- `VerifyCsrfToken` の直接参照箇所を洗い出す

**完了条件**
- ブロッカー一覧が確定している
- 依存関係の更新順が決まっている
- issue 草案に必要なスコープが揃っている

**完了結果**
- `darkaonline/l5-swagger` は Laravel 13 直進のブロッカーとして確定
  - Evidence: `composer.lock:920-934`
- `15web/filament-tree` は Laravel 13 直進のブロッカーとして確定
  - Evidence: `composer.lock:17-23`
- `codewithdennis/filament-select-tree` は初期ブロッカーではないことを確認
  - Evidence: `composer.lock:575-597`
- `app/Http/Kernel.php` / `app/Providers/Filament/AdminPanelProvider.php` / `app/Http/Middleware/VerifyCsrfToken.php` の CSRF 直参照を確認
  - Evidence: `app/Http/Kernel.php:12,61`, `app/Providers/Filament/AdminPanelProvider.php:18,116`, `app/Http/Middleware/VerifyCsrfToken.php:5-7`

**Sprint 0 完了日**
- 2026-04-04

---

### Sprint 1: Composer / 依存関係の更新

**目的**
- Laravel 13 を解決できる状態にする

**主な作業**
- `laravel/framework` を `^13.0` に上げる
- `laravel/boost` を `^2.0` に上げる
- `laravel/tinker` を `^3.0` に上げる
- `phpunit/phpunit` を `^12.0` に上げる
- `pestphp/pest` を `^4.0` に上げる
- `darkaonline/l5-swagger` の更新・代替・fork のいずれかを実施する
- `15web/filament-tree` の Laravel 13 対応方針を反映する

**依存関係**
- Sprint 0 のブロッカー整理が前提

**完了条件**
- Composer が Laravel 13 の依存解決に通る
- 最低限のアプリ起動が壊れていない

**完了結果**
- `composer.json` の主要制約を Laravel 13 対応へ更新
  - Evidence: `composer.json:20-31`, `composer.json:56-68`
- `packages/15web/filament-tree/composer.json` を Laravel 13 対応へ更新
  - Evidence: `packages/15web/filament-tree/composer.json:19-24`
- `darkaonline/l5-swagger 11.0.0` / `laravel/boost 2.4.1` / `laravel/tinker 3.0.0` / `phpunit 12.5.16` / `pest 4.4.5` へ更新
  - Evidence: `composer.lock` の再解決結果
- `barryvdh/laravel-debugbar 4.2.3` / `systemsdk/phpcpd 8.3.0` に更新し、PHPUnit 12 と整合
  - Evidence: `composer.lock` の再解決結果
- `./vendor/bin/sail test tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php` が PASS
  - Evidence: `6 passed (47 assertions)`

**Sprint 1 完了日**
- 2026-04-04

---

### Sprint 2: アプリ互換・起動経路の追従

**目的**
- Laravel 13 の起動経路に影響する破壊的変更を吸収する

**主な作業**
- `VerifyCsrfToken` 参照を `PreventRequestForgery` 前提へ見直す
- `app/Http/Kernel.php` と `app/Providers/Filament/AdminPanelProvider.php` の middleware 設定を再確認する
- `bootstrap/app.php` を含む起動経路の差分を確認する
- cache / session / routing / queue の低〜中影響箇所を点検する
- `config/cache.php` などで framework デフォルト依存をしていないか確認する

**依存関係**
- Sprint 1 の composer 更新が前提

**完了条件**
- 主要リクエスト経路が通る
- CSRF / tenant / Filament / Livewire の基本導線が壊れていない

**完了結果**
- `app/Http/Kernel.php` の web middleware を `PreventRequestForgery` 前提へ更新
  - Evidence: `app/Http/Kernel.php:17-19`, `app/Http/Kernel.php:56-63`
- `app/Providers/Filament/AdminPanelProvider.php` の panel middleware を `PreventRequestForgery` 前提へ更新
  - Evidence: `app/Providers/Filament/AdminPanelProvider.php:18-20`, `app/Providers/Filament/AdminPanelProvider.php:111-121`
- `app/Http/Middleware/VerifyCsrfToken.php` の基底クラスを `PreventRequestForgery` に更新
  - Evidence: `app/Http/Middleware/VerifyCsrfToken.php:5-7`
- `config/sanctum.php` の CSRF middleware を Laravel 13 前提へ更新
  - Evidence: `config/sanctum.php:62-65`
- `bootstrap/app.php` は変更不要と確認
  - Evidence: `bootstrap/app.php:14-55`
- `./vendor/bin/sail test tests/Feature/Filament/DashboardTest.php tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php tests/Feature/Mcp/RemoteMcpHttpRouteTest.php tests/Feature/Api/BootstrapManifestApiTest.php` が PASS
  - Evidence: `20 passed (53 assertions)`

**Sprint 2 完了日**
- 2026-04-04

---

### Sprint 3: 回帰テスト・リリース準備

**目的**
- 変更の安全性を確認し、リリース可能な状態にする

**主な作業**
- tenant 初期化が必要な Feature test を重点実行する
- Filament / Livewire / permission / search / MCP の代表導線を確認する
- Mroonga / search / database migration 系の重点テストを実行する
- 必要なら UI 変更に合わせて `sail npm run build` を実施する
- ロールバック観点と残課題を整理する

**依存関係**
- Sprint 2 の互換対応が前提

**完了条件**
- 主要テストが PASS
- 未解決の互換問題が明文化されている
- リリース判断ができる

**完了結果**
- `./vendor/bin/sail test tests/Feature/Filament/DashboardTest.php tests/Feature/Filament/RoleResourceFolderPermissionRelationManagerTest.php tests/Feature/Livewire/TenantSwitcherTest.php tests/Feature/PermissionCacheConsistencyTest.php tests/Feature/Mcp/RemoteMcpHttpRouteTest.php tests/Feature/Api/BootstrapManifestApiTest.php tests/Feature/Search/SearchControllerAdditionalTest.php tests/Feature/Mcp/SearchLedgersToolKeywordSearchTest.php tests/Feature/Mcp/SearchLedgersToolSemanticSearchTest.php tests/Feature/Mcp/SearchLedgersToolSortingTest.php` が PASS
  - Evidence: `49 passed (130 assertions)`
- Filament / Livewire / permission / search / MCP の代表導線が Laravel 13 更新後も維持されていることを確認
- `sail npm run build` は今回は不要（Sprint 3 で frontend 変更なし）
- 残課題・未解決の互換問題は本回帰確認では新規発生なし

**Sprint 3 完了日**
- 2026-04-04

## リスクと対応

| リスク | 影響 | 対応 |
| --- | --- | --- |
| `l5-swagger` が 13 非対応 | Composer 解決不可 | 先に更新・代替・fork を決める |
| `15web/filament-tree` が 13 非対応 | tree UI / resource 周辺が壊れる | path package のためローカル修正含めて方針確定する |
| CSRF middleware 名称変更 | Filament / web middleware / テストが壊れる | `PreventRequestForgery` へ寄せる |
| Pest / PHPUnit の世代差分 | テスト実行失敗 | 依存更新とテスト実行を同じ Sprint に入れる |
| Laravel 13 のデフォルト変更 | cache / queue / route / session の微差分 | 低影響項目も回帰テストで拾う |

## このメモを起点に切る issue の候補

詳細は `2026-04-04_laravel-13-issue-drafts.md` を参照。

## 完了後メモ

- Laravel 13 アップグレードの Sprint 0〜3 は完了
- 追加の変更が入る場合は `recheck_trigger` に沿って再確認する
- 詳細な回帰結果は `docs/work/architecture/2026-04-04_laravel-13-sprint3-completion-report.md` を参照する



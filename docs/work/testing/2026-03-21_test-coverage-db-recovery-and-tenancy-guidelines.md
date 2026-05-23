# `test:coverage` 失敗の調査記録と再発防止ガイド

**記録日:** 2026-03-21
**状態:** 調査完了・再発防止策を記録
**対象:** `./vendor/bin/sail composer test:coverage`

## 1. 何が起きたか

`test:coverage` の前処理として動く `bin/prepare-local-test-env.sh` で、テスト DB の再構築後に `php artisan migrate --env=testing` が `Table already exists` で失敗した。

### 代表的な失敗例
- `users` テーブルが既に存在する
- `permissions` テーブルが既に存在する
- `domains` テーブルが既に存在する
- `ledgers` テーブルが既に存在する
- 一方で、`migrations` テーブルが見つからない / 接続がずれているように見えるケースもあった

## 2. 事実として確認できたこと

### 2.1 `bin/reset-test-db.sh` は正常に再構築できた

`bin/reset-test-db.sh` を単体で実行すると、以下が確認できた。
- `ledgerleap_test` と worker DB 群を削除→再作成できる
- `migrations` テーブルを作成したうえで `migrate` が完走する
- central DB のテーブル数は 0 から開始できる

### 2.2 `bin/prepare-local-test-env.sh` は「空にしたつもりでも空でない」状態を見逃していた

`prepare` スクリプトは DB の drop / create を行っていたが、
`mysql_testing` 接続と Laravel 側の接続状態を揃え切れず、`migrate` の実行時に既存テーブルが残っている状態が発生した。

### 2.3 tenancy のテスト資料と一致する運用が必要

`stancl/tenancy` の公式寄りドキュメントでは、テナントテストでは `setUp()` で tenant を作成・初期化すること、tenant migration は `tenants:migrate` で実行することが示されている。

Laravel 側の testing docs でも、
- DB をきれいにしたいなら `RefreshDatabase` / `DatabaseMigrations` / `db:wipe` の役割を分けること
- 直列と並列で DB の扱いが違うこと
- parallel testing は process token ごとの DB を持つこと
が明示されている。

## 3. 反映した修正

### 3.1 `bin/prepare-local-test-env.sh`

- central / worker DB を MySQL root で再作成
- `php artisan db:wipe --database=mysql_testing --force`
- `php artisan migrate --database=mysql_testing --force`
- shared tenant を作成後に `tenants:migrate`
- `migrate:status` も `mysql_testing` を明示

### 3.2 `database/migrations/2024_06_30_115527_create_permission_tables.php`

- permission tables を冪等化
- 既存テーブルがある場合は再作成しない
- `down()` は `dropIfExists()` に変更

## 4. 再発防止ルール

### 4.1 テスト DB の再構築は「root で drop/create」だけに頼らない

root 側で DB を再作成しても、Laravel 側の接続状態や migration 履歴がずれると、`migrate` 実行時に古い状態を拾うことがある。

**ルール**
- DB の物理削除/作成
- Laravel の `db:wipe`
- `mysql_testing` の明示
- その後に `migrate`

をセットで扱う。

### 4.2 tenancy のテストは `setUp()` で tenant 初期化を前提にする

`tenancy()` が null のまま実行されるテストは、ルート生成や tenant scoped query で壊れやすい。

**ルール**
- Feature test の `setUp()` では `tenancy()->initialize($tenant)` を明示
- tenant migration は `tenants:migrate`
- tenant DB の初期化順序を `central -> tenant -> test data` に統一

### 4.3 `RefreshDatabase` と tenant-aware trait の役割を混ぜない

**指針**
- central DB の履歴管理: `mysql_testing`
- tenant DB の初期化: `tenants:migrate`
- プロセス単位で DB を持つ parallel test は worker DB 名を明示

### 4.4 既存 migration は「再実行されても壊れない」形を優先する

特に package 由来の migration や shared DB を触る migration は、再実行時に `Schema::hasTable()` / `dropIfExists()` を使って安全側に寄せる。

## 5. 参照した公式寄り資料

- Stancl Tenancy docs
  - Testing: https://github.com/stancl/tenancy-docs/blob/master/source/docs/v3/testing.blade.md
  - Migrations: https://github.com/stancl/tenancy-docs/blob/master/source/docs/v3/migrations.blade.md
  - Console commands / tenant-aware migrate: https://github.com/stancl/tenancy-docs/blob/master/source/docs/v3/console-commands.blade.md
  - 要点: `setUp()` で tenant を作成・初期化し、tenant migrations は `database/migrations/tenant` で管理して `tenants:migrate` を使う
- Laravel docs
  - Database Testing: https://laravel.com/docs/12.x/database-testing
  - Migrations: https://laravel.com/docs/12.x/migrations
  - Testing / Parallel: https://laravel.com/docs/12.x/testing#running-tests-in-parallel
  - 要点: `RefreshDatabase` はトランザクション中心、DB の完全再構築は `migrate:fresh` / `db:wipe` / `migrate` を役割分担して扱う

## 6. 次回の確認コマンド

```bash
./vendor/bin/sail composer test:coverage
./vendor/bin/sail test
bash -lc 'cd /Users/kazutaka/PhpstormProjects/LedgerLeap && bash ./bin/reset-test-db.sh'
```

## 7. フレッシュネスメタデータ

- `status`: confirmed
- `last_confirmed_at`: 2026-03-21
- `recheck_after`: 2026-06-21
- `recheck_trigger`: `stancl/tenancy` / Laravel の testing or migrations docs が更新されたとき、または `test:coverage` の DB 初期化で再度 `Table already exists` が出たとき



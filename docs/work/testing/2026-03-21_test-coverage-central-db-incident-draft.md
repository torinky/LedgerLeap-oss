# `sail composer test:coverage` で central DB を触ってしまう件の調査ドラフト

**作成日:** 2026-03-21 (更新: 2026-03-23)  
**状態:** closed (Issue #117 にて解決済み)  
**対象:** `./vendor/bin/sail composer test:coverage`

## 0. 目的

このメモは、`test:coverage` 実行時に central DB 側へマイグレーションが走ってしまった件について、
- 現状の事実
- 想定される原因
- 当面の回避策
- 恒久対応方針

を、GitHub issue 化する前の下書きとして整理するものです。

> 既存の復旧・再発防止メモ: [`docs/work/testing/2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md`](./2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md)

## 1. 現象

`./vendor/bin/sail composer test:coverage` を実行したところ、
テストDBではなく central DB 側のマイグレーションが走り、
本番相当の DB を消してしまう挙動が確認された。

特に問題なのは、`test:coverage` が「coverage の確認」ではなく、
**DB 初期化を伴うテスト実行の入口**になっている点である。

## 2. 確認できた事実

### 2.1 `Coverage: Full` の Run Configuration は Composer Script を実行している

PhpStorm の `Coverage: Full` は `.idea/runConfigurations/Coverage__Full.xml` で `composer.json` の `test:coverage` を呼んでいる。

### 2.2 `composer test:coverage` は前処理を含む

`composer.json` の `test:coverage` は `test:prepare:local` を先頭で実行する。

### 2.3 `test:prepare:local` は `bin/prepare-local-test-env.sh` を呼ぶ

このスクリプトは以下を実行する。

- central / worker DB の再作成
- `php artisan db:wipe --database=mysql_testing --force`
- `php artisan migrate --database=mysql_testing --force`
- shared tenant の作成と `tenants:migrate`

### 2.4 `.env` と `.env.testing` の役割が分かれている

- `.env` は central DB を指す（`DB_CONNECTION=mysql`、`DB_DATABASE=ledgerleap`）
- `.env.testing` はテストDB を指す（`DB_CONNECTION=mysql_testing`、`DB_DATABASE=ledgerleap_test`）

### 2.5 `phpunit.xml` は testing 環境を定義しているが、shell script には直接効かない

Laravel のテスト実行では `phpunit.xml` により testing 環境が使われるが、
`bin/prepare-local-test-env.sh` のような **独立した Artisan 呼び出し** は、
そのままだと `.env` 側の設定を拾う可能性がある。

### 2.6 `tests/TestCase.php` の Sail 防御はテスト本体向け

`tests/TestCase.php` は Sail / Docker interpreter 外の実行を止めるが、
`bin/prepare-local-test-env.sh` のようなテスト前処理までは保護しない。

## 3. 仮説

### 仮説A: `prepare-local-test-env.sh` の Artisan 呼び出しが testing 環境に固定されていない

もっとも疑わしいのはこれ。

`php artisan migrate --database=mysql_testing --force` のように接続名は指定していても、
`APP_ENV=testing` を明示していないため、Laravel が `.env` を読み、
`mysql_testing` 接続の定義解決が central DB 側に寄った可能性がある。

### 仮説B: configuration cache / 実行コンテキストの不一致

`config:clear` は実行しているが、
- 実際にどの `.env` が読まれたか
- PhpStorm の実行時と Sail の実行時でどの環境変数が渡されたか

によって、central DB 側へ向く可能性がある。

### 仮説C: coverage 入口が「テスト実行」ではなく「DB 初期化付きのローカル再構築」になっている

`test:coverage` が単なる coverage 実行ではなく、
DB 再構築を伴う `test:prepare:local` を含んでいるため、
誤接続が起きたときの破壊範囲が大きい。

## 4. 当面の回避策

### 4.1 `sail composer test:coverage` を無条件で実行しない

原因が完全に潰れるまで、フル coverage の入口としては使わない。

### 4.2 coverage が必要なら、分割された script を個別に使う

現在の安全寄りの実行候補は以下。

```bash
./vendor/bin/sail composer test:coverage:unit
./vendor/bin/sail composer test:coverage:feature
./vendor/bin/sail composer test:coverage:db-migrations
```

### 4.3 DB を触る前に test DB を再構築する

必要に応じて先に以下を使う。

```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && ./bin/reset-test-db.sh"
```

## 5. 実施した恒久対応 (Issue #117)

Issue #117 にて以下の恒久対応を実装し、問題を解決した。

### 対応1: `prepare-local-test-env.sh` 内の testing 環境固定とガード追加
スクリプト冒頭で、以下の通りテスト環境変数を強制適用し、万が一 central DB (`ledgerleap`) を向いていた場合は即座に停止する**フェイルセーフガード**を導入した。
```bash
export APP_ENV=testing
export DB_CONNECTION=mysql_testing

# Laravel が testing 環境設定を読んでいるか確認する Fail-Safe
PHP_DB_NAME=$(php artisan tinker --env=testing --execute='echo config("database.connections.mysql_testing.database", "");' 2>/dev/null || echo "")
if [[ "$PHP_DB_NAME" == "ledgerleap" || "$PHP_DB_NAME" == "ledgerleap_prod" ]]; then
    echo "ERROR: Laravel の testing 環境設定が central DB ($PHP_DB_NAME) を指しています。処理を中止します。"
    exit 1
fi
```
また、以降のすべての `php artisan` コマンドに `--env=testing` を明示的に付与した。

#### テスト用テナント初期化処理に関する安全性担保
以前は `Tenant::firstOrCreate` などを行う `tinker` 実行に `--env=testing` がなく、想定外に central DB へテスト用テナントを作成、または `TestCase.php` の `tearDown()` 経由で central DB 内の正当なテナントを全て削除・初期化（wipe）してしまう潜在的リスクが高かったが、上記のガードと環境変数強制により、その可能性は完全に遮断された。

### 対応2: coverage と DB 初期化の完全分離
`composer.json` のスクリプト定義を見直し、`test:coverage` から `@test:prepare:local` を削除した。
これにより、`sail composer test:coverage` を無条件で叩いてしまっても DB 初期化は走らなくなり、ローカルの `central DB` が破壊されるリスクを排除した。

## 6. 確認済事項 (本事象の解決証明)

- `sail composer test:prepare:local` 実行時、central DB（`Tenant` モデルのデータなど）が維持されていることを `tinker` で確認済み。
- 意図的に central DB を叩くような環境設定をした場合、スクリプトが Fail-Safe により即時 `exit 1` となり中断されることを確認済み。
- `TestCases.php` 側の `abortIfTestsShouldNotRunInCurrentRuntime()` 防御壁と合わせ、前処理・テスト実行の両面からの保護が完成している。

## 7. 関連ファイル

- `composer.json`
- `bin/prepare-local-test-env.sh`
- `bin/reset-test-db.sh`
- `.env` / `.env.testing`
- `phpunit.xml`
- `config/database.php`
- `config/tenancy.php`
- `tests/TestCase.php`
- `.idea/runConfigurations/Coverage__Full.xml`
- `docs/work/testing/2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md`

## 8. 今後の推奨運用

- ローカルでテスト用 DB をリセット・再構築する場合は、明示的に `./vendor/bin/sail composer test:prepare:local` または `./bin/reset-test-db.sh` を使用する。
- カバレッジの取得自体は PhpStorm や `sail composer test:coverage` 経由で安全に行える。


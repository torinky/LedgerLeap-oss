# テスト DB 分離: Dotenv 解決順序と .env.testing の必要性

**作成日:** 2026-05-31
**最終更新:** 2026-05-31
**ステータス:** 確定済み（実装反映済み）
**関連 Issue:** なし（CI テスト障害調査）
**関連ドキュメント:** `.env.testing`, `phpunit.xml`, `phpunit.serial.xml`, `phpunit.parallel.xml`, `tests/Traits/RefreshDatabaseWithTenant.php`

---

## 1. 発見された問題

`Pest: Main (phpunit.serial.xml)` を PhpStorm + Laravel Sail で実行した際、`migrate:fresh` がテスト DB (`ledgerleap_test`) ではなく本番 DB (`ledgerleap`) に対して実行されていた。結果として本番 DB のテーブルが破壊される深刻な障害が発生した。

## 2. 根本原因: Dotenv の環境変数解決チェーン

Laravel の `env()` ヘルパーは内部的に Dotenv `Repository` を使用する。このリポジトリのアダプター解決順序は以下の通り:

```
PutenvAdapter (getenv())
        ↓
EnvConstAdapter ($_ENV)
        ↓
ServerConstAdapter ($_SERVER)
```

**最初に見つかったアダプターの値が採用される。** つまり `getenv()` > `$_ENV` > `$_SERVER`。

### 2.1 phpunit.xml の `<server>` が効かない理由

phpunit.xml の `<server name="DB_DATABASE" value="ledgerleap_test"/>` は `$_SERVER`（最下位）にしか書き込まない。

テスト実行時の流れ:
1. Docker コンテナ起動 — DB 変数は未設定（`getenv()` = false）
2. PHPUnit が phpunit.xml を処理 → `$_SERVER['DB_DATABASE'] = 'ledgerleap_test'`
3. Laravel 起動 → `LoadEnvironmentVariables` → `.env` を Dotenv で読み込み
4. `.env` の `DB_DATABASE=ledgerleap` が `putenv('DB_DATABASE=ledgerleap')` を実行
5. `env('DB_DATABASE')` → `getenv()` が `ledgerleap` を返す → **本番 DB が選択される**

`$_SERVER` の値は一度も参照されない。Dotenv は immutable リポジトリを使用するが、phpunit.xml が `$_ENV` ではなく `$_SERVER` に書き込むため、`.env` の読み込み時点では「未設定」と判定され、`.env` の値が書き込まれてしまう。

### 2.2 phpunit.xml の `<env>` も完全ではない理由

`<env force="true">` は `$_ENV`（中位）に書き込むが `putenv()`（最上位）は触らない。

- `.env` が `putenv('DB_DATABASE=ledgerleap')` を実行済みの場合 → `getenv()` が勝つ
- `.env` 読み込み前に `$_ENV` が設定されていれば Dotenv の immutable チェックが `.env` の上書きをブロックするが、`putenv()` には影響しない

結論: **phpunit.xml だけでは `.env` の値を完全に上書きできない。**

## 3. 正しい解決策: `.env.testing`

Laravel 12.x 公式ドキュメントより:

> you may create a `.env.testing` file in the root of your project. This file will be used **instead of** the `.env` file when running Pest and PHPUnit tests.

`.env.testing` が存在する場合、Dotenv は `.env` の代わりに `.env.testing` を読み込む。このファイルから全アダプター（putenv, $_ENV, $_SERVER）にテスト用の値が設定される。

## 4. 適用した3層防御

| 層 | ファイル | 役割 |
|---|---|---|
| **1. 根本対策** | `.env.testing` | `.env` の代わりに読み込まれ、全アダプターにテスト用の値を設定 |
| **2. 設定防御** | `phpunit.xml` (3ファイル) | `<server>` → `<env force="true">` に変更。`$_ENV` レベルでのバックアップ |
| **3. ランタイム防御** | `RefreshDatabaseWithTenant` | `--database=mysql_testing` 明示指定 + `config()->set()` による最終防衛線 |

### 4.1 `.env.testing` の設計方針

- `DB_CONNECTION=mysql_testing`（テスト専用接続）
- `DB_DATABASE=ledgerleap_test`（テスト専用 DB）
- `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`（テスト最適化）
- `RAG_ENABLED=false`（外部サービス不使用）
- その他の値（`DB_HOST`, `REDIS_HOST` 等）は `.env` と共通

### 4.2 RefreshDatabaseWithTenant の防御線

```php
// reapplyWorkerDatabaseConnection() 内
config()->set('database.default', 'mysql_testing');
config()->set('tenancy.database.central_connection', 'mysql_testing');

// refreshDatabase() 内
$this->artisan('migrate:fresh', [
    '--database' => 'mysql_testing',  // 明示的な防御
    ...
]);
```

## 5. 再発防止チェックリスト

- [ ] `.env.testing` が存在し、`DB_CONNECTION=mysql_testing` を含むこと
- [ ] 新しい phpunit.xml を作成する際は `<env force="true">` を使用すること（`<server>` 禁止）
- [ ] `migrate:fresh` や `tenants:migrate` 等の破壊的 Artisan コマンドは常に `--database` を明示指定すること
- [ ] テスト追加時に `.env.testing` に新しい必須変数がないか確認すること

## 6. 参考リンク

- [Laravel 12.x Testing: .env.testing](https://laravel.com/docs/12.x/testing#the-env-testing-environment-file)
- [PHPUnit 12.x Configuration: `<env>` element](https://docs.phpunit.de/en/12.0/configuration.html#the-env-element)
- [Dotenv Repository: Adapter chain](https://github.com/vlucas/phpdotenv)
- 実装コミット: `892a16d3`, `cedfa127`

## 7. 更新履歴

| 日付 | 変更内容 |
|------|---------|
| 2026-05-31 | 初版作成。テスト DB 分離の根本原因と3層防御を文書化 |

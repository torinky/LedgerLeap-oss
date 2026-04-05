# テスト実行ガイド（並列導入後の標準手順）

- 関連Issue: https://github.com/torinky/LedgerLeap/issues/81
- 最終更新: 2026-04-05（Sprint 7 / parallel-first CI）

---

## ローカル開発での標準コマンド

### 日常的な開発サイクル（推奨）

```bash
# 1. コード整形
./vendor/bin/sail pint

# 2. 標準 CI をローカルで再現（parallel-first）
./vendor/bin/sail composer test:ci

# 3. parallel 側だけを個別確認
./vendor/bin/sail composer test:ci:parallel

# 4. serial remainder だけを個別確認
./vendor/bin/sail composer test:ci:feature:serial

# 5. 旧来の直列実行を丸ごと検証したいときだけ使う
./vendor/bin/sail composer test:ci:serial

# 6. フル検証（準備 + 標準 CI + external）
./vendor/bin/sail composer test:full
```

**備考**:
- `composer test:ci` は `parallel → serial remainder → db-migrations` の順で実行する
- `composer test:ci:serial` は旧来のフル直列実行を残した検証用
- `--recreate-databases` は使わない
- parallel 標準は `FeatureParallelSubset`（Livewire / Services）
- serial remainder は `FeatureSerial`（parallel-safe subset を除外した残余）
- test DB が壊れた場合は `bin/reset-test-db.sh` でリセットしてから再実行する

### PhpStorm の推奨 Run Configuration

- `CI: Standard` → `composer test:ci`
- `Parallel: Unit (CI)` → `composer test:ci:unit`
- `Parallel: Feature Subset` → `composer test:ci:feature`
- `Feature: Serial Remainder` → `composer test:ci:feature:serial`
- `Serial: DB Migrations` → `composer test:ci:db-migrations`
- `Pest: Serial (phpunit.serial.xml)` → 旧来のフル直列 `phpunit.serial.xml`
- `Pest: Parallel (phpunit.parallel.xml)` → `phpunit.parallel.xml` を使う並列 Pest 実行

### 全テスト実行（CI と同等）

```bash
# 標準 CI（parallel-first）
./vendor/bin/sail composer test:ci

# parallel 側（Unit + Feature subset）
./vendor/bin/sail composer test:ci:parallel

# serial remainder（FeatureSerial）
./vendor/bin/sail composer test:ci:feature:serial

# 旧来のフル直列（検証用）
./vendor/bin/sail composer test:ci:serial

# DatabaseMigrations 系（直列・Mroonga 含む）
./vendor/bin/sail test --group=database-migrations

# external（外部コンテナが起動している場合のみ）
./vendor/bin/sail test --group=external
```

### 特定テストのデバッグ

```bash
# フィルタ指定
./vendor/bin/sail test --filter=テストクラス名orメソッド名

# 並列 + フィルタ（最小再現確認に使用）
./vendor/bin/sail pest --parallel --processes=2 \
  --filter="LedgerDiffViewerTest" \
  --testsuite=FeatureParallelSubset \
  --display-errors
```

---

## グループ分類ルール

| グループ | 対象 | 実行方法 |
|---|---|---|
| `parallel-safe`（無指定） | standard Unit / `FeatureParallelSubset` | `--parallel` 可 |
| `serial-remainder` | `FeatureSerial` | serial のみ |
| `database-migrations` | Mroonga / `DatabaseMigrationsOnce` / `DatabaseMigrations` 使用テスト | 直列のみ・専用ジョブ |
| `external` | VLM / LDAP / Embedding 等の外部コンテナ依存 | 手動実行のみ |

### 新規テストの分類判断

```
テストを書いた → DB が必要？
  ├─ No  → 分類なし（parallel-safe）
  └─ Yes → Mroonga MATCH() AGAINST() を使う？
              ├─ Yes → #[Group('database-migrations')] + DatabaseMigrationsOnce を使う
              └─ No  → 外部コンテナ（VLM/LDAP 等）が必要？
                          ├─ Yes → #[Group('external')] を付与
                          └─ No  → parallel-safe か serial-remainder かを判定する
```

---

## CI ジョブ構成（Sprint 7 時点）

### 標準 CI（`phpunit.yml`）— parallel-first

| ジョブ | 対象 | タイムアウト |
|---|---|---|
| `unit` | `--parallel --testsuite=Unit --exclude-group=external --exclude-group=database-migrations` | 30分 |
| `feature-parallel` | `--parallel --testsuite=FeatureParallelSubset --exclude-group=external --exclude-group=database-migrations` | 30分 |
| `feature` | `--testsuite=FeatureSerial --exclude-group=external --exclude-group=database-migrations` | 40分 |
| `db-migrations` | `--group=database-migrations` | 30分 |

### parallel canary（`parallel-canary.yml`）— 手動観測

| ジョブ | 対象 | 備考 |
|---|---|---|
| `canary-unit-parallel` | Unit を `--parallel` で実行 | `workflow_dispatch` のみ |
| `canary-feature-parallel` | `FeatureParallelSubset` を `--parallel` で実行 | `workflow_dispatch` のみ |

**現在のローカル検証結果（2026-03-07）**:
- `./vendor/bin/sail pest --parallel --processes=2 --filter="LedgerDiffViewerTest" --testsuite=FeatureParallelSubset --display-errors`
  - `10 passed (44 assertions)` / `18.30s` / `Parallel: 2 processes`
- `./vendor/bin/sail pest --parallel --testsuite=FeatureParallelSubset --exclude-group=external --exclude-group=database-migrations`
  - `2 skipped, 461 passed (1105 assertions)` / `498.76s` / `Parallel: 8 processes`

**parallel 昇格基準**: 10 連続成功 & フレーク率 < 1% → Feature 全件の更なる並列化を検討

### external CI（`external-tests.yml`）— 手動実行のみ

- 外部コンテナ環境（self-hosted runner）整備後に週次スケジュールを有効化予定

---

## トレイト使用ガイド

### `RefreshDatabaseWithTenant`（標準 Feature テスト用）

```php
class MyFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant(); // ← 必須
        // ... テスト固有のセットアップ
    }
}
```

**ポイント**:
- プロセスキー（`ParallelTesting::token() ?: 'global'`）単位で状態管理
- 並列実行時は `mysql_testing` を worker DB へ毎テスト再選択する
- CI 環境では `migrate:fresh` / `tenants:migrate` をスキップ（ワークフローで実施済み）
- `TestDatabaseState::reset()` を呼べば全静的状態をリセット可能
- shared tenant 取得後に別 `Tenant::create()` で上書きしない

### `DatabaseMigrationsOnce`（Mroonga 全文検索テスト用）

```php
#[Group('database-migrations')]  // ← 必須
class MyMroongaTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabaseMigrationsOnce(); // ← 必須
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce(); // ← 必須（TRUNCATE でMroongaインデックスをクリア）
        parent::tearDown();
    }
}
```

**ポイント**:
- Mroonga インデックスはトランザクションロールバックで消えないため TRUNCATE を使用
- CI 環境では `migrate:fresh` をスキップし `ci-test-tenant` を再利用

---

## ロールバック手順（簡易版）

詳細: `docs/work/architecture/testing/2026-03-06_Parallel_Testing_Rollback_Runbook.md`

| 状況 | 対応 |
|---|---|
| parallel canary だけ失敗 | `parallel-canary.yml` を手動運用のまま維持し、必要なら一時停止 |
| 標準 CI が不安定化 | `feature-parallel` を `workflow_dispatch` のみに切り替えるか、`feature` を旧 serial へ戻す |
| テナント初期化漏れ | `setUp()` に `tenancy()->initialize(static::getSharedTenantForCurrentProcess())` を追加 |
| CI 全体ロールバック | `phpunit.yml` を `6df40d8f` に revert |

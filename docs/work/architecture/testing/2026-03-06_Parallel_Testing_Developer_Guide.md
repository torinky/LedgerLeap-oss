# テスト実行ガイド（並列導入後の標準手順）

- 関連Issue: https://github.com/torinky/LedgerLeap/issues/81
- 最終更新: 2026-03-06（Sprint 5）

---

## ローカル開発での標準コマンド

### 日常的な開発サイクル（推奨）

```bash
# 1. コード整形
./vendor/bin/sail pint

# 2. 並列テスト（高速・parallel-safe 対象）
./vendor/bin/sail artisan test --parallel --recreate-databases \
  --exclude-group=external \
  --exclude-group=database-migrations

# 3. 直列テスト（Mroonga / DatabaseMigrations 系）
./vendor/bin/sail test --group=database-migrations
```

### 全テスト実行（CI と同等）

```bash
# Unit のみ
./vendor/bin/sail test --testsuite=Unit \
  --exclude-group=external \
  --exclude-group=database-migrations

# Feature のみ
./vendor/bin/sail test --testsuite=Feature \
  --exclude-group=external \
  --exclude-group=database-migrations

# DatabaseMigrations 系（直列・Mroonga 含む）
./vendor/bin/sail test --group=database-migrations

# external（外部コンテナが起動している場合のみ）
./vendor/bin/sail test --group=external
```

### 特定テストのデバッグ

```bash
# フィルタ指定
./vendor/bin/sail test --filter=テストクラス名orメソッド名

# 並列 + フィルタ
./vendor/bin/sail artisan test --parallel --filter=テストクラス名
```

---

## グループ分類ルール

| グループ | 対象 | 実行方法 |
|---|---|---|
| `parallel-safe`（無指定） | 標準 Unit / Feature | `--parallel` 可 |
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
                          └─ No  → RefreshDatabaseWithTenant を使う（parallel-safe）
```

---

## CI ジョブ構成（Sprint 5 時点）

### 標準 CI（`phpunit.yml`）— 全 Push / PR で実行

| ジョブ | 対象 | タイムアウト |
|---|---|---|
| `unit` | `--testsuite=Unit --exclude-group=external --exclude-group=database-migrations` | 30分 |
| `feature` | `--testsuite=Feature --exclude-group=external --exclude-group=database-migrations` | 40分 |
| `db-migrations` | `--group=database-migrations` | 30分 |

### カナリア CI（`parallel-canary.yml`）— 並走検証中

| ジョブ | 対象 | 備考 |
|---|---|---|
| `canary-unit-parallel` | Unit を `--parallel` で実行 | `continue-on-error: true` |
| `canary-feature-parallel` | Feature/Livewire + Feature/Services を `--parallel` で実行 | `continue-on-error: true` |

**カナリア昇格基準**: 10 連続成功 & フレーク率 < 1% → Feature 全件並列化 → 標準 CI に統合

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
- CI 環境では `migrate:fresh` / `tenants:migrate` をスキップ（ワークフローで実施済み）
- `TestDatabaseState::reset()` を呼べば全静的状態をリセット可能

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
| カナリアのみ失敗 | `parallel-canary.yml` の `on:` を `workflow_dispatch` のみに変更 |
| 標準 CI が不安定化 | composite action を展開形式に戻す（Runbook Level 2 参照） |
| テナント初期化漏れ | `setUp()` に `tenancy()->initialize(static::getSharedTenantForCurrentProcess())` を追加 |
| CI 全体ロールバック | `parallel-canary.yml` を削除、`phpunit.yml` を `6df40d8f` に revert |


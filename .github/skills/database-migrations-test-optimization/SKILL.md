---
name: database-migrations-test-optimization
description: >
  DatabaseMigrations トレイトを使うテストの高速化・CI分離パターン。
  全文検索テストや複数テナントをまたぐテストを新規作成・変更する際に参照すること。
---

# DatabaseMigrations テストの最適化パターン

## このスキルを使うタイミング

以下のいずれかに該当するとき、**このスキルを参照すること**:

- `DatabaseMigrations` トレイトを使うテストを新規作成・変更するとき
- Mroonga 全文検索（`Ledger::search()` / `MATCH() AGAINST()`）を使うテストを書くとき
- 複数テナントを跨いだ境界検証テスト（`$tenantA->run()`, `$tenantB->run()`）を書くとき
- CI でテストが300秒超かかる、またはタイムアウトが発生するとき
- テスト後に他のテストが「テーブルが存在しない」「テナントDBに接続できない」と失敗するとき

---

## 1. トレイト選択の基準

```
テストを書く
  ↓
Mroonga 全文検索（MATCH AGAINST）を使う？
  → YES → DatabaseMigrationsOnce を使う（後述 §3）
  → NO  → 続く
  ↓
複数テナントを跨ぐ境界検証が必要？（$tenantA->run() / $tenantB->run()）
  → YES → DatabaseMigrations を使う + #[Group('database-migrations')]
  → NO  → 続く
  ↓
テナントコンテキストが必要？
  → YES → RefreshDatabaseWithTenant を使う（テストケースは親TestCaseを継承するだけでOK）
  → NO  → RefreshDatabase を使う（シンプルなユニットテスト）
```

---

## 2. トレイト比較表

| トレイト | migrate:fresh | テナント | 速度 | 用途 |
|---|---|---|---|---|
| `RefreshDatabase` | トランザクションで代替 | ❌ | ⚡ 速い | シンプルなCRUDテスト |
| `RefreshDatabaseWithTenant` | クラスで1回（CI）/ テストで1回（ローカル） | ✅ | ⚡ 速い | 通常の機能テスト |
| `DatabaseMigrationsOnce` | クラスで1回 + TRUNCATE | ✅ | 🟡 中程度 | **Mroonga全文検索テスト** |
| `DatabaseMigrations` | テストメソッドごと | ✅ | 🔴 遅い | 複数テナント境界検証のみ |

### なぜ `DatabaseMigrations` が遅いのか

- `migrate:fresh` に約13秒かかる（Mroongaのインデックス再構築を含む）
- テストメソッドが10個あれば 13秒 × 10 = **130秒**
- `migrate:rollback` が他テストのDB状態を破壊する（CI での連鎖失敗の原因）

---

## 3. DatabaseMigrationsOnce トレイト

`tests/Traits/DatabaseMigrationsOnce.php` に実装済み。

### 設計思想

- `migrate:fresh` を**クラスで1回だけ**実行する（初回テストのみ13秒）
- 各テスト後は `TRUNCATE` でテーブルをクリーンアップ
- トランザクションではなく TRUNCATE を使う理由：
  **Mroonga の全文検索インデックスはトランザクション外で更新されるため、
  ロールバックしてもインデックスに残留データが残り次テストに影響する**

### 使い方

```php
use Tests\Traits\DatabaseMigrationsOnce;

#[Group('database-migrations')]  // CIの専用ジョブで実行
class MySearchTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Embeddingコンテナ不要にする（§外部依存スキル参照）
        $this->setUpDatabaseMigrationsOnce();

        // テナントは DatabaseMigrationsOnce が共有テナントを作成・初期化済み
        $this->tenant = static::$sharedTenantForMigrationsOnce;
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce(); // TRUNCATEでクリーンアップ
        parent::tearDown();
    }
}
```

### TRUNCATE対象テーブルのカスタマイズ

デフォルトの対象テーブル（`getTablesToTruncateForMigrationsOnce()` でオーバーライド可能）：

```php
protected function getTablesToTruncateForMigrationsOnce(): array
{
    return [
        'ledgers',
        'ledger_chunks',
        'attached_files',
        'activity_log',
        'taggables',
        'tags',
    ];
}
```

テストで作成するテーブルが増えた場合はオーバーライドして追加すること。

---

## 4. DatabaseMigrations（複数テナント境界検証）

複数テナントを跨ぐ検証（`$tenantA->run()`, `$tenantB->run()`）が必要な場合のみ使用する。
この場合は CI の他テストへの影響を防ぐため `#[Group('database-migrations')]` を必ず付与すること。

```php
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Group;

/**
 * DatabaseMigrations を使うため、CI では専用の db-migrations ジョブで実行される。
 * RefreshDatabaseWithTenant と混在させると他テストの DB 状態を破壊するため分離が必要。
 */
#[Group('database-migrations')]
class FolderTest extends TestCase
{
    use DatabaseMigrations;
}
```

---

## 5. CI ジョブ分離の理由

`DatabaseMigrations` / `DatabaseMigrationsOnce` を使うテストを通常の unit/feature ジョブと
混在させてはならない。

### なぜ混在が問題か

```
通常テスト（RefreshDatabaseWithTenant）
  → ci-test-tenant を事前作成・テナントDBをmigrate済み

FolderTest（DatabaseMigrations）
  → setUp(): migrate:fresh（テナントDBを含め全DB再構築）
  → tearDown(): migrate:rollback（全テーブルDROP）
    → ci-test-tenant DBが消滅

次の通常テスト（RefreshDatabaseWithTenant）
  → ci-test-tenant が存在しない
  → factory()->create() が接続タイムアウト（60秒）❌
```

### 対応

`.github/workflows/phpunit.yml` の `unit`/`feature` ジョブに
`--exclude-group=database-migrations` が設定されている。
`database-migrations` グループのテストは独立した `db-migrations` ジョブで実行される。

---

## 6. パフォーマンス比較（実測値）

| 変更前 | 変更後 |
|---|---|
| `LedgerFullTextSearchTest` 9テスト: **117秒**（13秒×9） | **16秒**（migrate:fresh 1回のみ） |
| `SearchControllerAdditionalTest` 8テスト: **104秒** | **32秒** |
| `LedgerControllerTest` (Api) 4テスト: **64秒タイムアウト** | **12秒** |

---

## 7. チェックリスト（テスト変更・新規作成時）

テストを変更・新規作成した後は以下を確認すること：

- [ ] 全文検索（`Ledger::search()` / `MATCH() AGAINST()`）を使う場合、`DatabaseMigrationsOnce` を使っているか
- [ ] `DatabaseMigrations` / `DatabaseMigrationsOnce` を使う場合、`#[Group('database-migrations')]` を付与しているか
- [ ] `tearDown()` で `tearDownDatabaseMigrationsOnce()` を呼んでいるか（TRUNCATEクリーンアップ）
- [ ] `RefreshDatabase` を使うべきでないところで使っていないか（テナント系テストは `RefreshDatabaseWithTenant` を使う）
- [ ] ローカルで `./vendor/bin/sail pest --group=database-migrations` を実行して通過することを確認したか
- [ ] CI の `db-migrations` ジョブで当該テストが検出されることを `--list-tests` で確認したか


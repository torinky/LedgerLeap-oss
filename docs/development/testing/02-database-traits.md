# DB トレイト・テナント・マイグレーション管理

**最終更新:** 2026-03-21
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## トレイト選択フロー

```
テストを書く
  |
  +-- Mroonga 全文検索（MATCH AGAINST）を使う？
  |     YES --> DatabaseMigrationsOnce を使う（§3参照）
  |
  +-- 複数テナントを跨ぐ境界検証（$tenantA->run() / $tenantB->run()）？
  |     YES --> DatabaseMigrations + #[Group('database-migrations')]
  |
  +-- テナントコンテキストが必要？
  |     YES --> RefreshDatabaseWithTenant
  |
  +-- それ以外 --> RefreshDatabase
```

詳細な使い分けと高速化パターンは
[.github/skills/database-migrations-test-optimization/SKILL.md](../../../.github/skills/database-migrations-test-optimization/SKILL.md)
を参照。

---

## テナント対応テストの必須セットアップ

LedgerLeap はマルチテナント対応のため、**全ての Feature テストでテナント初期化が必須**。

```php
use Tests\Traits\RefreshDatabaseWithTenant;

class MyFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        // テナントは RefreshDatabaseWithTenant が共有テナントを作成・初期化済み
    }
}
```

### テナント初期化の注意点

**重複初期化の禁止:**
`setUp()` で `tenancy()->initialize()` を行っている場合、テストメソッド内で別のテナントオブジェクトを使って再初期化してはならない。
Spatie\Permission のキャッシュ等が不整合を起こし「閲覧権限がありません」等の不可解な失敗を招く。

**権限付与のタイミング:**
`actingAs($user)` の前に必要なパーミッションを付与する。権限変更はテナント初期化後・テスト操作直前が最も安全。

**テナント初期化を忘れた場合の症状:**
- リレーションクエリが `null` を返す
- `ledger_id` は設定されているのに `$attachment->ledger` が `null` になる

### `test:coverage` と DB 復旧の標準導線

`composer test:coverage` の前処理で DB が壊れた場合は、`prepare-local-test-env.sh` の中だけで完結させず、次の順序を守る。

1. `./bin/reset-test-db.sh` で central / worker DB を再構築する
2. `docs/work/testing/2026-03-21_test-coverage-db-recovery-and-tenancy-guidelines.md` を確認する
3. `prepare-local-test-env.sh` は `mysql_testing` を明示して `db:wipe` → `migrate` → `tenants:migrate` の順で進める

**覚えておくこと**
- `tenant` テストは `tenancy()->initialize($tenant)` が前提
- `tenants:migrate` は tenant DB のマイグレーション専用
- `RefreshDatabase` だけで tenant 初期化を代替しない
- `Schema::hasTable()` / `dropIfExists()` を使って migration を再実行可能に保つ

---

## Mroonga 全文検索テスト

### 必須設定

```php
use Tests\Traits\DatabaseMigrationsOnce;
use PHPUnit\Framework\Attributes\Group;

#[Group('database-migrations')]
class SearchTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Embeddingコンテナへの接続を防ぐ
        $this->setUpDatabaseMigrationsOnce();
        $this->tenant = static::$sharedTenantForMigrationsOnce;
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce();
        parent::tearDown();
    }
}
```

### 全文検索の注意点

- **複合インデックス不可**: Mroonga では `MATCH(col1, col2)` が動作しない
- **OR 結合が必要**: `MATCH(col1) AGAINST(...) OR MATCH(col2) AGAINST(...)` で検索
- **`RefreshDatabase` 使用不可**: トランザクションロールバックではインデックスが正しく復元されない

---

## spatie/laravel-query-builder 使用ガイド

### カンマ区切りパラメータの処理

```php
// ❌ 問題のあるスコープフィルタ
AllowedFilter::scope('with_tags'), // カンマ区切りが正しく処理されない

// ✅ 推奨: コールバックフィルタ
AllowedFilter::callback('with_tags', function ($query, $value) {
    $tagNames = is_string($value) ? array_filter(explode(',', $value)) : $value;
    if (!empty($tagNames)) {
        $query->whereHas('define.tags', function ($q) use ($tagNames) {
            $q->whereIn('name', $tagNames);
        }, '=', count($tagNames));
    }
}),
```

### 除外検索の実装

```php
AllowedFilter::callback('exclude_q', function ($query, $value) {
    $query->where(function ($q) use ($value) {
        $q->whereRaw('not match(`content`) against (? IN BOOLEAN MODE)', [$value])
          ->whereRaw('not match(`content_attached`) against (? IN BOOLEAN MODE)', [$value]);
    });
}),
```

---

## マイグレーション管理とトラブルシューティング

### テスト環境でのマイグレーションリセット

```bash
# 推奨: 専用スクリプト
./bin/reset-test-db.sh

# 直近の coverage 再実行
./vendor/bin/sail composer test:coverage
```

**⚠️ 注意:**
- `migrate:fresh` は環境によって MySQL モニタに入ってしまう場合がある
- `migrate:refresh` はデッドロックリスクがある（使用非推奨）
- `prepare-local-test-env.sh` は `mysql_testing` と Laravel の接続状態を揃えたうえで実行する

### マイグレーションファイルの冪等性（必須）

```php
// カラム追加
if (! Schema::hasColumn('table', 'new_col')) {
    $table->timestamp('new_col')->nullable()->after('some_col');
}

// インデックス追加
if (! Schema::hasIndex('table', 'idx_name')) {
    $table->index('column', 'idx_name');
}

// 安全な削除
public function down(): void
{
    Schema::table('table', function (Blueprint $table) {
        if (Schema::hasIndex('table', 'idx_name')) {
            $table->dropIndex('idx_name');
        }
        $cols = array_filter(['col1', 'col2'], fn($c) => Schema::hasColumn('table', $c));
        if (!empty($cols)) {
            $table->dropColumn($cols);
        }
    });
}
```

### チェックリスト

マイグレーション作成時:
- [ ] `hasColumn()` / `hasIndex()` で存在チェック
- [ ] `after()` 句は動的に決定
- [ ] `down()` メソッドも冪等性を確保
- [ ] `comment()` で日本語説明を記述


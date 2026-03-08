# CI Fix Patterns — ci-failure-investigation Reference

## §1 DomainOccupiedByOtherTenantException

Multiple test classes calling `domains()->create(['domain' => 'test.localhost'])` collide
because `RefreshDatabase` does not run `migrate:fresh` in CI.

### Fix: unique domain per class

```php
// ❌ all classes share the same domain
$this->tenant = Tenant::create();
$this->tenant->domains()->create(['domain' => 'test.localhost']);

// ✅ unique per class
$this->tenant = Tenant::create(['id' => 'my-feature-' . uniqid()]);
$this->tenant->domains()->firstOrCreate(['domain' => 'my-feature-test.localhost']);
```

### Naming convention

| Class | Domain |
|---|---|
| `FolderFormTest` | `folder-form-test.localhost` |
| `LedgerDuplicateControllerTest` | `ledger-duplicate-test.localhost` |

### Alternative: move to `database-migrations` group

If setUp is complex (Role::create, multiple tenants), add `#[Group('database-migrations')]`
to run in the isolated `db-migrations` CI job where `migrate:fresh` is guaranteed.

---

## §2 wnjpn.db Not Found

`wnjpn.db` (194 MB) is excluded from git. Split zips
(`wnjpn.db.zip.aa` + `wnjpn.db.zip.ab`) are committed but must be merged in CI.

### Add to phpunit.yml (all jobs)

```yaml
- name: Restore wnjpn.db from split zips
  run: |
    cat database/wordnet_data/wnjpn.db.zip.aa \
        database/wordnet_data/wnjpn.db.zip.ab > /tmp/wnjpn.db.zip
    unzip -o /tmp/wnjpn.db.zip -d database/wordnet_data/
```

### .gitignore conflict

If `.gitignore` contains `!/database/wordnet_data/wnjpn.db` (force-include),
remove it and add `/database/wordnet_data/wnjpn.db` at the end of the file.

---

## §3 Local vs CI Environment Differences

| Item | Local | CI |
|---|---|---|
| DB_PASSWORD | `password` | `""` (empty) |
| Embedding container | available at `http://embedding:8000` | **not available** |
| `QUEUE_CONNECTION` | `sync` | `sync` (set in phpunit.yml) |
| `RAG_ENABLED` | often `true` in `.env` | `false` (set in phpunit.xml) |
| `wnjpn.db` | present | needs zip merge step |

Tests that need RAG must opt in: `config(['rag.enabled' => true])` in `setUp()`.

---

## §4 Wrong `#[Group('database-migrations')]` Placement (since Issue #74 Sprint 8)

Adding this group to a `RefreshDatabaseWithTenant` test puts it in the same job as
`FolderTest`, whose `tearDown()` calls `migrate:rollback` and drops all central tables.

| Trait used | Correct CI job |
|---|---|
| `DatabaseMigrations` / `DatabaseMigrationsOnce` | `db-migrations` — **add** group |
| `RefreshDatabaseWithTenant` / `RefreshDatabase` | `feature` — **do NOT add** group |

**Diagnosis**: `SQLSTATE[42S02]: Table 'tenants' doesn't exist` after a passing class
→ check for `#[Group('database-migrations')]` on the failing class.

---

## §5 TestDatabaseState — Resetting RefreshDatabaseWithTenant Global State

When a test calls `migrate:fresh` in `setUp()`, `$globalDatabaseMigrated` stays `true`.
Fix: call `TestDatabaseState::reset()` after `migrate:fresh`.

```php
$this->artisan('migrate:fresh', ['--force' => true]);
TestDatabaseState::reset();  // resets $globalDatabaseMigrated, $sharedTenant, etc.
```

`TestDatabaseState` lives at `tests/Support/TestDatabaseState.php`.

---

## §6 DatabaseMigrationsOnce: tenants テーブルが FolderTest 後に DROP される

**症状**: `db-migrations` ジョブで `FolderTest` が PASS した直後、
`SearchApiTest` / `LedgerFullTextSearchTest` / `SearchControllerAdditionalTest` が
`SQLSTATE[42S02]: Table 'ledgerleap_test.tenants' doesn't exist` で一括 FAIL。

**根本原因**:
- `FolderTest` は `DatabaseMigrations` を使用
- `DatabaseMigrations::tearDown()` が各テストメソッド後に `migrate:rollback` を実行
- `migrate:rollback` は central DB の **全テーブル**（`tenants` を含む）を DROP する
- その後、`DatabaseMigrationsOnce` の CI モードが `Tenant::find('ci-test-tenant')` を呼ぶと
  `tenants` テーブルが存在しないため `QueryException` が発生

**実行順（問題が起きるパターン）**:
```
FolderTest (DatabaseMigrations) → migrate:rollback で tenants DROP
  ↓
SearchApiTest (DatabaseMigrationsOnce) → Tenant::find() → FAIL
```

**修正**: `DatabaseMigrationsOnce::setUpDatabaseMigrationsOnce()` に
`Schema::connection('mysql_testing')->hasTable('tenants')` のガードを追加。
テーブルが存在しない場合は `migrate --force` + `tenants:migrate` を再実行する。

```php
// tests/Traits/DatabaseMigrationsOnce.php (CI ブランチ)
if (! Schema::connection('mysql_testing')->hasTable('tenants')) {
    $this->artisan('migrate', ['--force' => true]);
    $this->app[Kernel::class]->setArtisan(null);
    $newTenant = \App\Models\Tenant::firstOrCreate(['id' => 'ci-test-tenant']);
    $this->artisan('tenants:migrate', ['--tenants' => [$newTenant->id], '--force' => true]);
    $this->app[Kernel::class]->setArtisan(null);
    static::$sharedTenantForMigrationsOnce = $newTenant;
} else {
    // 通常パス: ci-test-tenant を find() して再利用
}
```

同様に「2回目以降」パスにも同じガードを追加（`static::$migratedOnceByClass` フラグが
`true` のまま残っているケースにも対応）。

**再発防止**: `db-migrations` ジョブのテスト実行順は PHPUnit のアルファベット順に依存する。
`FolderTest` → `LedgerFullTextSearchTest` → `SearchApiTest` のように `F < L < S` の順になるため、
この問題は構造的に発生しうる。`Schema::hasTable()` ガードが常に有効。


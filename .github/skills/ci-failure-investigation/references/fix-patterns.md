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

## §4 Wrong `#[Group('database-migrations')]` Placement (since Issue #74 Sprint 8)

Adding `#[Group('database-migrations')]` to a test that uses `RefreshDatabaseWithTenant`
(not `DatabaseMigrations`) puts it in the same CI job as `FolderTest`.

`FolderTest` uses `DatabaseMigrations` whose `tearDown()` calls `migrate:rollback`,
dropping all central tables (`tenants`, `domains`, etc.). The next class that calls
`tenancy()->initialize($sharedTenant)` gets `SQLSTATE[42S02]: Table 'tenants' doesn't exist`.

### Rule

| Trait used | Correct CI job |
|---|---|
| `DatabaseMigrations` | `db-migrations` — add `#[Group('database-migrations')]` |
| `DatabaseMigrationsOnce` | `db-migrations` — add `#[Group('database-migrations')]` |
| `RefreshDatabaseWithTenant` | `feature` — **do NOT add** `#[Group('database-migrations')]` |
| `RefreshDatabase` | `feature` — **do NOT add** `#[Group('database-migrations')]` |

### Diagnosis

If `SearchApiTest` (or any `RefreshDatabaseWithTenant` class) suddenly fails with
`Table 'tenants' doesn't exist` after another class passes, check for
`#[Group('database-migrations')]` on the failing class.

---

## §5 TestDatabaseState — Resetting RefreshDatabaseWithTenant Global State

When a test calls `migrate:fresh` in `setUp()` (e.g. `SearchApiPoCTest`),
`RefreshDatabaseWithTenant::$globalDatabaseMigrated` is still `true` from a previous class,
so subsequent classes skip re-migration. Fix: call `TestDatabaseState::reset()` after
`migrate:fresh`.

```php
$this->artisan('migrate:fresh', ['--force' => true]);
TestDatabaseState::reset();  // resets $globalDatabaseMigrated, $sharedTenant, etc.
```

`TestDatabaseState` lives at `tests/Support/TestDatabaseState.php`.
The 4 static properties of `RefreshDatabaseWithTenant` are `public` so this helper can reach them.

---

## §3 Local vs CI Environment Differences

| Item | Local | CI |
|---|---|---|
| DB_PASSWORD | `password` | `""` (empty) |
| Embedding container | available at `http://embedding:8000` | **not available** |
| `QUEUE_CONNECTION` | `sync` | `sync` (set in phpunit.yml) |
| `RAG_ENABLED` | often `true` in `.env` | `false` (set in phpunit.xml) |
| `wnjpn.db` | present | needs zip merge step |

### RAG_ENABLED in phpunit.xml

```xml
<php>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="RAG_ENABLED" value="false"/>
</php>
```

Tests that need RAG must opt in explicitly:
```php
protected function setUp(): void
{
    parent::setUp();
    config(['rag.enabled' => true]);
}
```


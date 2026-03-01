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


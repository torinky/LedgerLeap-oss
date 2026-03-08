# DatabaseMigrationsOnce Trait Usage — database-migrations-test-optimization Reference

## Implementation

`tests/Traits/DatabaseMigrationsOnce.php`

- Runs `migrate:fresh` **once per class** (not per test method)
- Cleans up with `TRUNCATE` after each test (not `migrate:rollback`)
- Why TRUNCATE instead of transaction rollback:
  Mroonga's full-text index is updated outside transactions — rollback leaves stale index data

## Basic Usage

```php
use Tests\Traits\DatabaseMigrationsOnce;
use PHPUnit\Framework\Attributes\Group;

#[Group('database-migrations')]
class MySearchTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected bool $tenancy = true;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();  // prevent Embedding container calls
        $this->setUpDatabaseMigrationsOnce();

        // shared tenant created by DatabaseMigrationsOnce
        $this->tenant = static::$sharedTenantForMigrationsOnce;
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce();  // TRUNCATE
        parent::tearDown();
    }
}
```

## Default TRUNCATE Tables

Override `getTablesToTruncateForMigrationsOnce()` if your test creates additional tables:

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

## DatabaseMigrations (Cross-Tenant Boundary)

Only use when you need `$tenantA->run()` / `$tenantB->run()` boundary verification.

```php
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Group;

#[Group('database-migrations')]
class CrossTenantTest extends TestCase
{
    use DatabaseMigrations;
}
```

## DomainOccupiedByOtherTenantException Fix

When multiple test classes share `test.localhost`, CI fails because `migrate:fresh` is not
run between tests in the `unit`/`feature` jobs.

### Option A: unique domain per class (recommended)

```php
// ❌ collides in CI
$this->tenant = Tenant::create();
$this->tenant->domains()->create(['domain' => 'test.localhost']);

// ✅ unique per class
$this->tenant = Tenant::create(['id' => 'my-feature-' . uniqid()]);
$this->tenant->domains()->firstOrCreate(['domain' => 'my-feature-test.localhost']);
```

### Option B: move to database-migrations group

Add `#[Group('database-migrations')]` — isolated job runs `migrate:fresh`, so no collision.

### Option C: firstOrCreate with shared tenant

```php
$this->tenant = Tenant::firstOrCreate(['id' => 'shared-tenant']);
$this->tenant->domains()->firstOrCreate(['domain' => 'shared-test.localhost']);
```


# Test Permission Setup Patterns

## Rule: grant permissions BEFORE actingAs()

```php
// ❌ Wrong — user snapshot already taken, role ignored
$component = Livewire::actingAs($user)
    ->test(MyComponent::class);
$user->assignRole('Inspector');  // too late

// ✅ Correct — role assigned before actingAs
$user->assignRole('Inspector');
$component = Livewire::actingAs($user)->test(MyComponent::class);
```

## Feature test setUp() — required order

```php
protected function setUp(): void
{
    parent::setUp();
    tenancy()->initialize($this->tenant);  // 1. tenant first

    $this->user = User::factory()->create();
    $this->user->assignRole('Inspector');  // 2. roles before actingAs

    // 3. Clear permission cache after any role/org change in tests
    app(\App\Services\UserService::class)->flushAllUserPermissionsCache();
    app(\App\Services\TenantAccessService::class)->clearAllCache();
}
```

## Granting folder permissions in tests

```php
use App\Enums\FolderPermissionType;
use App\Models\RoleFolderPermission;

// Grant WRITE access to a folder for a role
RoleFolderPermission::create([
    'role_id'     => $role->id,
    'folder_id'   => $folder->id,
    'permission'  => FolderPermissionType::WRITE,
    'modifier_id' => $adminUser->id,
]);

// Then clear folder cache
app(\App\Repositories\WritableFolderRepository::class)->clearAllCache($user);
```

## Asserting folder access

```php
// Check via repository
$writableIds = app(\App\Repositories\WritableFolderRepository::class)
    ->getWritableFolderIds($user);
$this->assertContains($folder->id, $writableIds);

// Check via policy (preferred for Livewire component tests)
$this->assertTrue($user->can('write', $folder));
```

## WritableFolderRepository mock pattern (MCP tool tests)

```php
// When testing MCP tools that use folder permissions:
$this->mock(\App\Repositories\WritableFolderRepository::class, function ($mock) use ($folder) {
    $mock->shouldReceive('getWritableFolderIds')
        ->andReturn([$folder->id]);
    $mock->shouldReceive('getReadableFolderIds')
        ->andReturn([$folder->id]);
});
```

## Reference files

- `app/Services/UserService.php` → `flushAllUserPermissionsCache()`
- `app/Services/TenantAccessService.php` → `clearAllCache()`
- `app/Repositories/WritableFolderRepository.php` → `clearAllCache()`, `refreshAllCache()`
- `app/Observers/UserPermissionsObserver.php`
- `app/Observers/RoleFolderPermissionObserver.php`


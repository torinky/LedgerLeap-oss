<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;

$tenant = Tenant::find('demo-tenant');
if ($tenant) {
    echo "Initializing tenancy for demo-tenant...\n";
    tenancy()->initialize($tenant);
}

$user = User::where('email', 'admin@example.com')->first();
if (! $user) {
    echo "User admin@example.com not found\n";
    exit;
}

$folder = Folder::where('title', '日報')->first();
if ($folder) {
    $folderIds = $folder->ancestorsAndSelf($folder->id)->pluck('id')->toArray();
    echo "Folder '日報' IDs: ".implode(',', $folderIds)."\n";

    $userService = app(UserService::class);
    $roleIds = $userService->getAllUniqueRolesForUser($user)->pluck('id')->toArray();
    echo 'User role IDs: '.implode(',', $roleIds)."\n";

    $tenantId = $tenant->id;

    // hasFolderPermission の内部クエリを再現
    $query = RoleFolderPermission::query()
        ->join('folders', 'role_folder_permissions.folder_id', '=', 'folders.id')
        ->where('folders.tenant_id', $tenantId)
        ->whereIn('role_folder_permissions.role_id', $roleIds)
        ->whereIn('role_folder_permissions.folder_id', $folderIds);

    echo 'Query SQL: '.$query->toSql()."\n";
    echo 'Query Bindings: '.json_encode($query->getBindings())."\n";

    $results = $query->get();
    echo 'Query results count: '.$results->count()."\n";
    foreach ($results as $r) {
        echo "- Folder ID: {$r->folder_id}, Permission: {$r->permission}\n";
    }
}

$role = Role::where('name', 'Super Admin')->first();
if ($role) {
    echo 'Super Admin role permissions: '.$role->permissions->count()."\n";
} else {
    echo "Super Admin role not found\n";
}

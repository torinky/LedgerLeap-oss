<?php

namespace App\Providers;

use App\Models\Role;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return new class($app) extends PermissionRegistrar
            {
                protected function getPermissionsClass()
                {
                    return Permission::class;
                }

                protected function getRolesClass()
                {
                    return Role::class;
                }

                public function getPermissions($params = [], bool $onlyOne = false)
                {
                    $organization_id = $params['organization_id'] ?? null;

                    return $this->getPermissionsClass()
                        ->when($organization_id, function ($query) use ($organization_id) {
                            return $query->where('organization_id', $organization_id);
                        })
                        ->with('roles')
                        ->get();
                }
            };
        });
    }
}

<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\FolderPolicy;
use App\Policies\LedgerDefinePolicy;
use App\Policies\LedgerPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Repositories\WritableFolderRepository;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Ledger::class => LedgerPolicy::class,
        LedgerDefine::class => LedgerDefinePolicy::class,
        Folder::class => FolderPolicy::class,
        Permission::class => PermissionPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
        Gate::before(function (User $user, $ability) {
            if ($user->hasRole('Super Admin')) {
                //                return true;
            }
        });

        Gate::define('inherit-permissions', function (User $user, $permission, Organization $organization) {
            $ancestors = $organization->ancestors;
            foreach ($ancestors as $ancestor) {
                if ($user->hasPermissionTo($permission, $ancestor)) {
                    return true;
                }
            }

            return false;
        });

    }

    public function register()
    {
        $this->app->bind(WritableFolderRepository::class, function ($app) {
            return new WritableFolderRepository;
        });
    }
}

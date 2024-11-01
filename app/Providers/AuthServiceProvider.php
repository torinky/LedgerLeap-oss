<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Organization;
use App\Models\User;
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
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
        Gate::before(function (User $user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
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
}

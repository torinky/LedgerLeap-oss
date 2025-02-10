<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Observers\RoleFolderPermissionObserver;
use App\Observers\UserPermissionsObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The model observers for your application.
     *
     * @var array
     */
    protected $observers = [
        User::class => [UserPermissionsObserver::class],
        Role::class => [UserPermissionsObserver::class],
        Organization::class => [UserPermissionsObserver::class],
    ];

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        RoleFolderPermission::observe(RoleFolderPermissionObserver::class);

    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

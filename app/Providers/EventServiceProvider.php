<?php

namespace App\Providers;

use App\Listeners\ProcessActivityLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Models\Ledger;
use App\Observers\RoleFolderPermissionObserver;
use App\Observers\UserPermissionsObserver;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        /*        'eloquent.created: Spatie\Activitylog\Models\Activity' => [ // Activity モデルの created イベントをリッスン
                    ProcessActivityLog::class,
                ],*/
        'eloquent.created: App\Models\Ledger' => [ // Ledger モデルの created, updated, deleted イベントをリッスン
            ProcessActivityLog::class,
        ],
        'eloquent.updated: App\Models\Ledger' => [
            ProcessActivityLog::class,
        ],
        'eloquent.deleted: App\Models\Ledger' => [
            ProcessActivityLog::class,
        ],

    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        RoleFolderPermission::observe(RoleFolderPermissionObserver::class);

        // Ledger モデルのイベントをリッスンし、NotificationService のメソッドを呼び出す
        Ledger::created(function (Ledger $ledger) {
            $notificationService = app(NotificationService::class);
            $notificationService->processActivityLog($ledger);
        });
        Ledger::updated(function (Ledger $ledger) {
            $notificationService = app(NotificationService::class);
            $notificationService->processActivityLog($ledger);
        });
        Ledger::deleted(function (Ledger $ledger) {
            $notificationService = app(NotificationService::class);
            $notificationService->processActivityLog($ledger);
        });

    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Repositories\WritableFolderRepository;
use App\Services\UserService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;
    use HasRoles {
        assignRole as protected spatieAssignRole;
        removeRole as protected spatieRemoveRole;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function boot()
    {
        parent::boot();

        // キャッシュの自動更新
        static::saved(function ($user) {
            app(WritableFolderRepository::class)->clearAllCache($user);
        });

        static::deleted(function ($user) {
            app(WritableFolderRepository::class)->refreshAllCache($user);
        });
    }

    /*    public function writableFolderIds()
        {
            return app(WritableFolderRepository::class)->getWritableFolderIds($this);
        }

        public function readableFolderIds()
        {
            return app(WritableFolderRepository::class)->getReadableFolderIds($this);
        }*/

    protected UserService $userService;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->userService = app(UserService::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'user_organizations')->withPivot('is_primary');
    }

    public function primaryOrganization()
    {
        return $this->belongsToMany(Organization::class, 'user_organizations')
            ->wherePivot('is_primary', true)
            ->withPivot('is_primary')
            ->first();
    }

    public function setPrimaryOrganization(Organization $organization)
    {
        $this->organizations()->update(['is_primary' => false]); // 既存のプライマリ組織を解除
        $this->organizations()->syncWithPivotValues([$organization->id], ['is_primary' => true], false);
    }

    public function getAllPermissions()
    {
        return $this->userService->getAllPermissionsForUser($this);
    }

    public function getAllRoles()
    {
        return $this->userService->getAllRolesForUser($this);
    }

    public function hasPermissionForOrganization($permission, $organization)
    {
        return $this->userService->hasPermissionForOrganization($this, $permission, $organization);
    }

    public function hasRoleForOrganization($role, $organization)
    {
        return $this->userService->hasRoleForOrganization($this, $role, $organization);
    }

    public function assignRoleToOrganization($role, $organization)
    {
        $this->userService->assignRoleToOrganization($this, $role, $organization);
    }

    public function hasRoleInOrganization($role, $organization)
    {
        return $this->userService->hasRoleInOrganization($this, $role, $organization);
    }

    public function getAllUniqueRoles()
    {
        return $this->userService->getAllUniqueRolesForUser($this);
    }

    public function getAllUniquePermissions()
    {
        return $this->userService->getAllUniquePermissionsForUser($this);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // すべてのドメインからのアクセスを許可
    }

    /**
     * SpatieのassignRoleメソッドをオーバーライドして、
     * ロール割り当て後にWritableFolderRepositoryのキャッシュをクリアする
     *
     * @param mixed ...$roles
     * @return $this
     */
    public function assignRole(...$roles): static
    {
        $this->spatieAssignRole(...$roles);

        app(WritableFolderRepository::class)->clearAllCache($this);
        app(UserService::class)->clearUserPermissionsCache($this);

        return $this;
    }

    /**
     * ユーザーからロールを削除し、関連するキャッシュをクリアする
     *
     * @param mixed ...$roles
     * @return $this
     */
    public function removeRole($role)
    {
        $this->spatieRemoveRole(...$role);

        app(WritableFolderRepository::class)->clearAllCache($this);
        app(UserService::class)->clearUserPermissionsCache($this);

        return $this;
    }

    /**
     * Get the user's unread notifications, considering both direct notifications and role-based notifications.
     *
     */
    public function unreadNotifications()
    {
        $userService = app(UserService::class);
        $roles = $userService->getAllUniqueRolesForUser($this);

        return DatabaseNotification::query()
            ->where(function ($query) use ($roles) {
                $query->where(function ($q) use ($roles) {
                    $q->where('notifiable_type', Role::class)
                        ->whereIn('notifiable_id', $roles->pluck('id'));
                })->orWhere(function ($q) {
                    $q->where('notifiable_type', get_class($this))
                        ->where('notifiable_id', $this->id);
                });
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('notification_user')
                    ->whereColumn('notification_user.notification_id', 'notifications.id')
                    ->where('notification_user.user_id', $this->id);
            });
    }

    public function notificationSettings()
    {
        return $this->hasMany(NotificationSetting::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'causer_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'content', 'ledger_define_id']) // 変更を監視する属性
            ->logOnlyDirty() // 変更があった場合のみ記録
            ->dontSubmitEmptyLogs() // 空のログは記録しない
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "Ledger has been {$eventName}");
        // ->logUnguarded() // ガードされていないすべての属性をログに記録 (fillable の逆)
        // ->dontLogIfAttributesChangedOnly(['column_define']) // 特定の属性のみが変更された場合はログを記録しない
    }

    /*    public function getActivitylogOptions(): LogOptions
        {
            return LogOptions::defaults()
                ->logOnly(['name', 'content', 'ledger_define_id']) // 変更を監視する属性
                ->logOnlyDirty() // 変更があった場合のみ記録
                ->dontSubmitEmptyLogs() // 空のログは記録しない
                ->logFillable();
        }*/
    /**
     * Get all notifications for the user via their roles.
     */
    public function roleNotifications(): HasManyThrough
    {
        return $this->hasManyThrough(
            config('notifications.database.model'), // 通常は \Illuminate\Notifications\DatabaseNotification::class
            Role::class, // カスタム Role モデル (app/Models/Role.php)
            'id', // Role モデルの主キー
            'notifiable_id', // notifications テーブルの外部キー
            'id', // User モデルの主キー
            'id'   // Role モデルの主キー
        )
            ->where('notifiable_type', Role::class);
    }
}

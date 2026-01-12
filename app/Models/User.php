<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LoginLandingPage;
use App\Repositories\WritableFolderRepository;
use App\Services\UserService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Lang;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
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
        'objectguid',
        'name',
        'email',
        'password',
        'login_landing_page',
        'ignore_ad_org_sync_until',
        'manual_sync_reason',
        'ad_last_synced_at',
        'chat_link',
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
        'login_landing_page' => LoginLandingPage::class,
        'pending_inspection_count' => 'integer',
        'pending_approval_count' => 'integer',
        'ad_last_synced_at' => 'datetime',
        'ignore_ad_org_sync_until' => 'datetime',
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

    /*    public function getAllPermissions()
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
*/
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
     * @param  mixed  ...$roles
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
     * @param  mixed  ...$roles
     * @return $this
     */
    public function removeRole($role)
    {
        $this->spatieRemoveRole($role);

        app(WritableFolderRepository::class)->clearAllCache($this);
        app(UserService::class)->clearUserPermissionsCache($this);
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('user')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getLogDescriptionForEvent($eventName));
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.user_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.user_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "ユーザーが{$eventName}されました";
    }

    /**
     * グローバル通知をさせるためにルートフォルダーを返す
     *
     * @return Folder
     */
    /**
     * ユーザーが所属するテナントへの多対多リレーションシップを定義します。
     */

    /**
     * グローバル通知をさせるためにルートフォルダーを返す
     *
     * @return Folder
     */
    public function folder()
    {
        return Folder::root();
    }
}

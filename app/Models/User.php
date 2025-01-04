<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Repositories\WritableFolderRepository;
use App\Services\UserService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasRoles {
        assignRole as protected spatieAssignRole;
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
            app(WritableFolderRepository::class)->refreshWritableFolderCache($user);
            app(WritableFolderRepository::class)->refreshReadableFolderCache($user);
        });

        static::deleted(function ($user) {
            app(WritableFolderRepository::class)->clearWritableFolderCache($user);
            app(WritableFolderRepository::class)->clearReadableFolderCache($user);
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

        app(WritableFolderRepository::class)->clearWritableFolderCache($this);
        app(WritableFolderRepository::class)->clearReadableFolderCache($this);

        return $this;
    }
}

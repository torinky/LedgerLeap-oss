<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 */
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'user_organizations')->withPivot('is_primary');
    }

    // ユーザーの全ての権限を取得（所属組織からの継承を含む）
    public function getAllPermissions()
    {
        $permissions = $this->permissions;

        foreach ($this->organizations as $organization) {
            $permissions = $permissions->merge($organization->getAllPermissions());
        }

        return $permissions->unique('id');
    }

    // ユーザーの全ての役割を取得（所属組織からの継承を含む）
    public function getAllRoles()
    {
        $roles = $this->roles;

        foreach ($this->organizations as $organization) {
            $roles = $roles->merge($organization->getAllRoles());
        }

        return $roles->unique('id');
    }

    public function primaryOrganization()
    {
        return $this->organizations()->wherePivot('is_primary', true)->first();
    }

    public function hasPermissionForOrganization($permission, $organization)
    {
        // スーパー管理者の場合、常にtrueを返す
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // ユーザー自身の権限をチェック
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        // 指定された組織とその祖先組織の権限をチェック
        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $this->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRoleForOrganization($role, $organization)
    {
        // ユーザー自身の役割をチェック
        if ($this->hasRole($role)) {
            return true;
        }

        // 組織とその祖先組織の役割をチェック
        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $this->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function setPrimaryOrganization(Organization $organization)
    {
        $this->organizations()->updateExistingPivot($organization->id, ['is_primary' => true]);
        $this->organizations()->where('id', '!=', $organization->id)->updateExistingPivot($organization->id, ['is_primary' => false]);
    }

    public function getPrimaryOrganizationAttribute()
    {
        return $this->organizations()->wherePivot('is_primary', true)->first();
    }

    /*    public function getStoredRole($roleName, $guardName = null)
        {
            $guardName = $guardName ?? $this->getDefaultGuardName();
            return Role::findByName($roleName, $guardName);
        }*/

    public function assignRoleToOrganization($role, $organization)
    {
        if (is_string($role)) {
            $role = Role::findByName($role, 'web');
        }

        $this->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    public function hasRoleInOrganization($role, $organization)
    {
        return $this->roles()
            ->where('name', $role)
            ->wherePivot('organization_id', $organization->id)
            ->exists();
    }

    public function getAllUniqueRoles()
    {
        return $this->roles->merge(
            $this->organizations->flatMap->getAllRoles()
        )->unique('id');
    }

    public function getAllUniquePermissions()
    {
        return $this->permissions->merge(
            $this->organizations->flatMap->getAllUniquePermissions()
        )->merge(
            $this->getAllUniqueRoles()->flatMap->permissions
        )->unique('id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // すべてのドメインからのアクセスを許可
    }
}

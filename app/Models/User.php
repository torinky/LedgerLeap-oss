<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 */
class User extends Authenticatable
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
        return $this->belongsToMany(Organization::class, 'user_organizations');
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
}

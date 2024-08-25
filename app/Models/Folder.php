<?php

namespace App\Models;

use App\Traits\HasModelRoles;
use CubeAgency\FilamentTreeView\Traits\HasTreeView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

class Folder extends Model
{
    use HasFactory, HasModelRoles, HasTreeView, NodeTrait, SoftDeletes;

    protected $fillable = [
        'title', 'modifier_id', 'creator_id',
    ];

    protected $guard_name = ['web', 'api'];

    public function ledgerDefines()
    {
        return $this->hasMany(LedgerDefine::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function tag()
    {
        return $this->hasMany(Tag::class, 'ledger_define_id');
    }

    /**
     * 子孫フォルダーのすべての`LedgerDefine`モデルの件数を取得します。
     *
     * @return int
     */
    public function descendantLedgerDefinesCount()
    {
        return $this->descendantsAndSelf($this->id)
            ->reduce(fn($carry, $folder) => $carry + $folder->ledgerDefines()->count(), 0);
    }

    /**
     * 子孫フォルダーのすべての件数を取得します。
     *
     * @return int
     */
    public function descendantCount()
    {
        return $this->descendants()->count();
    }

    /**
     * User モデルへの creator リレーションを定義します。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * User モデルへの modifier リレーションを定義します。
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    /**
     * 親組織から継承された権限を含む全ての権限を取得
     *
     * @return mixed
     */
    public function getAllPermissions()
    {
        $allPermissions = $this->permissions;

        foreach ($this->ancestors as $ancestor) {
            $allPermissions = $allPermissions->merge($ancestor->permissions);
        }

        return $allPermissions->unique('id');
    }

    /**
     * 親組織から継承された役割を含む全ての役割を取得
     *
     * @return mixed
     */
    public function getAllRoles()
    {
        $allRoles = $this->roles;

        foreach ($this->ancestors as $ancestor) {
            $allRoles = $allRoles->merge($ancestor->roles);
        }

        return $allRoles->unique('id');
    }

    public function hasPermissionWithInheritance($permission)
    {
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRoleWithInheritance($role)
    {
        if ($this->hasRole($role)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getDirectRoles()
    {
        return $this->roles;
    }

    public function getInheritedRoles()
    {
        $inheritedRoles = collect();
        foreach ($this->ancestors as $ancestor) {
            $inheritedRoles = $inheritedRoles->merge($ancestor->roles);
        }

        return $inheritedRoles->unique('id')->diff($this->getDirectRoles());
    }

    public function getDirectPermissions()
    {
        return $this->permissions;
    }

    public function getInheritedPermissions()
    {
        $inheritedPermissions = collect();
        foreach ($this->ancestors as $ancestor) {
            $inheritedPermissions = $inheritedPermissions->merge($ancestor->getAllPermissions());
        }

        return $inheritedPermissions->unique('id')->diff($this->getDirectPermissions());
    }

    public function getAllUniquePermissions()
    {
        return $this->getAllPermissions()->unique('id');
    }

    public function getDirectPermissionsViaRoles()
    {
        return $this->getDirectRoles()->flatMap->permissions->unique('id');
    }

    public function getInheritedPermissionsViaRoles()
    {
        return $this->getInheritedRoles()->flatMap->permissions->unique('id');
    }

    public function getAllUniquePermissionsViaRoles()
    {
        return $this->getAllRoles()->flatMap->permissions->unique('id');
    }

    public static function treeList($nodes)
    {

        $options = [];
        $traverse = function ($categories, $prefix = '-') use (&$traverse, &$options) {
            foreach ($categories as $category) {
                $options[$category->id] = $prefix . ' ' . $category->title;

                $traverse($category->children, $prefix . '-');
            }
        };

        $traverse($nodes);

        return $options;
    }
}

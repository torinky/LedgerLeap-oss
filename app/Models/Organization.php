<?php

namespace App\Models;

//use CubeAgency\FilamentTreeView\Traits\HasTreeView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class Organization extends Model
{
//    use HasFactory, HasRoles, HasTreeView, LogsActivity, NodeTrait, SoftDeletes;
    use HasFactory, HasRoles, LogsActivity, NodeTrait, SoftDeletes;

    protected $fillable = ['org_id', 'name', 'description', 'parent_id'];

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('organization')
            ->setDescriptionForEvent(fn(string $eventName) => $this->getLogDescriptionForEvent($eventName));
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.organization_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.organization_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "組織が{$eventName}されました";
    }

    /**
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organizations')->withPivot('is_primary');
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

        return $inheritedPermissions->unique('id')->diff($this->permissions());
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
}

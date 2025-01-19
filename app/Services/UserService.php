<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class UserService
{
    protected $writableFolderRepository;

    public function __construct(WritableFolderRepository $writableFolderRepository)
    {
        $this->writableFolderRepository = $writableFolderRepository;
    }

    /**
     * 指定されたユーザーに関連するすべての権限を取得し、組織からも権限を含めます。
     *
     * @param  User  $user  権限を取得するユーザー。
     * @return Collection 重複を取り除いた権限オブジェクトのコレクション。
     */
    public function getAllPermissionsForUser(User $user): Collection
    {
        $permissions = $user->permissions;

        foreach ($user->organizations as $organization) {
            $permissions = $permissions->merge($organization->getAllPermissions());
        }

        return $permissions->unique('id');
    }

    /**
     * ユーザーが指定された権限を持っているかどうかを判定する
     * 組織から継承した権限も考慮する
     *
     * @param  string|string[]  $permissionName
     */
    public function hasPermission(User $user, $permissionName): bool
    {
        $permissions = $this->getAllUniquePermissionsForUser($user);
        if (is_array($permissionName)) {
            //            dd($permissions->whereIn('name', $permissionName)->isNotEmpty());
            return $permissions->whereIn('name', $permissionName)->isNotEmpty();
        } else {
            return $permissions->contains('name', $permissionName);
        }
    }

    /**
     * 指定されたユーザーに関連するすべての役割を取得し、組織からも役割を含めます。
     *
     * @param  User  $user  役割を取得するユーザー。
     * @return Collection 重複を取り除いた役割オブジェクトのコレクション。
     */
    public function getAllRolesForUser(User $user): Collection
    {
        $roles = $user->roles;

        foreach ($user->organizations as $organization) {
            $roles = $roles->merge($organization->getAllRoles());
        }

        return $roles->unique('id');
    }

    /**
     * ユーザーが組織に対して特定の権限を持っているかどうかを確認します。
     * スーパー管理者、ユーザー自身の権限、ユーザーが所属する組織の権限も確認します。
     *
     * @param  User  $user  権限を確認するユーザー。
     * @param  string  $permission  確認する権限。
     * @param  Organization  $organization  権限を確認する組織。
     * @return bool ユーザーが権限を持っている場合は true、そうでない場合は false。
     */
    public function hasPermissionForOrganization(User $user, string $permission, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $user->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ユーザーが組織に対して特定の役割を持っているかどうかを確認します。
     * スーパー管理者、ユーザー自身の役割、ユーザーが所属する組織の役割も確認します。
     *
     * @param  User  $user  役割を確認するユーザー。
     * @param  string  $role  確認する役割。
     * @param  Organization  $organization  役割を確認する組織。
     * @return bool ユーザーが役割を持っている場合は true、そうでない場合は false。
     */
    public function hasRoleForOrganization(User $user, string $role, Organization $organization): bool
    {
        if ($user->hasRole($role)) {
            return true;
        }

        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $user->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ユーザーに組織に固有の役割を割り当てます。
     * 役割が文字列で渡された場合は、対応する役割オブジェクトを検索します。
     *
     * @param  User  $user  役割を割り当てるユーザー。
     * @param  mixed  $role  割り当てる役割。文字列か役割オブジェクトのいずれか。
     * @param  Organization  $organization  役割を割り当てる組織。
     */
    public function assignRoleToOrganization(User $user, $role, Organization $organization): void
    {
        if (is_string($role)) {
            $role = Role::findByName($role, 'web');
        }

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    /**
     * ユーザーが組織に対して特定の役割を持っているかどうかを確認します。
     *
     * @param  User  $user  役割を確認するユーザー。
     * @param  string  $role  確認する役割。
     * @param  Organization  $organization  役割を確認する組織。
     * @return bool ユーザーが役割を持っている場合は true、そうでない場合は false。
     */
    public function hasRoleInOrganization(User $user, string $role, Organization $organization): bool
    {
        return $user->roles()
            ->where('name', $role)
            ->wherePivot('organization_id', $organization->id)
            ->exists();
    }

    /**
     * ユーザーに関連するすべての一意の権限を取得し、組織からも権限を含めます。
     * キャッシュを利用して、パフォーマンスを向上させる
     *
     * @param  User  $user  権限を取得するユーザー。
     * @return Collection 重複を取り除いた権限オブジェクトのコレクション。
     *
     * このメソッドは、ユーザーが持つすべての権限と、ユーザーが所属する組織が持つすべての権限を
     * 取得し、重複を取り除いたコレクションを返します。組織の権限は、組織の階層構造に従って取得されます。
     */
    public function getAllUniquePermissionsForUser(User $user): Collection
    {
        $cacheKey = "user:{$user->id}:all_permissions";

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {
            return $user->permissions->merge(
                $user->organizations->flatMap->getAllUniquePermissions()
            )->merge(
                $this->getAllUniqueRolesForUser($user)->flatMap->permissions
            )->unique('id');
        });
    }

    /**
     * ユーザーに関連する権限のキャッシュをクリアする
     */
    public function clearUserPermissionsCache(User $user): void
    {
        Cache::forget("user:{$user->id}:all_permissions");
    }

    /**
     * ユーザーに関連するすべての一意の役割を取得し、組織からも役割を含めます。
     *
     * @param  User  $user  役割を取得するユーザー。
     * @return Collection 重複を取り除いた役割オブジェクトのコレクション。
     */
    public function getAllUniqueRolesForUser(User $user): Collection
    {
        return $user->roles->merge(
            $user->organizations->flatMap->getAllRoles()
        )->unique('id');
    }

    public function isManageableFolderForUser(User $user, Folder $folder): bool
    {
        $manageableFolderIds = $this->writableFolderRepository->getManageableFolderIds($user, $folder);

        return in_array($folder->id, $manageableFolderIds);
    }

    /**
     * ユーザーが指定されたフォルダーに対して書き込み権限を持っているかどうかを判定する
     */
    public function isWritableFolderForUser(User $user, Folder $folder): bool
    {
        $writableFolderIds = $this->writableFolderRepository->getWritableFolderIds($user, $folder);

        return in_array($folder->id, $writableFolderIds)
            || $this->isManageableFolderForUser($user, $folder);
    }

    public function isReadableFolderForUser(User $user, Folder $folder): bool
    {
        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user, $folder);

        return in_array($folder->id, $readableFolderIds)
            || $this->isWritableFolderForUser($user, $folder)
            || $this->isManageableFolderForUser($user, $folder);
    }
}

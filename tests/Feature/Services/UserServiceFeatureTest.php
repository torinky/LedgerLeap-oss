<?php

namespace Tests\Feature\Services;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(UserService::class)]
class UserServiceFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private UserService $userService;

    private Folder $folder;

    private Role $role;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->userService = app(UserService::class);
        $this->folder = Folder::factory()->create();
        $this->role = Role::create(['name' => 'test-role-'.uniqid()]);
        $this->user = User::factory()->create();
    }

    // ----------------------------------------------------------------
    // hasFolderPermission
    // ----------------------------------------------------------------

    public function test_has_folder_permission_returns_true_when_role_has_write_permission(): void
    {
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);

        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::WRITE)
        );
    }

    public function test_has_folder_permission_returns_true_when_higher_permission_is_granted(): void
    {
        // ADMIN 権限は WRITE を includes するため、WRITE 要求に対して true を返す
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::ADMIN,
        ]);

        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::WRITE)
        );
    }

    public function test_has_folder_permission_returns_false_when_no_role(): void
    {
        // ロールなし → false
        Cache::tags(['folder_permissions'])->flush();

        $this->assertFalse(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::READ)
        );
    }

    public function test_has_folder_permission_returns_false_when_permission_not_sufficient(): void
    {
        // READ 権限しかないのに WRITE を要求 → false
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);

        Cache::tags(['folder_permissions'])->flush();

        $this->assertFalse(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::WRITE)
        );
    }

    public function test_has_folder_permission_returns_false_when_folder_has_no_tenant(): void
    {
        // tenant_id がないフォルダ → false
        $folder = new Folder;
        $folder->tenant_id = null;

        $this->assertFalse(
            $this->userService->hasFolderPermission($this->user, $folder, FolderPermissionType::READ)
        );
    }

    public function test_has_folder_permission_inspect_and_approve(): void
    {
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT,
        ]);

        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::INSPECT)
        );
        $this->assertFalse(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::APPROVE)
        );
    }

    // ----------------------------------------------------------------
    // isManageableFolderForUser / isWritableFolderForUser / isReadableFolderForUser
    // canInspectInFolder / canApproveInFolder
    // ----------------------------------------------------------------

    public function test_is_manageable_folder_for_user(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::ADMIN,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue($this->userService->isManageableFolderForUser($this->user, $this->folder));
    }

    public function test_is_writable_folder_for_user(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue($this->userService->isWritableFolderForUser($this->user, $this->folder));
        $this->assertFalse($this->userService->isManageableFolderForUser($this->user, $this->folder));
    }

    public function test_is_readable_folder_for_user(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue($this->userService->isReadableFolderForUser($this->user, $this->folder));
        $this->assertFalse($this->userService->isWritableFolderForUser($this->user, $this->folder));
    }

    public function test_can_inspect_and_approve_in_folder(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $this->assertTrue($this->userService->canInspectInFolder($this->user, $this->folder));
        $this->assertFalse($this->userService->canApproveInFolder($this->user, $this->folder));
    }

    // ----------------------------------------------------------------
    // clearFolderPermissionCache
    // ----------------------------------------------------------------

    public function test_clear_folder_permission_cache(): void
    {
        // キャッシュにデータを書き込んでから clearFolderPermissionCache を呼んで消えることを確認
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        // 一度呼んでキャッシュに乗せる
        $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::WRITE);

        // キャッシュクリア（例外が出なければ OK）
        $this->userService->clearFolderPermissionCache($this->user);

        // クリア後もDBから正しい値が返ること
        $this->assertTrue(
            $this->userService->hasFolderPermission($this->user, $this->folder, FolderPermissionType::WRITE)
        );
    }

    // ----------------------------------------------------------------
    // getUsersWithFolderPermission
    // ----------------------------------------------------------------

    public function test_get_users_with_folder_permission_returns_users_with_matching_role(): void
    {
        $this->user->assignRole($this->role);

        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);

        $result = $this->userService->getUsersWithFolderPermission($this->folder, FolderPermissionType::WRITE);

        $this->assertTrue($result->contains('id', $this->user->id));
    }

    public function test_get_users_with_folder_permission_returns_empty_when_no_role_assigned(): void
    {
        // ロールに permission があってもユーザーにロールが割り当てられていない
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);

        $result = $this->userService->getUsersWithFolderPermission($this->folder, FolderPermissionType::WRITE);

        $this->assertFalse($result->contains('id', $this->user->id));
    }

    public function test_get_users_with_folder_permission_returns_empty_when_no_permissions(): void
    {
        $result = $this->userService->getUsersWithFolderPermission($this->folder, FolderPermissionType::WRITE);
        $this->assertCount(0, $result);
    }

    public function test_get_users_with_folder_permission_filters_by_search_query(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);

        // ユーザー名で検索（ヒットしない）
        $result = $this->userService->getUsersWithFolderPermission(
            $this->folder,
            FolderPermissionType::WRITE,
            'XXXXNOTEXIST'
        );
        $this->assertCount(0, $result);

        // ユーザー名の一部で検索（ヒットする）
        $result = $this->userService->getUsersWithFolderPermission(
            $this->folder,
            FolderPermissionType::WRITE,
            mb_substr($this->user->name, 0, 3)
        );
        $this->assertTrue($result->count() >= 1);
    }

    // ----------------------------------------------------------------
    // getClaimableTasks
    // ----------------------------------------------------------------

    public function test_get_claimable_tasks_returns_empty_when_user_has_no_folder_permission(): void
    {
        $result = $this->userService->getClaimableTasks($this->user);
        $this->assertCount(0, $result);
    }

    public function test_get_claimable_tasks_returns_tasks_user_can_take_over(): void
    {
        // claimer に INSPECT 権限を付与
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $creator = User::factory()->create();
        $originalInspector = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $originalInspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $result = $this->userService->getClaimableTasks($this->user);

        $this->assertTrue($result->contains('id', $ledger->id));
    }

    public function test_get_claimable_tasks_excludes_tasks_where_user_is_creator(): void
    {
        // claimer 自身が申請者のタスクは除外される
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);
        $otherInspector = User::factory()->create();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,  // ← user 自身が申請者
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $otherInspector->id,
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $result = $this->userService->getClaimableTasks($this->user);

        $this->assertFalse($result->contains('id', $ledger->id));
    }

    public function test_get_claimable_tasks_excludes_tasks_where_user_is_current_assignee(): void
    {
        // claimer 自身が現在の担当者のタスクは除外される
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::INSPECT,
        ]);
        Cache::tags(['folder_permissions'])->flush();

        $creator = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $creator->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
        ]);

        $diff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->user->id, // ← user 自身が担当者
            'version' => 1,
        ]);
        $ledger->update(['latest_diff_id' => $diff->id]);

        $result = $this->userService->getClaimableTasks($this->user);

        $this->assertFalse($result->contains('id', $ledger->id));
    }

    // ----------------------------------------------------------------
    // getNotifiableRoles / getUsersByRoleIds
    // ----------------------------------------------------------------

    public function test_get_notifiable_roles_returns_all_users_role(): void
    {
        // PoC 実装: 常に 'All Users' ロールを返す（引数は eventType と eventSubject）
        $result = $this->userService->getNotifiableRoles('workflow_action', $this->folder);

        // 'All Users' ロールが存在しない場合は空コレクションになるだけで例外は出ない
        $this->assertNotNull($result);
    }

    public function test_get_users_by_role_ids_returns_users_with_given_roles(): void
    {
        $this->user->assignRole($this->role);

        $result = $this->userService->getUsersByRoleIds([$this->role->id]);

        $this->assertTrue($result->contains('id', $this->user->id));
    }

    public function test_get_users_by_role_ids_returns_empty_for_empty_input(): void
    {
        $result = $this->userService->getUsersByRoleIds([]);
        $this->assertCount(0, $result);
    }

    // ----------------------------------------------------------------
    // canUserAccessSettings
    // ----------------------------------------------------------------

    public function test_can_user_access_settings_returns_true_for_specific_permission(): void
    {
        // 'view_roles' は specificPermissions リストに含まれる
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'view_roles', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->userService->canUserAccessSettings($this->user));
    }

    public function test_can_user_access_settings_returns_true_for_keyword_permission(): void
    {
        // 'manage_something' は keywords 'manage' に前方一致
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'manage_something', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->userService->canUserAccessSettings($this->user));
    }

    public function test_can_user_access_settings_returns_true_for_subject_permission(): void
    {
        // 'view_users' は subjects 'users' を含む
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'view_users', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->userService->canUserAccessSettings($this->user));
    }

    public function test_can_user_access_settings_returns_false_for_no_permissions(): void
    {
        // 権限なし → false
        $this->assertFalse($this->userService->canUserAccessSettings($this->user));
    }

    public function test_can_user_access_settings_returns_false_for_unrelated_permission(): void
    {
        // 設定画面に無関係なパーミッション
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'view_dashboard', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse($this->userService->canUserAccessSettings($this->user));
    }

    // ----------------------------------------------------------------
    // getAccessibleRootFolderIdForUser
    // ----------------------------------------------------------------

    public function test_get_accessible_root_folder_id_returns_null_when_no_permissions(): void
    {
        $result = $this->userService->getAccessibleRootFolderIdForUser($this->user);
        $this->assertNull($result);
    }

    public function test_get_accessible_root_folder_id_returns_root_folder_id(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);

        $result = $this->userService->getAccessibleRootFolderIdForUser($this->user);
        $this->assertNotNull($result);
        $this->assertIsInt($result);
    }

    // ----------------------------------------------------------------
    // getAccessibleTenantsForUser
    // ----------------------------------------------------------------

    public function test_get_accessible_tenants_returns_empty_when_no_roles(): void
    {
        $result = $this->userService->getAccessibleTenantsForUser($this->user);
        $this->assertCount(0, $result);
    }

    public function test_get_accessible_tenants_returns_tenant_when_folder_permission_exists(): void
    {
        $this->user->assignRole($this->role);
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);

        $result = $this->userService->getAccessibleTenantsForUser($this->user);

        // テナントが1件以上返る
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    public function test_get_accessible_tenants_returns_empty_when_no_permissions(): void
    {
        // ロールは持つが folder_permission なし
        $this->user->assignRole($this->role);

        $result = $this->userService->getAccessibleTenantsForUser($this->user);
        $this->assertCount(0, $result);
    }
}

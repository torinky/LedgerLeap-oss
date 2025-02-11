<?php

namespace tests\Unit\Policies;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private RolePolicy $rolePolicy;

    private UserService $userService;

    private User $userWithViewRolesPermission;

    private User $userWithoutViewRolesPermission;

    public function test_should_allow_a_user_with_view_roles_permission_to_view_roles()
    {
        $this->assertTrue($this->rolePolicy->viewAny($this->userWithViewRolesPermission));
        $this->assertTrue($this->rolePolicy->view($this->userWithViewRolesPermission));
    }

    public function test_should_not_allow_a_user_without_view_roles_permission_to_view_roles()
    {
        $this->assertFalse($this->rolePolicy->viewAny($this->userWithoutViewRolesPermission));
        $this->assertFalse($this->rolePolicy->view($this->userWithoutViewRolesPermission));
    }

    public function test_should_allow_a_user_with_manage_roles_permission_to_view_roles()
    {
        $userWithManageRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithManageRolesPermission, ['view_roles', 'manage_roles'])
            ->andReturn(true);

        $this->assertTrue($this->rolePolicy->viewAny($userWithManageRolesPermission));
        $this->assertTrue($this->rolePolicy->view($userWithManageRolesPermission));
    }

    public function test_should_allow_a_user_with_create_roles_permission_to_create_roles()
    {
        $userWithCreateRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithCreateRolesPermission, ['create_roles', 'manage_roles'])
            ->andReturn(true);

        $this->assertTrue($this->rolePolicy->create($userWithCreateRolesPermission));
    }

    public function test_should_deny_a_user_without_manage_roles_permission_from_viewing_roles()
    {
        $userWithoutManageRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithoutManageRolesPermission, ['view_roles', 'manage_roles'])
            ->andReturn(false);

        $this->assertFalse($this->rolePolicy->viewAny($userWithoutManageRolesPermission));
        $this->assertFalse($this->rolePolicy->view($userWithoutManageRolesPermission));
    }

    public function test_should_deny_a_user_without_create_roles_permission_from_creating_roles()
    {
        $userWithoutCreateRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithoutCreateRolesPermission, ['create_roles', 'manage_roles'])
            ->andReturn(false);

        $this->assertFalse($this->rolePolicy->create($userWithoutCreateRolesPermission));
    }

    public function test_should_allow_a_user_with_manage_roles_permission_to_create_roles()
    {
        $userWithManageRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithManageRolesPermission, ['create_roles', 'manage_roles'])
            ->andReturn(true);

        $this->assertTrue($this->rolePolicy->create($userWithManageRolesPermission));
    }

    public function test_should_deny_a_user_without_manage_roles_permission_from_creating_roles()
    {
        $userWithoutManageRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithoutManageRolesPermission, ['create_roles', 'manage_roles'])
            ->andReturn(false);

        $this->assertFalse($this->rolePolicy->create($userWithoutManageRolesPermission));
    }

    public function test_should_allow_a_user_with_edit_roles_permission_to_update_roles()
    {
        $userWithEditRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithEditRolesPermission, ['update_roles', 'manage_roles'])
            ->andReturn(true);

        $this->assertTrue($this->rolePolicy->update($userWithEditRolesPermission));
    }

    public function test_should_deny_a_user_without_edit_roles_permission_from_updating_roles()
    {
        $userWithoutEditRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($userWithoutEditRolesPermission, ['update_roles', 'manage_roles'])
            ->andReturn(false);

        $this->assertFalse($this->rolePolicy->update($userWithoutEditRolesPermission));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = $this->mock(UserService::class);
        $this->rolePolicy = new RolePolicy($this->userService);

        $this->userWithViewRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($this->userWithViewRolesPermission, ['view_roles', 'manage_roles'])
            ->andReturn(true);

        $this->userWithoutViewRolesPermission = User::factory()->create();
        $this->userService->shouldReceive('hasPermission')
            ->with($this->userWithoutViewRolesPermission, ['view_roles', 'manage_roles'])
            ->andReturn(false);
    }
}

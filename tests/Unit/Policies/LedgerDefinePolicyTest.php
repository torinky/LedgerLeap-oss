<?php

namespace tests\Unit\Policies;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Policies\LedgerDefinePolicy;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use tests\TestCase;

class LedgerDefinePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_returns_true_for_user_with_view_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledger_defines')->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->viewAny($user));
    }

    public function test_view_any_returns_false_for_user_without_view_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->viewAny($user));
    }

    public function test_view_returns_true_for_user_with_view_ledger_defines_permission_and_readable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledger_defines')->andReturn(true);
        // isReadableFolderForUser メソッドのモック設定を追加
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->view($user, $ledgerDefine));
    }

    public function test_view_returns_false_for_user_without_view_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->view($user, $ledgerDefine));
    }

    public function test_view_returns_false_for_user_when_folder_is_not_readable()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->view($user, $ledgerDefine));
    }

    public function test_create_returns_true_for_user_with_create_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledger_defines')->andReturn(true);
        // モックの設定: isWritableFolderForUser が true を返すようにする
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        // テストメソッドの引数に $folder を追加
        $this->assertTrue($policy->create($user, $folder));
    }

    public function test_create_returns_false_for_user_without_create_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create(); // $folderの定義がなく、hasPermissionがfalseの場合にしか通らないので追加
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        // テストメソッドの引数に $folder を追加
        $this->assertFalse($policy->create($user, $folder));
    }

    public function test_create_returns_false_for_user_with_create_ledger_defines_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->create($user, $folder)); // $folder を渡すように修正
    }

    public function test_update_returns_true_for_user_with_manage_ledger_defines_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, ['manage_ledger_defines', 'update_ledger_defines'])->andReturn(true);
        $userServiceMock->expects()->isWritableFolderForUser($user, $ledgerDefine->folder)
            ->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act
        $result = $policy->update($user, $ledgerDefine);

        // Assert
        $this->assertTrue($result);
    }
    public function test_update_returns_false_for_user_without_manage_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, ['manage_ledger_defines', 'update_ledger_defines'])->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->update($user, $ledgerDefine));
    }

    public function test_update_returns_false_for_user_with_manage_ledger_defines_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, ['manage_ledger_defines', 'update_ledger_defines'])->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->update($user, $ledgerDefine));
    }

    public function test_delete_returns_true_for_user_with_delete_ledger_defines_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->delete($user, $ledgerDefine));
    }

    public function test_delete_returns_false_for_user_without_delete_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->delete($user, $ledgerDefine));
    }

    public function test_delete_returns_false_for_user_with_delete_ledger_defines_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->delete($user, $ledgerDefine));
    }

    public function test_restore_returns_true_for_user_with_restore_ledger_defines_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->restore($user, $ledgerDefine));
    }

    public function test_restore_returns_false_for_user_without_restore_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->restore($user, $ledgerDefine));
    }

    public function test_restore_returns_false_for_user_with_restore_ledger_defines_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->restore($user, $ledgerDefine));
    }

    public function test_forceDelete_returns_true_for_user_with_force_delete_ledger_defines_permission_and_Writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'force_delete_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->forceDelete($user, $ledgerDefine));
    }

    public function test_forceDelete_returns_false_for_user_without_force_delete_ledger_defines_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'force_delete_ledger_defines')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->forceDelete($user, $ledgerDefine));
    }

    public function test_forceDelete_returns_false_for_user_with_force_delete_ledger_defines_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'force_delete_ledger_defines')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->forceDelete($user, $ledgerDefine));
    }

    public function test_ledgerView_returns_true_for_user_with_view_ledgers_permission_and_readable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerView($user, $ledgerDefine));
    }

    public function test_ledgerView_returns_false_for_user_without_view_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerView($user, $ledgerDefine));
    }

    public function test_ledgerView_returns_false_for_user_with_view_ledgers_permission_but_not_readable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerView($user, $ledgerDefine));
    }

    public function test_ledgerCreate_returns_true_for_user_with_create_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerCreate($user, $ledgerDefine));
    }

    public function test_ledgerCreate_returns_false_for_user_without_create_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerCreate($user, $ledgerDefine));
    }

    public function test_ledgerCreate_returns_false_for_user_with_create_ledgers_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'create_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerCreate($user, $ledgerDefine));
    }

    public function test_ledgerUpdate_returns_true_for_user_with_update_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'update_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerUpdate($user, $ledgerDefine));
    }

    public function test_ledgerUpdate_returns_false_for_user_without_update_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'update_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerUpdate($user, $ledgerDefine));
    }

    public function test_ledgerUpdate_returns_false_for_user_with_update_ledgers_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'update_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerUpdate($user, $ledgerDefine));
    }

    public function test_ledgerDelete_returns_true_for_user_with_delete_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerDelete($user, $ledgerDefine));
    }

    public function test_ledgerDelete_returns_false_for_user_without_delete_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerDelete($user, $ledgerDefine));
    }

    public function test_ledgerDelete_returns_false_for_user_with_delete_ledgers_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerDelete($user, $ledgerDefine));
    }

    public function test_ledgerRestore_returns_true_for_user_with_restore_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerRestore($user, $ledgerDefine));
    }

    public function test_ledgerRestore_returns_false_for_user_without_restore_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerRestore($user, $ledgerDefine));
    }

    public function test_ledgerRestore_returns_false_for_user_with_restore_ledgers_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'restore_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerRestore($user, $ledgerDefine));
    }

    public function test_ledgerForceDelete_returns_true_for_user_with_delete_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(true);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertTrue($policy->ledgerForceDelete($user, $ledgerDefine));
    }

    public function test_ledgerForceDelete_returns_false_for_user_without_delete_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerForceDelete($user, $ledgerDefine));
    }

    public function test_ledgerForceDelete_returns_false_for_user_with_delete_ledgers_permission_but_not_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'delete_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $ledgerDefine->folder)->andReturn(false);
        $policy = new LedgerDefinePolicy($userServiceMock);

        // Act & Assert
        $this->assertFalse($policy->ledgerForceDelete($user, $ledgerDefine));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

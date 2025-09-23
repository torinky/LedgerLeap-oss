<?php

namespace Tests\Unit\Policies;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Policies\LedgerDefinePolicy;
use App\Policies\LedgerPolicy;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LedgerPolicyTest extends TestCase
{
    use RefreshDatabase;
    protected bool $tenancy = true;

    public function test_view_any_returns_true_for_user_with_view_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(true);

        // LedgerDefinePolicy のモックを作成
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);

        // LedgerPolicy のインスタンスを作成し、モックをコンストラクタインジェクション
        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertTrue($policy->viewAny($user));
    }
    public function test_view_any_returns_false_for_user_without_view_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(false);
        // LedgerDefinePolicy のモックを作成
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);

        // LedgerPolicy のインスタンスを作成し、モックをコンストラクタインジェクション
        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertFalse($policy->viewAny($user));
    }

    public function test_view_returns_true_for_user_with_view_ledgers_permission_and_readable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledger->define->folder)->andReturn(true);

        // LedgerDefinePolicy のモックを作成
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        // ledgerView メソッドのモック設定を修正：$ledger->define を使う
        $ledgerDefinePolicyMock->shouldReceive('ledgerView')->with($user, $ledger->define)->andReturn(true);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertTrue($policy->view($user, $ledger));
    }
    public function test_view_returns_false_for_user_when_folder_is_not_readable()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $userServiceMock->shouldReceive('hasPermission')->with($user, 'view_ledgers')->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $ledger->define->folder)->andReturn(false);
        // ledgerView メソッドのモック設定を追加
        $ledgerDefinePolicyMock->shouldReceive('ledgerView')->with($user, $ledger->define)->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertFalse($policy->view($user, $ledger));
    }

    public function test_create_returns_true_for_user_with_create_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerCreate')->with($user, $ledgerDefine)->andReturn(true);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertTrue($policy->create($user, $ledgerDefine));
    }

    public function test_create_returns_false_for_user_without_create_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerCreate')->with($user, $ledgerDefine)->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertFalse($policy->create($user, $ledgerDefine));
    }

    public function test_update_returns_true_for_user_with_update_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerUpdate')->with($user, $ledger->define)->andReturn(true);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertTrue($policy->update($user, $ledger));
    }

    public function test_update_returns_false_for_user_without_update_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerUpdate')->with($user, $ledger->define)->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertFalse($policy->update($user, $ledger));
    }

    public function test_delete_returns_true_for_user_with_delete_ledgers_permission_and_writable_folder()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerDelete')->with($user, $ledger->define)->andReturn(true);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertTrue($policy->delete($user, $ledger));
    }

    public function test_delete_returns_false_for_user_without_delete_ledgers_permission()
    {
        // Arrange
        $user = User::factory()->create();
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $ledgerDefinePolicyMock = Mockery::mock(LedgerDefinePolicy::class);
        $ledgerDefinePolicyMock->shouldReceive('ledgerDelete')->with($user, $ledger->define)->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock, $ledgerDefinePolicyMock);

        // Act & Assert
        $this->assertFalse($policy->delete($user, $ledger));
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

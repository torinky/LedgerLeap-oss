<?php

namespace Tests\Unit\Policies;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\User;
use App\Policies\LedgerPolicy;
use App\Repositories\WritableFolderRepository;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LedgerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // 各テストメソッドの前にデータベースを初期化
        $this->seed(RolesAndPermissionsSeeder::class);

        // Spatieのキャッシュクリア
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_viewAny_returns_true_for_user_with_view_ledgers_permission()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_ledgers');

        $userServiceMock = Mockery::mock(UserService::class);
        $policy = new LedgerPolicy($userServiceMock);

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_viewAny_returns_false_for_user_without_view_ledgers_permission()
    {
        $user = User::factory()->create();

        $userServiceMock = Mockery::mock(UserService::class);
        $policy = new LedgerPolicy($userServiceMock);

        $this->assertFalse($policy->viewAny($user));
    }

    public function test_view_returns_true_for_user_with_view_ledgers_permission_and_readable_folder()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_ledgers');
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->andReturn(true);

        $policy = new LedgerPolicy($userServiceMock);
        $this->assertTrue($policy->view($user, $ledger));
    }

    public function test_view_returns_false_for_user_when_folder_is_not_readable()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock);

        $this->assertFalse($policy->view($user, $ledger));
    }

    public function test_view_returns_false_for_user_with_view_ledgers_permission_but_not_readable_folder()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_ledgers');

        // ユーザーにロールを割り当て、ロールにフォルダを関連付ける
        $role = Role::create(['name' => 'test-role']);
        $user->assignRole($role);
        $folder = Folder::factory()->create();
        $role->readableFolders()->attach($folder, ['permission' => 'write', 'modifier_id' => $user->id]);

        // Spatieのキャッシュクリア
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

        // UserService のモックを作成
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('isReadableFolderForUser')
            ->with($user, $ledger->define->folder)
            ->andReturn(false);

        $policy = new LedgerPolicy($userServiceMock);

        $this->assertFalse($policy->view($user, $ledger));
    }

    public function test_view_returns_false_when_ledger_define_is_null()
    {
        $user = User::factory()->create();
        $ledger = Ledger::factory()->create(); // LedgerDefine は関連付けない

        $userServiceMock = Mockery::mock(UserService::class);
        $policy = new LedgerPolicy($userServiceMock);

        $this->assertFalse($policy->view($user, $ledger));
    }

    public function test_create_returns_true_for_user_with_create_ledgers_permission_and_writable_folder()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create_ledgers');
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // UserService のモックを作成し、isWritableFolderForUser および isReadableFolderForUser メソッドが true を返すように設定
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $folder)->andReturn(true);
        $userServiceMock->shouldReceive('isReadableFolderForUser')->with($user, $folder)->andReturn(true);

        // LedgerPolicy のインスタンスを作成し、モックをコンストラクタインジェクション
        $policy = new LedgerPolicy($userServiceMock);

        $this->assertTrue($policy->create($user, $folder));
    }

    public function test_create_returns_false_for_user_without_create_ledgers_permission()
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create();

        // UserService のモックを作成
        $userServiceMock = Mockery::mock(UserService::class);

        // LedgerPolicy のインスタンスを作成し、モックをコンストラクタインジェクション
        $policy = new LedgerPolicy($userServiceMock);
        $this->assertFalse($policy->create($user, $folder));
    }

    public function test_create_returns_false_for_user_with_create_ledgers_permission_but_not_writable_folder()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create_ledgers');
        $folder = Folder::factory()->create();

        // UserService のモックを作成し、isWritableFolderForUser メソッドが false を返すように設定
        $userServiceMock = Mockery::mock(UserService::class);
        $userServiceMock->shouldReceive('isWritableFolderForUser')->with($user, $folder)->andReturn(false);

        // LedgerPolicy のインスタンスを作成し、モックをコンストラクタインジェクション
        $policy = new LedgerPolicy($userServiceMock);
        $this->assertFalse($policy->create($user, $folder));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Unit\Mcp\Traits;

use App\Mcp\Traits\AuthenticatedMcpTool;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Response;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AuthenticatedMcpTraitの詳細テスト
 *
 * 注意: このテストクラスは共通トレイトの内部ロジックをテストします。
 * 実際のMCPツールの統合テストはMcpToolsAuthenticationTest.phpで実施されます。
 */
class AuthenticatedMcpToolTest extends TestCase
{
    use RefreshDatabase;

    // テスト用のクラスを作成
    private object $testClass;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        // ユーザー作成とトークン生成
        $this->user = User::factory()->create();
        $tokenResult = $this->user->createToken('test-token');
        $this->validToken = $tokenResult->plainTextToken;

        // トレイトを使用するテストクラスを作成
        $this->testClass = new class
        {
            use AuthenticatedMcpTool;

            public function callAuthenticateUser()
            {
                return $this->authenticateUser();
            }

            public function callCheckFolderPermission($user, $folder, $permission)
            {
                return $this->checkFolderPermission($user, $folder, $permission);
            }

            public function callAuthenticateOrError()
            {
                return $this->authenticateOrError();
            }

            public function callCheckFolderPermissionOrError($user, $folder, $permission)
            {
                return $this->checkFolderPermissionOrError($user, $folder, $permission);
            }

            public function callAuthenticationError($message = 'Authentication failed')
            {
                return $this->authenticationError($message);
            }

            public function callPermissionError($message = 'Permission denied')
            {
                return $this->permissionError($message);
            }
        };
    }

    #[Test]
    public function authenticate_user_returns_user_with_valid_token(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $user = $this->testClass->callAuthenticateUser();

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);
    }

    #[Test]
    public function authenticate_user_throws_exception_with_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MCP_AUTH_TOKEN environment variable is not set');

        $this->testClass->callAuthenticateUser();
    }

    #[Test]
    public function authenticate_user_throws_exception_with_invalid_token(): void
    {
        putenv('MCP_AUTH_TOKEN=invalid-token-12345');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The provided token is invalid or has been revoked');

        $this->testClass->callAuthenticateUser();
    }

    #[Test]
    public function check_folder_permission_returns_true_with_access(): void
    {
        $folder = Folder::factory()->create();

        // WritableFolderRepositoryをモック（権限あり）
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with($this->user, \App\Enums\FolderPermissionType::READ)
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $result = $this->testClass->callCheckFolderPermission($this->user, $folder, 'READ');

        $this->assertTrue($result);
    }

    #[Test]
    public function check_folder_permission_returns_false_without_access(): void
    {
        $folder = Folder::factory()->create();

        // WritableFolderRepositoryをモック（権限なし）
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with($this->user, \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([]); // 空の配列 = 権限なし

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $result = $this->testClass->callCheckFolderPermission($this->user, $folder, 'WRITE');

        $this->assertFalse($result);
    }

    #[Test]
    public function check_folder_permission_handles_admin_permission(): void
    {
        $folder = Folder::factory()->create();

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with($this->user, \App\Enums\FolderPermissionType::ADMIN)
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $result = $this->testClass->callCheckFolderPermission($this->user, $folder, 'ADMIN');

        $this->assertTrue($result);
    }

    #[Test]
    public function check_folder_permission_throws_exception_with_invalid_permission(): void
    {
        $folder = Folder::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid permission: INVALID');

        $this->testClass->callCheckFolderPermission($this->user, $folder, 'INVALID');
    }

    #[Test]
    public function authenticate_or_error_returns_user_on_success(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $result = $this->testClass->callAuthenticateOrError();

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($this->user->id, $result->id);
    }

    #[Test]
    public function authenticate_or_error_returns_error_response_on_failure(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $result = $this->testClass->callAuthenticateOrError();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('MCP_AUTH_TOKEN environment variable is not set', $result->content());
    }

    #[Test]
    public function check_folder_permission_or_error_returns_true_with_permission(): void
    {
        $folder = Folder::factory()->create(['title' => 'Test Folder']);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with($this->user, \App\Enums\FolderPermissionType::READ)
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $result = $this->testClass->callCheckFolderPermissionOrError($this->user, $folder, 'READ');

        $this->assertTrue($result);
    }

    #[Test]
    public function check_folder_permission_or_error_returns_error_response_without_permission(): void
    {
        $folder = Folder::factory()->create(['title' => 'Test Folder']);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with($this->user, \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $result = $this->testClass->callCheckFolderPermissionOrError($this->user, $folder, 'WRITE');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('Insufficient permission (WRITE) for folder: Test Folder', $result->content());
    }

    #[Test]
    public function authentication_error_returns_error_response(): void
    {
        $result = $this->testClass->callAuthenticationError('Custom auth error');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertEquals('Custom auth error', $result->content());
    }

    #[Test]
    public function permission_error_returns_error_response(): void
    {
        $result = $this->testClass->callPermissionError('Custom permission error');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertEquals('Custom permission error', $result->content());
    }

    #[Test]
    public function authentication_error_uses_default_message(): void
    {
        $result = $this->testClass->callAuthenticationError();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertEquals('Authentication failed', $result->content());
    }

    #[Test]
    public function permission_error_uses_default_message(): void
    {
        $result = $this->testClass->callPermissionError();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->isError());
        $this->assertEquals('Permission denied', $result->content());
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature\Search;

use App\Enums\FolderPermissionType;
use App\Http\Controllers\Api\V1\SearchController;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tests\Traits\DatabaseMigrationsOnce;

/**
 * Api/V1/SearchController の追加テスト
 *
 * 既存の SearchApiTest が権限フィルタ・pagination・tags 等を網羅しているため、
 * このテストでは未カバー領域（デバッグモード・例外処理・POST メソッド）を対象とする。
 *
 * DatabaseMigrationsOnce を使用: migrate:fresh をクラスで1回だけ実行する。
 */
#[CoversClass(SearchController::class)]
#[Group('database-migrations')]
class SearchControllerAdditionalTest extends TestCase
{
    use DatabaseMigrationsOnce;

    protected bool $tenancy = true;

    protected User $adminUser;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefine;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->setUpDatabaseMigrationsOnce();

        $this->tenant = static::$sharedTenantForMigrationsOnce;

        // 権限・ロール設定
        $permission = Permission::firstOrCreate(['name' => 'view_ledgers', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin_search_test', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permission);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);

        // フォルダ・台帳定義・フォルダ権限
        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
        ]);
        RoleFolderPermission::create([
            'role_id' => $adminRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::ADMIN,
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        $this->token = $this->adminUser->createToken('test')->plainTextToken;

        // ドメイン設定
        config(['tenancy.central_domains' => ['127.0.0.1']]);
        if (! $this->tenant->domains()->where('domain', 'localhost')->exists()) {
            $this->tenant->domains()->create(['domain' => 'localhost']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce();
        parent::tearDown();
    }

    // ================================================================
    // POST /api/v1/search — 日本語キーワードを POST で送信
    // ================================================================

    #[Test]
    public function post_search_with_japanese_keyword_returns_200(): void
    {
        $this->postJson('/api/v1/search', ['q' => '日本語テスト'], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['total', 'limit', 'offset']]);
    }

    // ================================================================
    // GET /api/v1/search — count モードで meta.total のみ返す
    // ================================================================

    #[Test]
    public function get_search_with_count_mode_returns_only_meta_total(): void
    {
        $this->getJson('/api/v1/search?mode=count', [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonStructure(['meta' => ['total']])
            ->assertJsonMissing(['data']);  // count モードは data を返さない
    }

    // ================================================================
    // POST /api/v1/search — count モード
    // ================================================================

    #[Test]
    public function post_search_with_count_mode_returns_only_meta_total(): void
    {
        $this->postJson('/api/v1/search', ['mode' => 'count'], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonStructure(['meta' => ['total']])
            ->assertJsonMissing(['data']);
    }

    // ================================================================
    // デバッグモード ON でもレスポンスは正常（ログが出ても 200 を返す）
    // ================================================================

    #[Test]
    public function search_with_debug_mode_enabled_returns_200(): void
    {
        config(['app.debug' => true]);

        $this->postJson('/api/v1/search', ['q' => 'debug test'], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);

        config(['app.debug' => false]);
    }

    // ================================================================
    // デバッグモード ON で count モード
    // ================================================================

    #[Test]
    public function search_count_mode_with_debug_enabled_returns_200(): void
    {
        config(['app.debug' => true]);

        $this->postJson('/api/v1/search', ['mode' => 'count'], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonStructure(['meta' => ['total']]);

        config(['app.debug' => false]);
    }

    // ================================================================
    // 例外が発生した場合に rethrow される（500 を返す）
    // ================================================================

    #[Test]
    public function search_rethrows_exception_on_service_error(): void
    {
        // LedgerService をモックして例外を throw させる
        $this->mock(LedgerService::class, function ($mock) {
            $mock->shouldReceive('searchLedgersForApi')
                ->andThrow(new \RuntimeException('Simulated service error'));
        });

        $this->withoutExceptionHandling();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated service error');

        $this->postJson('/api/v1/search', ['q' => 'test'], [
            'Authorization' => 'Bearer '.$this->token,
        ]);
    }

    // ================================================================
    // 未認証では 401
    // ================================================================

    #[Test]
    public function search_without_auth_returns_401(): void
    {
        $this->postJson('/api/v1/search', ['q' => 'test'])
            ->assertStatus(401);
    }

    // ================================================================
    // limit / offset パラメータが meta に反映される
    // ================================================================

    #[Test]
    public function search_meta_reflects_limit_and_offset_params(): void
    {
        $this->postJson('/api/v1/search', ['limit' => 5, 'offset' => 10], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertStatus(200)
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.offset', 10);
    }
}

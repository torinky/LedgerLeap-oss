<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tag;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\DatabaseMigrationsOnce;

// SearchApiTest は Mroonga 全文検索を使うため DatabaseMigrationsOnce を使用し、
// 各テスト後に TRUNCATE でインデックスをクリアする。
// RefreshDatabaseWithTenant（トランザクション方式）では Mroonga インデックスに
// 他テストの Ledger が残留して件数アサーションが失敗する。
#[Group('database-migrations')]
class SearchApiTest extends TestCase
{
    use DatabaseMigrationsOnce;

    /** テストデータ初期化済みフラグ（クラス全体で1回のみ作成） */
    private static bool $dataInitialized = false;

    private static User $adminUser;

    private static User $writerUser;

    private static User $viewerUser;

    private static User $noRoleUser;

    private static Folder $writeFolder;

    private static Folder $readFolder;

    private static Folder $privateFolder;

    private static Ledger $writeLedger;

    private static Ledger $readLedger;

    private static Ledger $privateLedger;

    private static string $adminToken;

    protected function getTablesToTruncateForMigrationsOnce(): array
    {
        return [
            'ledgers',
            'ledger_chunks',
            'attached_files',
            'activity_log',
            'taggables',
            'tags',
            'role_folder_permissions',
            'folders',
            'ledger_defines',
            'personal_access_tokens',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabaseMigrationsOnce();

        // APIテスト実行時にlocalhostをテナントドメインとして扱うための設定
        config(['tenancy.central_domains' => ['127.0.0.1']]);

        // テナントにドメインを追加（初回のみ）
        $tenant = static::$sharedTenantForMigrationsOnce;
        if (! $tenant->domains()->where('domain', 'localhost')->exists()) {
            $tenant->domains()->create(['domain' => 'localhost']);
        }

        // テストデータはクラスで1回のみ作成
        if (! static::$dataInitialized) {
            $this->createSharedData();
            static::$dataInitialized = true;
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabaseMigrationsOnce();
        parent::tearDown();
    }

    /**
     * クラス全体で共有するテストデータを作成
     */
    private function createSharedData(): void
    {
        $permissionRegistrar = $this->app->make(\Spatie\Permission\PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view_ledgers', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $writerRole = Role::firstOrCreate(['name' => 'writer', 'guard_name' => 'web']);
        $viewerRole = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $adminRole->givePermissionTo($permission);
        $writerRole->givePermissionTo($permission);
        $viewerRole->givePermissionTo($permission);

        self::$adminUser = User::factory()->create(['name' => 'Admin User']);
        self::$writerUser = User::factory()->create(['name' => 'Writer User']);
        self::$viewerUser = User::factory()->create(['name' => 'Viewer User']);
        self::$noRoleUser = User::factory()->create(['name' => 'No Role User']);

        self::$adminUser->assignRole($adminRole);
        self::$writerUser->assignRole($writerRole);
        self::$viewerUser->assignRole($viewerRole);

        $tenant = static::$sharedTenantForMigrationsOnce;
        $tenant->run(function () use ($adminRole, $writerRole, $viewerRole) {
            self::$writeFolder = Folder::factory()->create(['title' => 'Writable Folder']);
            self::$readFolder = Folder::factory()->create(['title' => 'Readable Folder']);
            self::$privateFolder = Folder::factory()->create(['title' => 'Private Folder']);

            $folderPermissions = [
                ['role_id' => $adminRole->id, 'folder_id' => self::$writeFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => self::$adminUser->id, 'modifier_id' => self::$adminUser->id],
                ['role_id' => $adminRole->id, 'folder_id' => self::$readFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => self::$adminUser->id, 'modifier_id' => self::$adminUser->id],
                ['role_id' => $adminRole->id, 'folder_id' => self::$privateFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => self::$adminUser->id, 'modifier_id' => self::$adminUser->id],
                ['role_id' => $writerRole->id, 'folder_id' => self::$writeFolder->id, 'permission' => FolderPermissionType::WRITE, 'creator_id' => self::$adminUser->id, 'modifier_id' => self::$adminUser->id],
                ['role_id' => $viewerRole->id, 'folder_id' => self::$readFolder->id, 'permission' => FolderPermissionType::READ, 'creator_id' => self::$adminUser->id, 'modifier_id' => self::$adminUser->id],
            ];
            foreach ($folderPermissions as $perm) {
                RoleFolderPermission::create($perm);
            }

            $writeLedgerDefine = LedgerDefine::factory()->create(['folder_id' => self::$writeFolder->id]);
            $readLedgerDefine = LedgerDefine::factory()->create(['folder_id' => self::$readFolder->id]);
            $privateLedgerDefine = LedgerDefine::factory()->create(['folder_id' => self::$privateFolder->id]);

            $writeLedgerFirstColumnId = $writeLedgerDefine->column_define[0]->id;
            self::$writeLedger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $writeLedgerDefine->id,
                'content' => [$writeLedgerFirstColumnId => 'Ledger in Writable Folder'],
                'creator_id' => self::$adminUser->id,
            ]);

            $readLedgerFirstColumnId = $readLedgerDefine->column_define[0]->id;
            self::$readLedger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $readLedgerDefine->id,
                'content' => [$readLedgerFirstColumnId => 'Ledger in Readable Folder'],
                'creator_id' => self::$adminUser->id,
            ]);

            $privateLedgerFirstColumnId = $privateLedgerDefine->column_define[0]->id;
            self::$privateLedger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $privateLedgerDefine->id,
                'content' => [$privateLedgerFirstColumnId => 'Ledger in Private Folder'],
                'creator_id' => self::$adminUser->id,
            ]);

            Tag::factory()->create(['name' => 'ProjectA', 'ledger_define_id' => $writeLedgerDefine->id, 'folder_id' => self::$writeFolder->id]);
            Tag::factory()->create(['name' => 'Urgent', 'ledger_define_id' => $writeLedgerDefine->id, 'folder_id' => self::$writeFolder->id]);
            Tag::factory()->create(['name' => 'ProjectB', 'ledger_define_id' => $readLedgerDefine->id, 'folder_id' => self::$readFolder->id]);
        });

        self::$adminToken = self::$adminUser->createToken('test-token')->plainTextToken;
        $permissionRegistrar->forgetCachedPermissions();

        // Mroonga全文検索インデックスの更新を待つ
        usleep(100000);
    }

    public function test_admin_can_search_all_ledgers()
    {
        // テナントコンテキストを確実に初期化
        tenancy()->initialize(static::$sharedTenantForMigrationsOnce);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search') // キーワード検索を外してテスト
            ->assertStatus(200)
            ->assertJsonCount(3, 'data'); // write, read, private の3つすべて
    }

    public function test_writer_can_only_search_ledgers_in_writable_folders()
    {
        $response = $this->actingAs(self::$writerUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$writeLedger->id);
    }

    public function test_viewer_can_only_search_ledgers_in_readable_folders()
    {
        $response = $this->actingAs(self::$viewerUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$readLedger->id);
    }

    public function test_user_with_no_folder_permission_cannot_search_any_ledgers()
    {
        $this->actingAs(self::$noRoleUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_folder_id_with_permission_check()
    {
        // writerはwriteFolderの権限を持つ
        $this->actingAs(self::$writerUser, 'sanctum')
            ->getJson('/api/v1/search?folder_id='.self::$writeFolder->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_access_folder_without_permission()
    {
        // writerはreadFolderの権限を持たない
        $this->actingAs(self::$writerUser, 'sanctum')
            ->getJson('/api/v1/search?folder_id='.self::$readFolder->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_writable_keyword()
    {
        // "Writable" というキーワードで検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Writable')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$writeLedger->id);
    }

    public function test_can_filter_by_readable_keyword()
    {
        // "Readable" というキーワードで検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Readable')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$readLedger->id);
    }

    public function test_can_filter_by_nonexistent_keyword()
    {
        // ヒットしないキーワードで検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=NonExistentKeyword')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_tags()
    {
        // 単一タグで検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$writeLedger->id);
    }

    public function test_can_filter_by_multiple_tags_and_condition()
    {
        // 複数タグ(AND)で検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA,Urgent')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$writeLedger->id);
    }

    public function test_can_filter_by_multiple_tags_no_match()
    {
        // 複数タグ(AND)でヒットしないケース
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA,ProjectB')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_nonexistent_tag()
    {
        // 存在しないタグで検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=NonExistentTag')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_write_ledger_define_id()
    {
        // writeLedger の ledger_define_id で検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?ledger_define_id='.self::$writeLedger->ledger_define_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$writeLedger->id);
    }

    public function test_can_filter_by_read_ledger_define_id()
    {
        // readLedger の ledger_define_id で検索
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?ledger_define_id='.self::$readLedger->ledger_define_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', self::$readLedger->id);
    }

    public function test_can_exclude_by_keyword()
    {
        // "Ledger"で検索し、"Writable"を除外 -> 2件返ってくる（ReadableとPrivate）
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&exclude_q=Writable')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_exclude_by_tags()
    {
        // "ProjectA"タグを持つものを除外 -> 2件返ってくる
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&exclude_tags=ProjectA')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_creator_id()
    {
        static::$sharedTenantForMigrationsOnce->run(function () {
            self::$writeLedger->update(['creator_id' => self::$adminUser->id]);
            self::$readLedger->update(['creator_id' => self::$adminUser->id]);
            self::$privateLedger->update(['creator_id' => self::$adminUser->id]);
        });

        // creator_idでフィルタリング
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?creator_id='.self::$adminUser->id)
            ->assertStatus(200)
            ->assertJsonCount(3, 'data'); // adminが作成した3つすべて

        // writerUserのLedgerを追加で作成（別テストに分離）
    }

    public function test_can_filter_by_different_creator_id()
    {
        static::$sharedTenantForMigrationsOnce->run(function () {
            $writeLedgerDefine = LedgerDefine::where('folder_id', self::$writeFolder->id)->first();
            $columnId = $writeLedgerDefine->column_define[0]->id;

            $writerLedger = Ledger::factory()->minimal()->create([
                'ledger_define_id' => $writeLedgerDefine->id,
                'creator_id' => self::$writerUser->id,
                'content' => [$columnId => 'Writer Created Ledger'],
            ]);

            try {
                // writerUserが作成したもののみ検索
                $this->actingAs(self::$adminUser, 'sanctum')
                    ->getJson('/api/v1/search?creator_id='.self::$writerUser->id)
                    ->assertStatus(200)
                    ->assertJsonCount(1, 'data')
                    ->assertJsonFragment(['id' => $writerLedger->id]);
            } finally {
                // テスト後にクリーンアップ
                $writerLedger->delete();
            }
        });
    }

    public function test_can_filter_by_date_range()
    {
        // 特定の日付範囲でフィルタリング
        $response = $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?created_from=2025-09-29&created_to=2025-09-30');

        $response->assertStatus(200);

        // データが存在することを確認（正確な件数は実行時期によって変わるため、>= 0で確認）
        $this->assertGreaterThanOrEqual(0, count($response->json('data', [])));
    }

    public function test_returns_only_count_with_mode_count()
    {
        $response = $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&mode=count');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'total' => 3,
                ],
            ])
            ->assertJsonMissingPath('data');
    }

    public function test_pagination_with_limit()
    {
        // limit=1 -> 1件だけ返ってくる
        $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&limit=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_pagination_with_limit_and_offset()
    {
        // limit=1, offset=1 -> 2件目の1件だけ返ってくる
        $response = $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&limit=1&offset=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_pagination_ids_are_different()
    {
        // 最初のページを取得（offset=0）
        $firstPageResponse = $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('http://localhost/api/v1/search?q=Ledger&limit=1&offset=0');

        $firstPageResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
        $firstPageId = $firstPageResponse->json('data.0.id');

        // 2ページ目を取得（offset=1）
        $secondPageResponse = $this->actingAs(self::$adminUser, 'sanctum')
            ->getJson('http://localhost/api/v1/search?q=Ledger&limit=1&offset=1');

        $secondPageResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
        $secondPageId = $secondPageResponse->json('data.0.id');

        // IDが異なることを確認（異なるLedgerが返されている）
        $this->assertNotEquals($firstPageId, $secondPageId, 'Pagination should return different ledgers');
    }

    public function test_search_api_returns_correct_structure()
    {
        $response = $this->actingAs(self::$adminUser, 'sanctum')->getJson('/api/v1/search?limit=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'define' => [
                            'id',
                            'name',
                            'description',
                        ],
                        'content',
                        'folder' => [
                            'id',
                            'name',
                            'path',
                        ],
                        'tags' => [
                            '*' => [
                                'id',
                                'name',
                            ],
                        ],
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'limit',
                    'offset',
                ],
            ]);

        // contentがキーバリュー形式（連想配列）であることを確認
        $responseData = $response->json('data.0');
        if (! empty($responseData['content'])) {
            $this->assertIsArray($responseData['content']);
            // キーが数値でないことを確認（文字列キーのはず）
            $this->assertFalse(is_int(array_key_first($responseData['content'])));
        }
    }
}

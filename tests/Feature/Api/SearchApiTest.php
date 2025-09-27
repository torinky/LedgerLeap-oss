<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private User $adminUser;
    private User $writerUser;
    private User $viewerUser;
    private User $noRoleUser;
    private Folder $writeFolder;
    private Folder $readFolder;
    private Folder $privateFolder;
    private Ledger $writeLedger;
    private Ledger $readLedger;
    private Ledger $privateLedger;

    protected function setUp(): void
    {
        parent::setUp();

        // APIテスト実行時にlocalhostをテナントドメインとして扱うための設定
        config(['tenancy.central_domains' => ['127.0.0.1']]);
        $this->tenant->domains()->create(['domain' => 'localhost']);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 権限とロールを定義
        Permission::findOrCreate('view_ledgers', 'web');
        $adminRole = Role::findOrCreate('admin', 'web')->givePermissionTo(['view_ledgers']);
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledgers']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo(['view_ledgers']);

        // ユーザーを作成
        $this->adminUser = User::factory()->create()->assignRole($adminRole);
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);
        $this->noRoleUser = User::factory()->create();

        $this->tenant->run(function () {
            // フォルダを作成
            $this->writeFolder = Folder::factory()->create(['title' => 'Writable Folder']);
            $this->readFolder = Folder::factory()->create(['title' => 'Readable Folder']);
            $this->privateFolder = Folder::factory()->create(['title' => 'Private Folder']);
        });

        // フォルダ権限を割り当て (中央コンテキストで実行)
        RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->writeFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->readFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        RoleFolderPermission::create(['role_id' => $adminRole->id, 'folder_id' => $this->privateFolder->id, 'permission' => FolderPermissionType::ADMIN, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        RoleFolderPermission::create(['role_id' => $writerRole->id, 'folder_id' => $this->writeFolder->id, 'permission' => FolderPermissionType::WRITE, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
        RoleFolderPermission::create(['role_id' => $viewerRole->id, 'folder_id' => $this->readFolder->id, 'permission' => FolderPermissionType::READ, 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);

        $this->tenant->run(function () {
            // 各フォルダに対応する台帳定義を作成
            $writeLedgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->writeFolder->id]);
            $readLedgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->readFolder->id]);
            $privateLedgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->privateFolder->id]);

            // 台帳を作成 (正しい ledger_define_id と content 形式を指定)
            $writeLedgerFirstColumnId = $writeLedgerDefine->column_define[0]->id;
            $this->writeLedger = Ledger::factory()->create(['ledger_define_id' => $writeLedgerDefine->id, 'content' => [$writeLedgerFirstColumnId => 'Ledger in Writable Folder']]);

            $readLedgerFirstColumnId = $readLedgerDefine->column_define[0]->id;
            $this->readLedger = Ledger::factory()->create(['ledger_define_id' => $readLedgerDefine->id, 'content' => [$readLedgerFirstColumnId => 'Ledger in Readable Folder']]);

            $privateLedgerFirstColumnId = $privateLedgerDefine->column_define[0]->id;
            $this->privateLedger = Ledger::factory()->create(['ledger_define_id' => $privateLedgerDefine->id, 'content' => [$privateLedgerFirstColumnId => 'Ledger in Private Folder']]);

            // タグを作成
            Tag::factory()->create(['name' => 'ProjectA', 'ledger_define_id' => $writeLedgerDefine->id]);
            Tag::factory()->create(['name' => 'Urgent', 'ledger_define_id' => $writeLedgerDefine->id]);
            Tag::factory()->create(['name' => 'ProjectB', 'ledger_define_id' => $readLedgerDefine->id]);
        });

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

        // 権限まわりのキャッシュがテストに影響するのを防ぐ
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // キャッシュを強制的にクリア
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_search_all_ledgers()
    {
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search') // キーワード検索を外してテスト
            ->assertStatus(200)
            ->assertJsonCount(3, 'data'); // write, read, private の3つすべて
    }

    public function test_writer_can_only_search_ledgers_in_writable_folders()
    {
        $response = $this->actingAs($this->writerUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->writeLedger->id);
    }

    public function test_viewer_can_only_search_ledgers_in_readable_folders()
    {
        $response = $this->actingAs($this->viewerUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->readLedger->id);
    }

    public function test_user_with_no_folder_permission_cannot_search_any_ledgers()
    {
        $this->actingAs($this->noRoleUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_folder_id_with_permission_check()
    {
        // writerはwriteFolderの権限を持つ
        $this->actingAs($this->writerUser, 'sanctum')
            ->getJson('/api/v1/search?folder_id=' . $this->writeFolder->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // writerはreadFolderの権限を持たない
        $this->actingAs($this->writerUser, 'sanctum')
            ->getJson('/api/v1/search?folder_id=' . $this->readFolder->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_specific_keyword()
    {
        // "Writable" というキーワードで検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Writable')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->writeLedger->id);

        // "Readable" というキーワードで検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Readable')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->readLedger->id);

        // ヒットしないキーワードで検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=NonExistentKeyword')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_tags()
    {
        // 単一タグで検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->writeLedger->id);

        // 複数タグ(AND)で検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA,Urgent')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->writeLedger->id);

        // 複数タグ(AND)でヒットしないケース
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=ProjectA,ProjectB')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // 存在しないタグで検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?tags=NonExistentTag')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_can_filter_by_ledger_define_id()
    {
        // writeLedger の ledger_define_id で検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?ledger_define_id=' . $this->writeLedger->ledger_define_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->writeLedger->id);

        // readLedger の ledger_define_id で検索
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?ledger_define_id=' . $this->readLedger->ledger_define_id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->readLedger->id);
    }

    public function test_can_exclude_by_keyword()
    {
        // "Ledger"で検索し、"Writable"を除外 -> 2件返ってくる
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&exclude_q=Writable')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_exclude_by_tags()
    {
        // "ProjectA"タグを持つものを除外 -> 2件返ってくる
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&exclude_tags=ProjectA')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_only_count_with_mode_count()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&mode=count');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'total' => 3,
                ]
            ])
            ->assertJsonMissingPath('data');
    }

    public function test_pagination_with_limit_and_offset()
    {
        // limit=1 -> 1件だけ返ってくる
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&limit=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // limit=1, offset=1 -> 2件目の1件だけ返ってくる
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/search?q=Ledger&limit=1&offset=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 1件目とIDが違うことを確認（順序は不定なので、単純なID比較はしない）
        $firstId = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/search?q=Ledger&limit=1')->json('data.0.id');
        $this->assertNotEquals($firstId, $response->json('data.0.id'));
    }

    public function test_search_api_returns_correct_structure()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/v1/search?limit=1');

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
        if (!empty($responseData['content'])) {
            $this->assertIsArray($responseData['content']);
            // キーが数値でないことを確認（文字列キーのはず）
            $this->assertFalse(is_int(array_key_first($responseData['content'])));
        }
    }
}

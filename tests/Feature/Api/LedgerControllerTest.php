<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Skip; // 追加

class LedgerControllerTest extends TestCase
{
    use DatabaseMigrations;

    protected bool $tenancy = true;

    private User $writerUser;
    private User $viewerUser;
    private Folder $writeFolder;
    private LedgerDefine $ledgerDefine;
    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを作成
        $this->tenant = \App\Models\Tenant::create(['id' => 'test_tenant']);
        $this->tenant->domains()->create(['domain' => 'localhost']);
        tenancy()->initialize($this->tenant);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 権限とロールを定義
        Permission::findOrCreate('view_ledgers', 'web');
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledgers']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo(['view_ledgers']);

        // ユーザーを作成
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);

        // フォルダを作成
        $this->writeFolder = Folder::factory()->create(['title' => 'Writable Folder']);

        // フォルダ権限を割り当て
        RoleFolderPermission::create([
            'role_id' => $writerRole->id,
            'folder_id' => $this->writeFolder->id,
            'permission' => FolderPermissionType::WRITE,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id
        ]);

        // 台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->writeFolder->id]);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function unauthenticated_user_cannot_create_ledger()
    {
        $response = $this->postJson(route('api.v1.ledgers.store'), []);
        $response->assertUnauthorized();
    }

    #[Test]
    public function it_returns_validation_error_if_required_fields_are_missing()
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $response = $this->postJson(route('api.v1.ledgers.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ledger_define_id', 'folder_id', 'content']);
    }

    #[Test]
    public function user_without_write_permission_cannot_create_ledger()
    {
        // viewerUserはwriteFolderへの書き込み権限を持たない
        $this->actingAs($this->viewerUser, 'sanctum');

        $columnId = $this->ledgerDefine->column_define[0]->id;
        $data = [
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->writeFolder->id,
            'content' => [$columnId => 'Test Content'],
        ];

        $response = $this->postJson(route('api.v1.ledgers.store'), $data);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_write_permission_can_create_ledger_with_tags()
    {
        $this->actingAs($this->writerUser, 'sanctum');

        // 実際のカラムIDを取得してテストデータを作成
        $columnId1 = $this->ledgerDefine->column_define[0]->id;
        $columnId2 = $this->ledgerDefine->column_define[1]->id;

        $data = [
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->writeFolder->id,
            'content' => [$columnId1 => 'Test Content', $columnId2 => 'Another Content'],
            'tags' => ['new-tag', 'another-tag'],
        ];

        $response = $this->postJson(route('api.v1.ledgers.store'), $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
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
            ]);

        // contentがキーバリュー形式（連想配列）であることを確認
        $responseData = $response->json('data');
        if (!empty($responseData['content'])) {
            $this->assertIsArray($responseData['content']);
            // キーが数値でないことを確認（文字列キーのはず）
            $this->assertFalse(is_int(array_key_first($responseData['content'])));
        }

        $this->assertDatabaseHas('ledgers', [
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        // Tagの作成はLedgerService内で行われるため、ここではDBに存在することを確認
        $this->assertDatabaseHas('tags', ['name' => 'new-tag', 'ledger_define_id' => $this->ledgerDefine->id]);
        $this->assertDatabaseHas('tags', ['name' => 'another-tag', 'ledger_define_id' => $this->ledgerDefine->id]);
    }

    #[Test]
    public function it_can_filter_ledgers_by_creator_id()
    {
        $this->tenant->run(function () {
            $this->actingAs($this->writerUser, 'sanctum');

            // 別のユーザーを作成
            $anotherUser = User::factory()->create();

            // 正しいカラムIDを取得
            $columnId = $this->ledgerDefine->column_define[0]->id;

            // writerUserが作成した台帳
            $ledgerByWriter = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->writerUser->id,
                'content' => [$columnId => 'Writer Content'],
            ]);

            // anotherUserが作成した台帳
            $ledgerByAnother = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $anotherUser->id,
                'content' => [$columnId => 'Another User Content'],
            ]);

            // writerUserでフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', ['filter' => ['creator_id' => $this->writerUser->id]]));

            $response->assertOk()
                ->assertJsonCount(1, 'ledgers')
                ->assertJsonFragment(['id' => $ledgerByWriter->id]);

            // anotherUserでフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', ['filter' => ['creator_id' => $anotherUser->id]]));

            $response->assertOk()
                ->assertJsonCount(1, 'ledgers')
                ->assertJsonFragment(['id' => $ledgerByAnother->id]);
        });
    }

    #[Test]
    public function it_can_filter_ledgers_by_created_between()
    {
        $this->tenant->run(function () {
            $this->actingAs($this->writerUser, 'sanctum');

            // テスト用の台帳を作成 - 正しいカラムIDを使用
            $columnId = $this->ledgerDefine->column_define[0]->id;
            $yesterdayLedger = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->writerUser->id,
                'created_at' => '2025-09-27 10:00:00',
                'content' => [$columnId => 'Yesterday Content'],
            ]);
            $todayLedger = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->writerUser->id,
                'created_at' => '2025-09-28 10:00:00',
                'content' => [$columnId => 'Today Content'],
            ]);

            // 昨日から今日までの期間でフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', [
                'filter' => ['created_between' => '2025-09-27,2025-09-28']
            ]));

            $response->assertOk()
                ->assertJsonCount(2, 'ledgers');
            
            // レスポンスに両方のledgerが含まれることを確認
            $responseData = $response->json();
            $responseIds = collect($responseData['ledgers'])->pluck('id')->toArray();
            $this->assertContains($yesterdayLedger->id, $responseIds);
            $this->assertContains($todayLedger->id, $responseIds);

            // 今日のみでフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', [
                'filter' => ['created_between' => '2025-09-28,2025-09-28']
            ]));

            $response->assertOk()
                ->assertJsonCount(1, 'ledgers');
                
            // レスポンスに今日のledgerのみが含まれ、昨日のは含まれないことを確認
            $responseData = $response->json();
            $responseIds = collect($responseData['ledgers'])->pluck('id')->toArray();
            $this->assertContains($todayLedger->id, $responseIds);
            $this->assertNotContains($yesterdayLedger->id, $responseIds);
        });
    }

    #[Test]
    public function it_can_filter_ledgers_by_q()
    {
        $this->tenant->run(function () {
            $this->actingAs($this->writerUser, 'sanctum');

            // テスト用の台帳を作成 - 正しいカラムIDを使用
            $columnId = $this->ledgerDefine->column_define[0]->id;
            $ledger1 = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->writerUser->id,
                'content' => [$columnId => 'apple content'],
            ]);
            $ledger2 = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $this->ledgerDefine->id,
                'creator_id' => $this->writerUser->id,
                'content' => [$columnId => 'banana content'],
            ]);
            
            // Mroongaのインデックス更新を待機
            sleep(1);

            // 'apple' でフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', ['filter' => ['q' => 'apple']]));

            $response->assertOk()
                ->assertJsonCount(1, 'ledgers');
                
            // レスポンスにappleのledgerが含まれ、bananaは含まれないことを確認
            $responseData = $response->json();
            $responseIds = collect($responseData['ledgers'])->pluck('id')->toArray();
            $this->assertContains($ledger1->id, $responseIds);
            $this->assertNotContains($ledger2->id, $responseIds);

            // 'banana' でフィルタリング
            $response = $this->getJson(route('api.v1.ledgers.index', ['filter' => ['q' => 'banana']]));

            $response->assertOk()
                ->assertJsonCount(1, 'ledgers');
                
            // レスポンスにbananaのledgerが含まれ、appleは含まれないことを確認
            $responseData = $response->json();
            $responseIds = collect($responseData['ledgers'])->pluck('id')->toArray();
            $this->assertContains($ledger2->id, $responseIds);
            $this->assertNotContains($ledger1->id, $responseIds);
        });
    }
}
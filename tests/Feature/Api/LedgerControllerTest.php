<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * 台帳作成 API テスト
 *
 * 全文検索は使用しないため RefreshDatabaseWithTenant で十分。
 * RAGジョブは Queue::fake() でモック化し、Embeddingコンテナへの接続を防ぐ。
 */
class LedgerControllerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $writerUser;

    private User $viewerUser;

    private Folder $writeFolder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // RAGジョブはdispatchされることだけを確認する。
        // EmbeddingServiceの実行はこのテストの責務外（Embeddingコンテナ不要）。
        Queue::fake();

        // APIリクエストのテナント識別に必要な設定
        config(['tenancy.central_domains' => ['127.0.0.1']]);
        if (! $this->getTenant()->domains()->where('domain', 'localhost')->exists()) {
            $this->getTenant()->domains()->create(['domain' => 'localhost']);
        }

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

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
            'modifier_id' => $this->writerUser->id,
        ]);

        // 台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->writeFolder->id]);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
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

        // 実際のカラムIDを取得してテストデータを作成（ID=0の場合を考慮）
        $columnId1 = $this->ledgerDefine->column_define[0]->id; // 0

        $data = [
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->writeFolder->id,
            'content' => [$columnId1 => 'Test Content'], // [0 => 'Test Content']
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
        if (! empty($responseData['content'])) {
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

        // RAGが有効な場合、台帳作成時にRAGジョブがdispatchされることを確認
        if (config('rag.enabled')) {
            Queue::assertPushed(ProcessLedgerForRagJob::class);
        }
    }

    // 検索関連のテストはSearchApiTestに移動済みのため削除
}

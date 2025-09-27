<?php

namespace Tests\Feature\Api;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private User $writerUser;
    private User $viewerUser;
    private Folder $writeFolder;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.central_domains' => ['127.0.0.1']]);
        $this->tenant->domains()->create(['domain' => 'localhost']);

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 権限とロールを定義
        Permission::findOrCreate('view_ledgers', 'web'); // LedgerPolicyで利用
        $writerRole = Role::findOrCreate('writer', 'web')->givePermissionTo(['view_ledgers']);
        $viewerRole = Role::findOrCreate('viewer', 'web')->givePermissionTo(['view_ledgers']);

        // ユーザーを作成
        $this->writerUser = User::factory()->create()->assignRole($writerRole);
        $this->viewerUser = User::factory()->create()->assignRole($viewerRole);

        $this->tenant->run(function () {
            // フォルダを作成
            $this->writeFolder = Folder::factory()->create(['title' => 'Writable Folder']);
        });

        // フォルダ権限を割り当て
        RoleFolderPermission::create([
            'role_id' => $writerRole->id,
            'folder_id' => $this->writeFolder->id,
            'permission' => FolderPermissionType::WRITE,
            'creator_id' => $this->writerUser->id,
            'modifier_id' => $this->writerUser->id
        ]);

        $this->tenant->run(function () {
            // 台帳定義を作成
            $this->ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->writeFolder->id]);
        });

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

        $data = [
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->writeFolder->id,
            'content' => ['0' => 'Test Content'],
        ];

        $response = $this->postJson(route('api.v1.ledgers.store'), $data);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_write_permission_can_create_ledger_with_tags()
    {
        $this->actingAs($this->writerUser, 'sanctum');

        $data = [
            'ledger_define_id' => $this->ledgerDefine->id,
            'folder_id' => $this->writeFolder->id,
            'content' => ['0' => 'Test Content', '1' => 'Another Content'],
            'tags' => ['new-tag', 'another-tag'],
        ];

        $response = $this->postJson(route('api.v1.ledgers.store'), $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'content',
                    'define',
                ]
            ]);

        $this->assertDatabaseHas('ledgers', [
            'ledger_define_id' => $this->ledgerDefine->id,
        ]);

        // Tagの作成はLedgerService内で行われるため、ここではDBに存在することを確認
        $this->assertDatabaseHas('tags', ['name' => 'new-tag', 'ledger_define_id' => $this->ledgerDefine->id]);
        $this->assertDatabaseHas('tags', ['name' => 'another-tag', 'ledger_define_id' => $this->ledgerDefine->id]);
    }
}

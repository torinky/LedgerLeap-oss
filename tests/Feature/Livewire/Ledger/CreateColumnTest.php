<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase; // ColumnDefine をインポート

class CreateColumnTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントとドメインを作成し、テナンシーを初期化
        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'test.localhost']);
        tenancy()->initialize($this->tenant);

        // ユーザーを作成
        $this->user = User::factory()->create();

        // 台帳作成に必要な権限を作成し、ユーザーに付与
        Permission::findOrCreate('create_ledgers', 'web');
        $role = Role::findOrCreate('test-creator-role', 'web');
        $role->givePermissionTo('create_ledgers');
        $this->user->assignRole($role);

        // ユーザーを認証
        $this->actingAs($this->user);

        // ★ Spatieの権限キャッシュをクリア
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ★ tearDownメソッドを追加
    protected function tearDown(): void
    {
        // テナントコンテキストを終了
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_ledger_with_correct_tenant_id()
    {
        // 1. テストデータの準備
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        // ColumnDefineオブジェクトを作成
        $columnDefineArray = [
            'id' => 1,
            'name' => 'Test Column',
            'type' => 'text',
            'order' => 1,
            'required' => true,
            'unique' => false,
            'options' => [],
            'group' => 'Group 1',
            'file' => null,
        ];

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => [$columnDefineArray], // 連想配列の配列を渡す
        ]);

        // 2. Livewireコンポーネントのテスト
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content', [1 => 'Test Value'])
            ->set('tenantId', $this->tenant->id)
            ->call('saveDirectly')
            ->assertHasNoErrors();

        // 3. アサーション
        $this->assertDatabaseHas('ledgers', [
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // contentの内容も検証
        $ledger = \App\Models\Ledger::first();
        $this->assertNotNull($ledger);
        $this->assertEquals('Test Value', $ledger->content[0]);
    }
}

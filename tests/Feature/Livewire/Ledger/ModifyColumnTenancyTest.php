<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\ModifyColumn;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ModifyColumnTenancyTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テナントとドメインを作成し、テナンシーを初期化（CI で複数テストが同ドメインを作らないようユニーク化）
        $this->tenant = $this->getTenant();

        // ユーザーを作成
        $this->user = User::factory()->create();

        // 権限を作成し、ユーザーに付与
        Permission::findOrCreate('view_ledgers', 'web');
        Permission::findOrCreate('update_ledgers', 'web');
        $role = Role::findOrCreate('test-editor-role', 'web');
        $role->givePermissionTo(['view_ledgers', 'update_ledgers']);
        $this->user->assignRole($role);

        // ユーザーを認証
        $this->actingAs($this->user);

        // テストデータの準備
        $folder = Folder::create(['title' => 'Test Folder', 'tenant_id' => $this->tenant->id, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);

        $columnDefine = new ColumnDefine((object) [
            'id' => 1,
            'name' => 'Test Column',
            'type' => 'text',
            'order' => 1,
            'required' => true,
            'unique' => false,
            'options' => [],
            'group' => 'Group 1',
            'file' => null,
            'sort_index' => null,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'tenant_id' => $this->tenant->id,
            'workflow_enabled' => false,
            'column_define' => [$columnDefine],
        ]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            // normalizeByColumnDefineと同じ形式でデータを作成
            // カラムID=1なので、インデックス0（空）とインデックス1（値）が必要
            'content' => [0 => '', 1 => 'Initial Value'],
        ]);
    }

    #[Test]
    public function it_maintains_tenancy_context_on_update_action()
    {
        // Livewireコンポーネントのテスト
        Livewire::test(ModifyColumn::class, ['ledgerId' => $this->ledger->id])
            ->set('content.1', 'Updated Value')
            ->call('saveDraft') // saveDraftメソッドを呼び出して更新をシミュレート
            ->assertHasNoErrors();

        // アサーション
        $this->assertDatabaseHas('ledgers', [
            'id' => $this->ledger->id,
            'tenant_id' => $this->tenant->id, // テナントIDが維持されていることを確認
        ]);

        // contentの内容が更新されていることを確認
        $updatedLedger = $this->ledger->fresh();
        $this->assertEquals('Updated Value', $updatedLedger->content[1]);
    }
}

<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SortingOptionLabelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        tenancy()->initialize($this->tenant);

        // Permissions
        Permission::findOrCreate('view_ledgers', 'web');
        $role = Role::firstOrCreate(['name' => 'test-viewer-role', 'guard_name' => 'web']);
        $role->givePermissionTo('view_ledgers');
        $this->user->assignRole($role);

        $this->folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $this->folder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'modifier_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_shows_dynamic_label_for_default_sort(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
                new ColumnDefine(1, '日付', 'YMD', 2, [], false, false, 2, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine->id],
            ]);

        $component->assertSet('orderBy', 'default');

        // ラベルに「デフォルト順」とカラム名が含まれていることを確認
        $label = __('ledger.default_sort_order');
        $component->assertSet('orderByLabel', "{$label} (主番, 日付)");

        // UI上にも表示されていることを確認（selected属性が付いているはず）
        $component->assertSee("{$label} (主番, 日付)");
    }

    #[Test]
    public function it_shows_default_sort_option_when_alternate_sort_is_selected(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine->id],
            ]);

        // 作成日ソートに変更
        $component->set('orderBy', 'created_at');

        // ラベルが「作成日」になっていることを確認
        $component->assertSet('orderByLabel', __('ledger.created_at'));

        // 選択肢の中に「デフォルト順」が表示されていることを確認
        $component->assertSee(__('ledger.default_sort_order'));

        // 再度デフォルト順に戻せることを確認
        $component->set('orderBy', 'default');
        $label = __('ledger.default_sort_order');
        $component->assertSet('orderByLabel', "{$label} (主番)");
    }
}

<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType; // RecordsTable から変更
use App\Livewire\Ledger\IndexManager;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SortingOptionLabelTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected Tenant $tenant;

    protected User $user;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = $this->getTenant();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

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

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
            'modifier_id' => $this->user->id,
        ]);

        // RecordsTable は #[Lazy] のため、テスト時は実コンテンツをレンダリングする
        Livewire::withoutLazyLoading();
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
            ->test(IndexManager::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine->id],
            ]);

        $component->assertSet('orderBy', 'default');

        // ラベルに「デフォルト順」とカラム名が含まれていることを確認
        $label = __('ledger.default_sort_order');
        $component->assertSet('orderByLabel', "{$label} (主番, 日付)");

        // IndexManager のビュー内でも表示を確認
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
            ->test(IndexManager::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine->id],
            ]);

        // 作成日ソートに変更
        $component->dispatch('sortRequested', columnName: 'created_at', columnLabel: __('ledger.created_at'));

        $component->assertSet('orderBy', 'created_at');

        // デフォルト順のオプションが表示されていることを確認 (セレクトボックス内など)
        $label = __('ledger.default_sort_order');
        $component->assertSee($label);
    }

    #[Test]
    public function it_shows_generic_label_for_default_sort_when_multiple_ledgers_are_selected(): void
    {
        $ledgerDefine1 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        $ledgerDefine2 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '金額', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine1->id],
            ]);

        $component->assertSet('orderBy', 'default');

        // 2本目の台帳を追加
        $component->dispatch('ledgerDefineIdToggled', ledgerDefineId: $ledgerDefine2->id);

        $component->assertSet('orderBy', 'default');

        // 複数選択時はカラム名が含まれないことを確認
        $label = __('ledger.default_sort_order');
        $component->assertSet('orderByLabel', $label);
        $component->assertDontSee("({$label} (主番))");
    }

    #[Test]
    public function it_shows_generic_label_for_dynamic_column_sort_when_multiple_ledgers_are_selected(): void
    {
        $ledgerDefine1 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        $ledgerDefine2 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '金額', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine1->id, $ledgerDefine2->id],
            ]);

        // content->0 でソート（「主番」または「金額」に対応する動的カラム）
        $component->dispatch('sortRequested', columnName: 'content->0');

        $component->assertSet('orderBy', 'content->0');

        // 複数台帳選択時は「項目指定」と表示されることを確認
        $genericLabel = __('ledger.column.custom_column_sort');
        $component->assertSet('orderByLabel', $genericLabel);
        $component->assertSee($genericLabel);
    }
}

<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\RecordsTable;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultSortTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        tenancy()->initialize($this->tenant);

        // 権限設定
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
    public function it_applies_default_sort_order_when_defined(): void
    {
        // 台帳定義にデフォルトソートを設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
                new ColumnDefine(1, '副番', 'number', 2, [], false, false, 2, '', [], 3, null),
                new ColumnDefine(2, '備考', 'text', 3, [], false, false, null, '', [], 3, null),
            ],
        ]);

        // テストデータを作成（0始まりの連番配列）
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '10', 1 => '20', 2 => 'c'], // 数値キーで0始まり
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '20', 1 => '10', 2 => 'b'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '10', 1 => '10', 2 => 'a'],
        ]);

        // Livewireコンポーネントをテスト
        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
            ]);

        // デフォルトソートが適用されていることを確認 (主番ASC, 副番ASC)
        $component->assertSet('orderBy', 'default');
        $component->assertSet('orderAsc', true); // デフォルトソートは常に昇順

        // ビューに渡されたデータから ledgerRecords を取得
        $viewData = $component->viewData('ledgerRecords');

        // 期待されるソート順 (主番10, 副番10 -> 主番10, 副番20 -> 主番20, 副番10)
        $ledgers = $viewData->items();
        $this->assertCount(3, $ledgers);
        $this->assertEquals('10', $ledgers[0]->content[0]);
        $this->assertEquals('10', $ledgers[0]->content[1]);
        $this->assertEquals('10', $ledgers[1]->content[0]);
        $this->assertEquals('20', $ledgers[1]->content[1]);
        $this->assertEquals('20', $ledgers[2]->content[0]);
        $this->assertEquals('10', $ledgers[2]->content[1]);
    }

    #[Test]
    public function it_resets_to_default_sort_when_sort_is_called_with_default(): void
    {
        // 台帳定義にデフォルトソートを設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);

        // テストデータを作成（0始まりの連番配列）
        $createdAt1 = now()->subDays(1);
        $createdAt2 = now()->subDays(2);

        $ledger1 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '10'],
            'created_at' => $createdAt1,
        ]);
        $ledger2 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '20'],
            'created_at' => $createdAt2,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
            ]);

        // 初期状態はデフォルトソート (主番ASC)
        $component->assertSet('orderBy', 'default');
        $ledgers = $component->viewData('ledgerRecords')->items();
        $this->assertEquals('10', $ledgers[0]->content[0]);

        // created_at で降順ソートに変更
        $component->call('sort', 'created_at', __('ledger.created_at'));
        $component->assertSet('orderBy', 'created_at');
        $component->assertSet('orderAsc', false); // トグルで降順
        $ledgers = $component->viewData('ledgerRecords')->items();
        // 最新のもの（ledger1）が最初に来ることを確認
        $this->assertEquals($ledger1->id, $ledgers[0]->id);

        // 'default' でソートをリセット
        $component->call('sort', 'default');
        $component->assertSet('orderBy', 'default');
        $component->assertSet('orderAsc', true); // デフォルトソートは常に昇順
        $ledgers = $component->viewData('ledgerRecords')->items();
        $this->assertEquals('10', $ledgers[0]->content[0]); // デフォルトソートに戻っていることを確認
    }

    #[Test]
    public function it_does_not_apply_default_sort_when_multiple_ledger_defines_selected(): void
    {
        // デフォルトソートを持つ台帳定義
        $ledgerDefine1 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 3, null),
            ],
        ]);
        // デフォルトソートを持たない台帳定義
        $ledgerDefine2 = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '連番', 'number', 1, [], false, false, null, '', [], 3, null),
            ],
        ]);

        // Livewireコンポーネントをテスト (複数選択)
        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$ledgerDefine1->id, $ledgerDefine2->id],
            ]);

        // デフォルトソートが適用されていないことを確認 (composite_scoreなどが適用される)
        $component->assertNotSet('orderBy', 'default');
    }

    #[Test]
    public function it_casts_column_values_for_sorting_correctly(): void
    {
        // 日付と数値カラムをデフォルトソートに設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '日付', 'YMD', 1, [], false, false, 1, '', [], 3, null),
                new ColumnDefine(1, '金額', 'number', 2, [], false, false, 2, '', [], 3, null),
            ],
        ]);

        // テストデータを作成（0始まりの連番配列）
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-01', 1 => '1000'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-02', 1 => '500'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-01', 1 => '2000'],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
            ]);

        $ledgers = $component->viewData('ledgerRecords')->items();

        // 期待されるソート順 (日付ASC, 金額ASC)
        // 2023-01-01, 1000
        // 2023-01-01, 2000
        // 2023-01-02, 500
        $this->assertCount(3, $ledgers);
        $this->assertEquals('2023-01-01', $ledgers[0]->content[0]);
        $this->assertEquals('1000', $ledgers[0]->content[1]);
        $this->assertEquals('2023-01-01', $ledgers[1]->content[0]);
        $this->assertEquals('2000', $ledgers[1]->content[1]);
        $this->assertEquals('2023-01-02', $ledgers[2]->content[0]);
        $this->assertEquals('500', $ledgers[2]->content[1]);
    }
    #[Test]
    public function it_sorts_auto_number_with_prefix_correctly(): void
    {
        // プレフィックス付きの自動採番をソート項目に設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '日報番号', 'auto_number', 1, ['prefix' => 'DAILY-', 'digits' => 4], false, false, 1, '', [], 3, null),
            ],
        ]);

        // テストデータを作成
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'DAILY-0005'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'DAILY-0001'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'DAILY-0003'],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(RecordsTable::class, [
                'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
            ]);

        $ledgers = $component->viewData('ledgerRecords')->items();

        // 期待されるソート順: DAILY-0001, DAILY-0003, DAILY-0005
        $this->assertCount(3, $ledgers);
        $this->assertEquals('DAILY-0001', $ledgers[0]->content[0]);
        $this->assertEquals('DAILY-0003', $ledgers[1]->content[0]);
        $this->assertEquals('DAILY-0005', $ledgers[2]->content[0]);
    }
}

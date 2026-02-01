<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager; // RecordsTable から IndexManager へ変更
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
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '副番', 'number', 2, [], false, false, 2, '', [], 1, null),
                new ColumnDefine(2, '備考', 'text', 3, [], false, false, null, '', [], 1, null),
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

        // Livewireコンポーネントをテスト - IndexManager を経由して検証
        // mount($folderId = null, $defineId = null) なので defineId を渡す
        Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'defineId' => $this->ledgerDefine->id, // これで selectedLedgerDefineIds がセットされる
            ])
            ->assertSet('orderBy', 'default') // デフォルトソートが適用されているか
            ->assertSet('orderAsc', true)
            // ソート順: 10-10(a), 10-20(c), 20-10(b)
            // 備考カラムの値で順番を確認する
            ->assertSeeInOrder(['a', 'c', 'b']);
    }

    #[Test]
    public function it_resets_to_default_sort_when_sort_is_called_with_default(): void
    {
        // 台帳定義にデフォルトソートを設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '主番', 'number', 1, [], false, false, 1, '', [], 1, null),
            ],
        ]);

        // テストデータを作成
        $createdAt1 = now()->subDays(1);
        $createdAt2 = now()->subDays(2);

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '10'],
            'created_at' => $createdAt1,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '20'],
            'created_at' => $createdAt2,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'defineId' => $this->ledgerDefine->id,
            ]);

        // 初期状態はデフォルトソート (主番ASC)
        $component->assertSet('orderBy', 'default');

        // created_at で降順ソートに変更
        // IndexManager は sort イベントをリッスンしている
        $component->dispatch('sortRequested', columnName: 'created_at', columnLabel: __('ledger.created_at'));

        $component->assertSet('orderBy', 'created_at')
            ->assertSet('orderAsc', false);

        // 'default' でソートをリセット
        $component->dispatch('sortRequested', columnName: 'default');

        $component->assertSet('orderBy', 'default')
            ->assertSet('orderAsc', true);
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
        // mount 引数では単一IDしか渡せないので、プロパティを直接セットするか、クエリパラメータを使う
        // ここでは set() を使用
        $component = Livewire::actingAs($this->user)
            ->test(IndexManager::class)
            ->set('selectedLedgerDefineIds', [$ledgerDefine1->id, $ledgerDefine2->id])
            ->call('updateSearchMetadata'); // メタデータ更新を明示的に呼ぶ（setだけでは呼ばれない場合があるため）

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
                new ColumnDefine(0, '日付', 'YMD', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '金額', 'number', 2, [], false, false, 2, '', [], 1, null),
            ],
        ]);

        // テストデータを作成（0始まりの連番配列）
        $l1 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-01', 1 => '1000'],
        ]);
        $l2 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-02', 1 => '500'],
        ]);
        $l3 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => '2023-01-01', 1 => '2000'],
        ]);

        // Livewireコンポーネントをテスト
        Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'defineId' => $this->ledgerDefine->id,
            ])
            ->assertSet('orderBy', 'default')
            // 期待されるソート順: 2023-01-01 1000 -> 2023-01-01 2000 -> 2023-01-02 500
            // 金額（数値）で判定する（日付は被るため）
            ->assertSeeInOrder(['1000', '2000', '500']);
    }

    #[Test]
    public function it_sorts_auto_number_with_prefix_correctly(): void
    {
        // プレフィックス付きの自動採番をソート項目に設定
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '日報番号', 'auto_number', 1, ['prefix' => 'DAILY-', 'digits' => 4], false, false, 1, '', [], 1, null),
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

        // Livewireコンポーネントをテスト
        Livewire::actingAs($this->user)
            ->test(IndexManager::class, [
                'defineId' => $this->ledgerDefine->id,
            ])
            ->assertSet('orderBy', 'default')
            // 期待されるソート順: DAILY-0001, DAILY-0003, DAILY-0005
            ->assertSeeInOrder(['DAILY-0001', 'DAILY-0003', 'DAILY-0005']);
    }
}

<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\CreateColumn;
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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class DateDefaultInitializationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected Tenant $tenant;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();

        // 権限設定
        Permission::findOrCreate('create_ledgers', 'web');
        Permission::findOrCreate('view_ledgers', 'web');
        Permission::findOrCreate('update_ledgers', 'web');
        $role = Role::findOrCreate('test-user-role', 'web');
        $role->givePermissionTo(['create_ledgers', 'view_ledgers', 'update_ledgers']);
        $this->user->assignRole($role);

        // Create a tenant specifically for this test
        $this->tenant = $this->getTenant();

        // Initialize tenancy for the created tenant

        $this->folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }

    public function test_create_column_initializes_date_with_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => false], true, false, null, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // 7日後の日付が自動設定されていることを確認
        $expectedDate = now()->addDays(7)->format('Y-m-d');
        $component->assertSet('content.1', $expectedDate);
    }

    public function test_create_column_does_not_set_default_when_offset_empty(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => ''], false, false, null, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // オフセットが空欄の場合はnullまたは空文字
        $component->assertSet('content.1', null);
    }

    public function test_create_column_with_today_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '作成日', 'YMD', 1, ['default_offset' => '0d'], true, false, null, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // 今日の日付が設定されていることを確認
        $expectedDate = now()->format('Y-m-d');
        $component->assertSet('content.1', $expectedDate);
    }

    public function test_modify_column_preserves_existing_value_without_overwrite(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => false], true, false, null, '', [], 3, null),
            ],
        ]);

        $existingDate = '2024-01-15';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            // normalizeByColumnDefineと同じ形式でデータを作成
            // カラムID=1なので、インデックス0（空）とインデックス1（値）が必要
            'content' => [0 => '', 1 => $existingDate],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // 既存値が保持されていることを確認
        $component->assertSet('content.1', $existingDate);
    }

    public function test_modify_column_overwrites_existing_value_when_enabled(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => true], true, false, null, '', [], 3, null),
            ],
        ]);

        $existingDate = '2024-01-15';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            // normalizeByColumnDefineと同じ形式でデータを作成
            // カラムID=1なので、インデックス0（空）とインデックス1（値）が必要
            'content' => [0 => '', 1 => $existingDate],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // 7日後の日付で上書きされていることを確認
        $expectedDate = now()->addDays(7)->format('Y-m-d');
        $component->assertSet('content.1', $expectedDate);
    }

    public function test_modify_column_sets_default_when_no_existing_value(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '3d'], false, false, null, '', [], 3, null),
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            // normalizeByColumnDefineと同じ形式でデータを作成
            // カラムID=1なので、インデックス0（空）とインデックス1（空）が必要
            'content' => [0 => '', 1 => ''], // 既存値なし
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // 3日後の日付が設定されていることを確認
        $expectedDate = now()->addDays(3)->format('Y-m-d');
        $component->assertSet('content.1', $expectedDate);
    }

    public function test_create_column_with_multiple_date_columns(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '開始日', 'YMD', 1, ['default_offset' => '0d'], true, false, null, '', [], 3, null),
                new ColumnDefine(2, '終了日', 'YMD', 2, ['default_offset' => '30d'], true, false, null, '', [], 3, null),
                new ColumnDefine(3, '任意日', 'YMD', 3, ['default_offset' => ''], false, false, null, '', [], 3, null),
            ],
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id]);

        // 各カラムが正しく初期化されていることを確認
        $component->assertSet('content.1', now()->format('Y-m-d'));
        $component->assertSet('content.2', now()->addDays(30)->format('Y-m-d'));
        $component->assertSet('content.3', null);
    }
}

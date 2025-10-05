<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Livewire\Ledger\ModifyColumn;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
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

        // Create a tenant specifically for this test
        tenancy()->central(function () {
            $this->tenant = Tenant::factory()->create();
        });

        // Initialize tenancy for the created tenant
        tenancy()->initialize($this->tenant);

        $this->folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            tenancy()->central(function () {
                if ($this->tenant->exists) {
                    $this->tenant->delete();
                }
            });
        }

        parent::tearDown();
    }

    public function test_create_column_initializes_date_with_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => false], true),
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
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => ''], false),
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
                new ColumnDefine(1, '作成日', 'YMD', 1, ['default_offset' => '0d'], true),
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
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => false], true),
            ],
        ]);

        $existingDate = '2024-01-15';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [1 => $existingDate],
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
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '7d', 'overwrite_existing' => true], true),
            ],
        ]);

        $existingDate = '2024-01-15';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [1 => $existingDate],
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
                new ColumnDefine(1, '提出日', 'YMD', 1, ['default_offset' => '3d'], false),
            ],
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [], // 既存値なし
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
                new ColumnDefine(1, '開始日', 'YMD', 1, ['default_offset' => '0d'], true),
                new ColumnDefine(2, '終了日', 'YMD', 2, ['default_offset' => '30d'], true),
                new ColumnDefine(3, '任意日', 'YMD', 3, ['default_offset' => ''], false),
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

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DateDefaultInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
        $this->user->tenants()->attach($this->tenant->id);

        $this->folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_column_initializes_date_with_default_offset(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(
                    id: 1,
                    name: '提出日',
                    type: 'YMD',
                    required: true,
                    options: [
                        'default_offset' => '7d', // 7日後
                        'overwrite_existing' => false,
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '提出日',
                    type: 'YMD',
                    required: false,
                    options: [
                        'default_offset' => '', // 空欄
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '作成日',
                    type: 'YMD',
                    required: true,
                    options: [
                        'default_offset' => '0d', // 今日
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '提出日',
                    type: 'YMD',
                    required: true,
                    options: [
                        'default_offset' => '7d',
                        'overwrite_existing' => false,
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '提出日',
                    type: 'YMD',
                    required: true,
                    options: [
                        'default_offset' => '7d',
                        'overwrite_existing' => true, // 上書き有効
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '提出日',
                    type: 'YMD',
                    required: false,
                    options: [
                        'default_offset' => '3d',
                    ]
                ),
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
                new ColumnDefine(
                    id: 1,
                    name: '開始日',
                    type: 'YMD',
                    required: true,
                    options: ['default_offset' => '0d']
                ),
                new ColumnDefine(
                    id: 2,
                    name: '終了日',
                    type: 'YMD',
                    required: true,
                    options: ['default_offset' => '30d']
                ),
                new ColumnDefine(
                    id: 3,
                    name: '任意日',
                    type: 'YMD',
                    required: false,
                    options: ['default_offset' => '']
                ),
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

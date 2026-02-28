<?php

namespace Tests\Feature\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DefaultSortPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected User $user;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase::setUp で tenancy()->initialize($this->tenant) が実行済み
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, 'ID', 'auto_number', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '日付', 'YMD', 2, [], false, false, 2, '', [], 1, null),
                new ColumnDefine(2, '金額', 'number', 3, [], false, false, null, '', [], 1, null),
            ],
        ]);
    }

    /**
     * 新規作成時に default_sort_value が自動生成されることを確認
     */
    public function test_default_sort_value_is_populated_on_creation(): void
    {
        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => [0 => 'EXP-0001', 1 => '2025-02-01', 2 => '1000'],
        ]);

        $this->assertNotNull($ledger->default_sort_value);
        // EXP-0001|2025-02-01
        $this->assertEquals('EXP-0001|2025-02-01', $ledger->default_sort_value);
    }

    /**
     * 更新時に default_sort_value が再計算されることを確認
     */
    public function test_default_sort_value_is_updated_on_save(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'EXP-0001', 1 => '2025-02-01', 2 => '1000'],
        ]);

        $ledger->content = [0 => 'EXP-0001', 1 => '2025-02-15', 2 => '1000'];
        $ledger->save();

        $this->assertEquals('EXP-0001|2025-02-15', $ledger->default_sort_value);
    }

    /**
     * カラム定義の sort_index 変更時にジョブが再生成を実行することを確認
     */
    public function test_regeneration_triggered_on_define_change(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'EXP-0001', 1 => '2025-02-01', 2 => '1000'],
        ]);

        // sort_index を変更（日付→IDの順に入れ替え: 日付が sort_index=1, ID が sort_index=2）
        $newColumns = [
            new ColumnDefine(0, 'ID', 'auto_number', 1, [], false, false, 2, '', [], 1, null),
            new ColumnDefine(1, '日付', 'YMD', 2, [], false, false, 1, '', [], 1, null),
            new ColumnDefine(2, '金額', 'number', 3, [], false, false, null, '', [], 1, null),
        ];

        $this->ledgerDefine->column_define = $newColumns;
        $this->ledgerDefine->save();

        // LedgerDefineObserver は delay(5秒) 付きで RegenerateLedgerSortValuesJob を dispatch する。
        // Queue::fake() 環境では BusFake::dispatchSync() もジョブを実際には実行しない。
        // そのためジョブを直接インスタンス化して handle() を呼び出す。
        (new \App\Jobs\Ledger\RegenerateLedgerSortValuesJob($this->ledgerDefine->id))->handle();

        // 期待される値: 日付(sort_index=1)→ID(sort_index=2) の順で 2025-02-01|EXP-0001
        $ledger->refresh();
        $this->assertEquals('2025-02-01|EXP-0001', $ledger->default_sort_value);
    }

    /**
     * 再生成コマンドが全テナントに対して動作することを確認
     */
    public function test_regenerate_command_works_across_tenants(): void
    {
        // 既存レコードのソート値を NULL にする
        $ledger1 = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'A', 1 => '2025-01-01'],
        ]);
        \DB::table('ledgers')->where('id', $ledger1->id)->update(['default_sort_value' => null]);

        // 別テナント作成 (規約上、テストメソッド内での新規作成・初期化は慎重に)
        $tenant2 = Tenant::factory()->create(['id' => 'tenant2']);
        tenancy()->initialize($tenant2);

        $define2 = LedgerDefine::factory()->create([
            'tenant_id' => $tenant2->id,
            'column_define' => $this->ledgerDefine->column_define,
        ]);
        $ledger2 = Ledger::factory()->create([
            'ledger_define_id' => $define2->id,
            'content' => [0 => 'B', 1 => '2025-01-01'],
        ]);
        \DB::table('ledgers')->where('id', $ledger2->id)->update(['default_sort_value' => null]);

        tenancy()->end();

        // コマンド実行
        Artisan::call('ledger:regenerate-default-sort', ['--force' => true]);

        // 各テナントの値を検証
        tenancy()->initialize($this->tenant);
        $this->assertEquals('A|2025-01-01', $ledger1->refresh()->default_sort_value);

        tenancy()->initialize($tenant2);
        $this->assertEquals('B|2025-01-01', $ledger2->refresh()->default_sort_value);
    }
}

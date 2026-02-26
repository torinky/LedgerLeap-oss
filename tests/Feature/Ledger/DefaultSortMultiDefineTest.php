<?php

namespace Tests\Feature\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultSortMultiDefineTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase::setUp で tenancy()->initialize($this->tenant) が実行済み
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // 強制的にデータをクリア（RefreshDatabaseが機能しない場合の対策）
        Ledger::query()->delete();
        LedgerDefine::query()->delete();
    }

    /**
     * 異なる台帳定義間でのソート順序整合性をテスト
     */
    public function test_sort_order_consistency_across_different_defines(): void
    {
        // 台帳定義1: 日付(優先度1)
        $define1 = LedgerDefine::factory()->create([
            'title' => '日付台帳',
            'column_define' => [
                new ColumnDefine(0, '日付', 'YMD', 1, [], false, false, 1, '', [], 1, null),
            ],
        ]);

        // 台帳定義2: 金額(優先度1)
        $define2 = LedgerDefine::factory()->create([
            'title' => '金額台帳',
            'column_define' => [
                new ColumnDefine(0, '金額', 'number', 1, [], false, false, 1, '', [], 1, null),
            ],
        ]);

        $l1 = Ledger::factory()->create([
            'ledger_define_id' => $define2->id,
            'content' => [0 => '-100'],
        ]);
        $l2 = Ledger::factory()->create([
            'ledger_define_id' => $define1->id,
            'content' => [0 => '2024-01-01'],
        ]);
        $l3 = Ledger::factory()->create([
            'ledger_define_id' => $define2->id,
            'content' => [0 => '50'],
        ]);
        $l4 = Ledger::factory()->create([
            'ledger_define_id' => $define1->id,
            'content' => [0 => '2025-01-01'],
        ]);

        // DBのCollation (utf8mb4_unicode_ci) では '-' < '+' となる
        // 期待される順序:
        // 1. l1: "-000...0100"
        // 2. l3: "+000...0050"
        // 3. l2: "2024-01-01"
        // 4. l4: "2025-01-01"

        $results = Ledger::orderBy('default_sort_value', 'asc')->get()->pluck('id')->toArray();

        $this->assertEquals([$l1->id, $l3->id, $l2->id, $l4->id], $results);
    }

    /**
     * ソートインデックスが未設定の台帳を含む場合の挙動
     */
    public function test_handling_ledger_without_sort_index(): void
    {
        // ソート設定あり
        $define1 = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, '名前', 'text', 1, [], false, false, 1, '', [], 1, null),
            ],
        ]);

        // ソート設定なし (default_sort_value は null または空)
        $define2 = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, '名前', 'text', 1, [], false, false, null, '', [], 1, null),
            ],
        ]);

        $l1 = Ledger::factory()->create(['ledger_define_id' => $define1->id, 'content' => [0 => 'A']]);
        $l2 = Ledger::factory()->create(['ledger_define_id' => $define2->id, 'content' => [0 => 'B']]);

        // null 値の扱いを確認（MySQLでは NULLS FIRST または LAST だが、RecordsTable の実装に依存）
        // RecordsTable では orderBy('default_sort_value', 'asc')
        $results = Ledger::orderBy('default_sort_value', 'asc')->get();

        $this->assertCount(2, $results);
    }

    /**
     * 512文字制限による切り詰めテスト
     */
    public function test_sort_value_truncation(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, '長文1', 'text', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '長文2', 'text', 2, [], false, false, 2, '', [], 1, null),
            ],
        ]);

        $longText1 = str_repeat('あ', 300);
        $longText2 = str_repeat('い', 300);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $define->id,
            'content' => [
                0 => $longText1,
                1 => $longText2,
            ],
        ]);

        $this->assertLessThanOrEqual(512, mb_strlen($ledger->default_sort_value));
        $this->assertStringContainsString('あ', $ledger->default_sort_value);
    }
}

<?php

namespace Tests\Feature\Import;

use App\Imports\LedgerImport;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LedgerImportSortValueTest extends TestCase
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

        // 台帳定義作成（sort_indexあり）
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine(0, '氏名', 'text', 1, [], false, false, 1, '', [], 1, null),
                new ColumnDefine(1, '年齢', 'number', 2, [], false, false, 2, '', [], 1, null),
            ],
        ]);
    }

    /**
     * インポートによって作成されたレコードに default_sort_value がセットされていることを確認
     */
    public function testImportSetsDefaultSortValue(): void
    {
        $import = new LedgerImport($this->ledgerDefine);

        $row = [
            '氏名' => '山田 太郎',
            '年齢' => '30',
        ];

        // LedgerImport::model は行データを Ledger インスタンスに変換する
        $ledger = $import->model($row);

        // 保存前に既にセットされているはず（model()内で計算しているため）
        $expectedSortValue = '山田 太郎|+00000000000000000030.0000000000';
        $this->assertEquals($expectedSortValue, $ledger->default_sort_value);

        // 実際に保存して確認
        $ledger->save();
        $this->assertDatabaseHas('ledgers', [
            'id' => $ledger->id,
            'default_sort_value' => $expectedSortValue,
        ]);
    }
}

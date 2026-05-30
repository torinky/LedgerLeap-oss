<?php

namespace Database\Seeders;

use App\Models\Ledger;
use Database\Factories\AutoNumberLedgerDefineFactory;
use Illuminate\Database\Seeder;

class AutoNumberingTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // AutoNumberLedgerDefineFactory を使用して台帳定義を作成
        $ledgerDefine = AutoNumberLedgerDefineFactory::new()->create();

        $this->command->info('Created LedgerDefine for AutoNumbering Test: '.$ledgerDefine->title);

        // テスト用の初期レコードをいくつか作成
        // シナリオ1: unique=false の自動採番カラム (ID: 0)
        // DOC-001-A, DOC-002-A, DOC-001-B
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-A',
                1 => 'PROJ-0001',
                2 => '最初の資料',
            ],
            'creator_id' => 1,
            'modifier_id' => 1,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-002-A',
                1 => 'PROJ-0002',
                2 => '二番目の資料',
            ],
            'creator_id' => 1,
            'modifier_id' => 1,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-B',
                1 => 'PROJ-0003',
                2 => '別の版の資料',
            ],
            'creator_id' => 1,
            'modifier_id' => 1,
        ]);

        // シナリオ2: unique=true の自動採番カラム (ID: 1)
        // PROJ-0001, PROJ-0002, PROJ-0003 は既に作成済み
        // PROJ-0001-X のような形式も考慮
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-003-A',
                1 => 'PROJ-0001-X',
                2 => '重複テスト用',
            ],
            'creator_id' => 1,
            'modifier_id' => 1,
        ]);

        $this->command->info('Seeded initial Ledger records for AutoNumbering Test.');
    }
}

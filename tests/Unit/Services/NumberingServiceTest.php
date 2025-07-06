<?php

namespace Tests\Unit\Services;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingService $numberingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numberingService = new NumberingService();
    }

    public function test_it_generates_initial_number_when_no_records_exist(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            '資料番号',
            'auto_number',
            1,
            [
                'prefix' => 'DOC-',
                'digits' => 3,
                'revision' => '-A',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('DOC-001-A', $nextNumber);
    }

    public function test_it_increments_number_when_unique_is_false_and_revision_matches(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            '資料番号',
            'auto_number',
            1,
            [
                'prefix' => 'DOC-',
                'digits' => 3,
                'revision' => '-A',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        // 既存レコードを作成
        User::unguard(); // マスアサインメント保護を一時的に無効化
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-A',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-002-A',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-B',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard(); // マスアサインメント保護を元に戻す

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('DOC-003-A', $nextNumber);
    }

    public function test_it_increments_number_when_unique_is_true_ignoring_revision(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            'プロジェクトID',
            'auto_number',
            1,
            [
                'prefix' => 'PROJ-',
                'digits' => 4,
                'revision' => '',
            ],
            true,
            true, // unique = true
            false,
            'ヒント'
        );

        // 既存レコードを作成 (異なる版記号)
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'PROJ-0001-X',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'PROJ-0002-Y',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('PROJ-0003', $nextNumber);
    }

    public function test_it_applies_correct_padding_based_on_digits(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            'テスト番号',
            'auto_number',
            1,
            [
                'prefix' => 'TEST-',
                'digits' => 4,
                'revision' => '',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        User::unguard();
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'TEST-0009',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('TEST-0010', $nextNumber);
    }

    public function test_it_handles_empty_prefix_and_revision(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            'シンプル番号',
            'auto_number',
            1,
            [
                'prefix' => '',
                'digits' => 2,
                'revision' => '',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        User::unguard();
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => '09',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('10', $nextNumber);
    }

    public function test_it_handles_non_consecutive_existing_numbers(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            '非連続番号',
            'auto_number',
            1,
            [
                'prefix' => 'GAP-',
                'digits' => 3,
                'revision' => '',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        User::unguard();
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'GAP-001',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'GAP-005',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('GAP-006', $nextNumber);
    }

    public function test_it_ignores_non_matching_content_values(): void
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        $columnDefine = new ColumnDefine(
            0,
            '無視テスト',
            'auto_number',
            1,
            [
                'prefix' => 'ABC-',
                'digits' => 3,
                'revision' => '',
            ],
            true,
            false,
            false,
            'ヒント'
        );

        User::unguard();
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'ABC-001',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'XYZ-999', // 異なるプレフィックス
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                0 => 'ABC-TEXT', // 数字部分がない
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $nextNumber = $this->numberingService->getNextNumber($columnDefine, $ledgerDefine->id);

        $this->assertEquals('ABC-002', $nextNumber);
    }
}

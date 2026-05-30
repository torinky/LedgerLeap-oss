<?php

namespace Tests\Unit\Models;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerDefaultSortTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    /**
     * 数値型の正規化テスト
     */
    public function test_normalize_number(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '数値',
                    'type' => 'number',
                    'sort_index' => 1,
                    'order' => 1,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;

        // 正の整数
        $ledger->content = [0 => '', 1 => '123'];
        $this->assertEquals('+00000000000000000123.0000000000', $ledger->generateDefaultSortValue());

        // 正の小数
        $ledger->content = [0 => '', 1 => '123.456'];
        $this->assertEquals('+00000000000000000123.4560000000', $ledger->generateDefaultSortValue());

        // 負の整数
        $ledger->content = [0 => '', 1 => '-123'];
        $this->assertEquals('-00000000000000000123.0000000000', $ledger->generateDefaultSortValue());

        // 負の小数
        $ledger->content = [0 => '', 1 => '-123.456'];
        $this->assertEquals('-00000000000000000123.4560000000', $ledger->generateDefaultSortValue());

        // 0
        $ledger->content = [0 => '', 1 => '0'];
        $this->assertEquals('+00000000000000000000.0000000000', $ledger->generateDefaultSortValue());

        // 非数値
        $ledger->content = [0 => '', 1 => 'abc'];
        $this->assertEquals(str_repeat(' ', 32), $ledger->generateDefaultSortValue());
    }

    /**
     * 自動採番型の正規化テスト
     */
    public function test_normalize_auto_number(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '伝票番号',
                    'type' => 'auto_number',
                    'sort_index' => 1,
                    'order' => 1,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;
        $ledger->content = [0 => '', 1 => 'EXP-0001'];

        $this->assertEquals('EXP-0001', $ledger->generateDefaultSortValue());
    }

    /**
     * 日付型の正規化テスト
     */
    public function test_normalize_date(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '日付',
                    'type' => 'YMD',
                    'sort_index' => 1,
                    'order' => 1,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;

        $ledger->content = [0 => '', 1 => '2025/02/01'];
        $this->assertEquals('2025-02-01', $ledger->generateDefaultSortValue());

        $ledger->content = [0 => '', 1 => 'invalid date'];
        $this->assertEquals('0000-00-00', $ledger->generateDefaultSortValue());
    }

    /**
     * テキスト型の正規化テスト
     */
    public function test_normalize_text(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '摘要',
                    'type' => 'text',
                    'sort_index' => 1,
                    'order' => 1,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;

        // HTMLタグとMarkdown、改行の除去
        $ledger->content = [0 => '', 1 => "<b>Hello</b>\n*World*"];
        $this->assertEquals('Hello World', $ledger->generateDefaultSortValue());

        // 50文字制限
        $ledger->content = [0 => '', 1 => str_repeat('あ', 60)];
        $this->assertEquals(str_repeat('あ', 50), $ledger->generateDefaultSortValue());

        // 空白の集約
        $ledger->content = [0 => '', 1 => '  A    B  '];
        $this->assertEquals('A B', $ledger->generateDefaultSortValue());
    }

    /**
     * ファイル型の正規化テスト
     */
    public function test_normalize_files(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '添付',
                    'type' => 'files',
                    'sort_index' => 1,
                    'order' => 1,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;

        // 最初のファイル名が使用される
        $ledger->content = [
            0 => '',
            1 => [
                'hash1' => 'important_doc.pdf',
                'hash2' => 'image.png',
            ],
        ];
        $this->assertEquals('important_doc.pdf', $ledger->generateDefaultSortValue());
    }

    /**
     * 複数カラムの連結テスト
     */
    public function test_combine_multiple_columns(): void
    {
        $define = LedgerDefine::factory()->create([
            'column_define' => [
                [
                    'id' => 1,
                    'name' => '日付',
                    'type' => 'YMD',
                    'sort_index' => 2,
                    'order' => 1,
                ],
                [
                    'id' => 2,
                    'name' => '番号',
                    'type' => 'number',
                    'sort_index' => 1,
                    'order' => 2,
                ],
            ],
        ]);

        $ledger = new Ledger;
        $ledger->ledger_define_id = $define->id;
        $ledger->content = [
            0 => '',
            1 => '2025-02-01',
            2 => '100',
        ];

        // sort_index: 番号(1) -> 日付(2) の順
        $expected = '+00000000000000000100.0000000000|2025-02-01';
        $this->assertEquals($expected, $ledger->generateDefaultSortValue());
    }
}

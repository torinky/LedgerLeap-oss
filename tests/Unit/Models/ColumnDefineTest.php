<?php


namespace Tests\Unit\Models;

use App\Models\ColumnDefine;
use RuntimeException;
use Tests\TestCase;

class ColumnDefineTest extends TestCase
{
    public function testObjectInitialization(): void
    {
        // テスト用のオブジェクトを作成
        $data = (object)[
            'id' => 1,
            'name' => 'column1',
            'type' => 'text',
            'order' => 2,
            'options' => ['option1', 'option2'],
            'required' => true,
            'doNotDuplicate' => false,
            'sortBy' => true,
        ];

        // オブジェクトによる初期化のテスト
        $column = new ColumnDefine($data);

        // オブジェクトのプロパティが期待通りにセットされているかテスト
        $this->assertEquals($data->id, $column->id);
        $this->assertEquals($data->name, $column->name);
        $this->assertEquals($data->type, $column->type);
        $this->assertEquals($data->order, $column->order);
        $this->assertFalse($column->useOptions); // 'text'は$optionsを持たないためtrueになる
        $this->assertEquals($data->options, $column->options);
        $this->assertEquals($data->required, $column->required);
        $this->assertEquals($data->doNotDuplicate, $column->doNotDuplicate);
        $this->assertEquals($data->sortBy, $column->sortBy);
    }

    public function testValueInitialization(): void
    {
        // 値による初期化のテスト
        $column = new ColumnDefine(2, 'column2', 'select', 1, ['option1', 'option2'], false, true, false);

        // オブジェクトのプロパティが期待通りにセットされているかテスト
        $this->assertEquals(2, $column->id);
        $this->assertEquals('column2', $column->name);
        $this->assertEquals('select', $column->type);
        $this->assertEquals(1, $column->order);
        $this->assertTrue($column->useOptions); // 'select'は$optionsを持つためtrueになる
        $this->assertEquals(['option1', 'option2'], $column->options);
        $this->assertFalse($column->required);
        $this->assertTrue($column->doNotDuplicate);
        $this->assertFalse($column->sortBy);
    }

    public function testSetType(): void
    {
        // 列の種類を変更するテスト
        $column = new ColumnDefine(3, 'column3', 'text', 1);

        $column->setType('textarea');
        $this->assertEquals('textarea', $column->type);
        $this->assertFalse($column->useOptions); // 'textarea'は$optionsを持たないためfalseになる

        $column->setType('chk');
        $this->assertEquals('chk', $column->type);
        $this->assertTrue($column->useOptions); // 'chk'は$optionsを持つためtrueになる

        // 無効な列の種類を設定しようとする場合のテスト
        $this->expectException(RuntimeException::class);
        $column->setType('invalid_type');
    }

    public function testTypeLabels(): void
    {
        // 列の種類のラベルを取得するテスト
        $labels = ColumnDefine::typeLabels();

        // 期待するラベルが含まれているかテスト
        $this->assertArrayHasKey('number', $labels);
        $this->assertArrayHasKey('text', $labels);
        $this->assertArrayHasKey('textarea', $labels);
        $this->assertArrayHasKey('chk', $labels);
        $this->assertArrayHasKey('select', $labels);
        $this->assertArrayHasKey('YMD', $labels);
        $this->assertArrayHasKey('files', $labels);
    }
}

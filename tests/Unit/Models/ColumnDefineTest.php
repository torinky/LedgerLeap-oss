<?php

namespace Tests\Unit\Models;

use App\Models\ColumnDefine;
use RuntimeException;
use Tests\TestCase;

class ColumnDefineTest extends TestCase
{
    public function test_object_initialization(): void
    {
        // テスト用のオブジェクトを作成
        $data = (object)[
            'id' => 1,
            'name' => 'column1',
            'type' => 'text',
            'order' => 2,
            'options' => ['option1', 'option2'],
            'required' => true,
            'unique' => false,
            'sortBy' => true,
            'hint' => '',
            'file' => '',
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
        $this->assertEquals($data->unique, $column->unique);
        $this->assertEquals($data->sortBy, $column->sortBy);
        $this->assertEquals($data->hint, $column->hint);
        $this->assertEquals($data->file, $column->file);
    }

    /**
     * @test
     *
     * 値による初期化のテスト
     *
     * ColumnDefineのインスタンスを生成するための引数を指定し、期待通りの値がセットされているかテスト
     */
    public function test_value_initialization(): void
    {
        // 値による初期化のテスト
        $column = new ColumnDefine(2, 'column2', 'select', 1, ['option1', 'option2'], false, true, false, false, ['file1', 'file2'], '');
        // オブジェクトのプロパティが期待通りにセットされているかテスト
        $this->assertEquals(2, $column->id);
        $this->assertEquals('column2', $column->name);
        $this->assertEquals('select', $column->type);
        $this->assertEquals(1, $column->order);
        $this->assertTrue($column->useOptions); // 'select'は$optionsを持つためtrueになる
        $this->assertEquals(['option1', 'option2'], $column->options);
        $this->assertFalse($column->required);
        $this->assertTrue($column->unique);
        $this->assertFalse($column->sortBy);
    }

    /**
     * @test
     *
     * 列の種類を変更するテスト
     *
     * 列の種類を変更するメソッドのテスト
     * 'text'→'textarea'、'textarea'→'chk'、無効な列の種類を設定しようとする場合をテスト
     */
    public function test_set_type(): void
    {
        // 列の種類を変更するテスト
        $column = new ColumnDefine(3, 'column3', 'text');

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

    /**
     * @test
     *
     * 列の種類のラベルを取得するテスト
     *
     * ColumnDefine::typeLabels()で取得できるラベルに期待する値があるかテスト
     */
    public function test_type_labels(): void
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

    /**
     * @test
     *
     * setOptions()に空の配列を渡すと、$optionsが空の配列になるかテスト
     *
     * setOptions()に空の配列を渡すと、$optionsが空の配列になることをテスト
     */
    public function test_handles_empty_options_array()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'select', 1, []);

        // Act
        $column->setOptions([]);

        // Assert
        $this->assertEquals([], $column->options);
    }

    /**
     * @test
     *
     * 'files'タイプの列の値を文字列に変換するテスト
     *
     * 'files'タイプの列に配列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_files_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'files', 1);
        $fileValue = ['file1.jpg', 'file2.png'];

        // Act
        $convertedValue = $column->convertColumnValue2Text($fileValue);

        // Assert
        $this->assertEquals('["file1.jpg","file2.png"]', $convertedValue);
    }

    /**
     * @test
     *
     * 'chk'タイプの列の値を文字列に変換するテスト
     *
     * 'chk'タイプの列に配列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_chk_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'chk', 1);
        $chkValue = ['option1', 'option2'];

        // Act
        $convertedValue = $column->convertColumnValue2Text($chkValue);

        // Assert
        $this->assertEquals('["option1","option2"]', $convertedValue);
    }

    /**
     * @test
     *
     * 'YMD'タイプの列の値を文字列に変換するテスト
     *
     * 'YMD'タイプの列に日付文字列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_ymd_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'YMD', 1);
        $dateValue = '2022-12-31';

        // Act
        $convertedValue = $column->convertColumnValue2Text($dateValue);

        // Assert
        $this->assertEquals('2022-12-31', $convertedValue);
    }

    /**
     * @test
     *
     * 'number'タイプの列の値を文字列に変換するテスト
     *
     * 'number'タイプの列に数値を設定し、convertColumnValue2Text()を実行することで、
     * 期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_number_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'number', 1);
        $numberValue = 12345;

        // Act
        $convertedValue = $column->convertColumnValue2Text($numberValue);

        // Assert
        $this->assertEquals('12345', $convertedValue);
    }

    /**
     * @test
     *
     * 'text'タイプの列の値を文字列に変換するテスト
     *
     * 'text'タイプの列に文字列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_text_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'text', 1);
        $textValue = 'Hello, World!';

        // Act
        $convertedValue = $column->convertColumnValue2Text($textValue);

        // Assert
        $this->assertEquals($textValue, $convertedValue);
    }

    /**
     * @test
     *
     * 'textarea'タイプの列の値を文字列に変換するテスト
     *
     * 'textarea'タイプの列に複数行テキストを設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_text_area_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'textarea', 1);
        $textareaValue = 'Hello, World!\nThis is a multi-line text.';

        // Act
        $convertedValue = $column->convertColumnValue2Text($textareaValue);

        // Assert
        $this->assertEquals('Hello, World!\\nThis is a multi-line text.', $convertedValue);
    }

    /**
     * @test
     *
     * 'select'タイプの列の値を文字列に変換するテスト
     *
     * 'select'タイプの列に選択肢を設定し、convertColumnValue2Text()を実行することで、
     * 選択された値が文字列に変換されるかテスト
     */
    public function test_column_type_conversion_for_select_type()
    {
        // Arrange
        $column = new ColumnDefine;
        $column->setType('select');
        $column->setOptions(['Option 1', 'Option 2', 'Option 3']);
        $selectedValue = 'Option 2';

        // Act
        $convertedValue = $column->convertColumnValue2Text($selectedValue);

        // Assert
        $this->assertEquals('Option 2', $convertedValue);
    }

    // Unit test for handling column type conversion for 'invalid' type

    /**
     * @test
     *
     * 不正な列の種類を指定した場合の列の値を文字列に変換するテスト
     *
     * 不正な列の種類を指定した場合、convertColumnValue2Text()を実行することで、
     * そのままの文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_invalid_type()
    {
        // Arrange
        $column = new ColumnDefine;
        $column->type = 'invalid';
        $value = 'test value';

        // Act
        $convertedValue = $column->convertColumnValue2Text($value);

        // Assert
        $this->assertEquals($value, $convertedValue);
    }

    /**
     * @test
     *
     * 'textarea'タイプの列の値を複数行文字列に変換するテスト
     *
     * 'textarea'タイプの列に複数行テキストを設定し、convertColumnValue2Text()を実行することで、
     * 期待する文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_text_area_type_with_multiple_lines()
    {
        // Arrange
        $column = new ColumnDefine;
        $column->type = 'textarea';
        $textareaValue = 'Hello, World!\nThis is a multi-line text.';

        // Act
        $convertedValue = $column->convertColumnValue2Text($textareaValue);

        // Assert
        $expectedConvertedValue = 'Hello, World!\\nThis is a multi-line text.';
        $this->assertEquals($expectedConvertedValue, $convertedValue);
    }

    /**
     * @test
     *
     * setOptions()に空の配列を渡すと、$optionsが空の配列になるかテスト
     *
     * setOptions()に空の配列を渡すと、$optionsが空の配列になることをテスト
     */
    public function test_set_options_with_empty_array()
    {
        // Arrange
        $column = new ColumnDefine;
        $column->type = 'select';
        $column->options = [];

        // Act
        $column->setOptions([]);

        // Assert
        $this->assertEquals([], $column->options);
    }

    /**
     * @test
     *
     * required/uniqueフラグのON/OFFをテスト
     *
     * required/uniqueフラグをON/OFFすることで、ColumnDefineのプロパティが
     * 正しく更新されるかテスト
     */
    public function test_set_required_and_unique_flags()
    {
        // Create a new instance of ColumnDefine
        $column = new ColumnDefine;

        // Set 'required' flag to true
        $column->setRequired(true);
        $this->assertTrue($column->required);

        // Set 'required' flag to false
        $column->setRequired(false);
        $this->assertFalse($column->required);

        // Set 'unique' flag to true
        $column->setUnique(true);
        $this->assertTrue($column->unique);

        // Set 'unique' flag to false
        $column->setUnique(false);
        $this->assertFalse($column->unique);
    }

    /**
     * @test
     *
     * 'chk'タイプの列の値に重複する値がある場合の列の値を文字列に変換するテスト
     *
     * 'chk'タイプの列に重複する値がある場合、convertColumnValue2Text()を実行することで、
     * そのままの文字列が生成されるかテスト
     */
    public function test_column_type_conversion_for_chk_type_with_duplicate_options()
    {
        // Arrange
        $column = new ColumnDefine;
        $column->type = 'chk';
        $column->options = ['Option 1', 'Option 2', 'Option 1'];

        // Act
        $convertedValue = $column->convertColumnValue2Text($column->options);

        // Assert
        $expectedConvertedValue = json_encode(['Option 1', 'Option 2', 'Option 1'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertEquals($expectedConvertedValue, $convertedValue);
    }
}

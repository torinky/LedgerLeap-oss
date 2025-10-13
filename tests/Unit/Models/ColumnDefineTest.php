<?php

namespace Tests\Unit\Models;

use App\Models\ColumnDefine;
use App\Models\ColumnTypes\InputTypeFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColumnDefineTest extends TestCase
{
    public function test_object_initialization(): void
    {
        // テスト用のオブジェクトを作成
        $data = (object) [
            'id' => 1,
            'name' => 'column1',
            'type' => 'text',
            'order' => 2,
            'options' => ['option1', 'option2'],
            'required' => true,
            'unique' => false,
            'sortBy' => true,
            'hint' => '',
            'file' => [],
        ];

        // オブジェクトによる初期化のテスト
        $column = new ColumnDefine($data);

        // オブジェクトのプロパティが期待通りにセットされているかテスト
        $this->assertEquals($data->id, $column->id);
        $this->assertEquals($data->name, $column->name);
        $this->assertEquals($data->type, $column->getType());
        $this->assertEquals($data->order, $column->order);
        $this->assertFalse($column->useOptions);
        $this->assertEquals($data->options, $column->options);
        $this->assertEquals($data->required, $column->required);
        $this->assertEquals($data->unique, $column->unique);
        $this->assertEquals($data->sortBy, $column->sortBy);
        $this->assertEquals($data->hint, $column->hint);
        $this->assertEquals($data->file, $column->file);

        // 新しいプロパティのデフォルト値を確認
        $this->assertEquals(3, $column->display_level);
        $this->assertNull($column->group);
    }

    /**
     * ColumnDefineのインスタンスを生成するための引数を指定し、期待通りの値がセットされているかテスト
     */
    #[Test]
    public function test_value_initialization(): void
    {
        // 値による初期化のテスト
        $column = new ColumnDefine(2, 'column2', 'select', 1, ['option1', 'option2'], false, true, false, 'hint text', []);
        // オブジェクトのプロパティが期待通りにセットされているかテスト
        $this->assertEquals(2, $column->id);
        $this->assertEquals('column2', $column->name);
        $this->assertEquals('select', $column->getType());
        $this->assertEquals(1, $column->order);
        $this->assertTrue($column->useOptions);
        $this->assertEquals(['option1', 'option2'], $column->options);
        $this->assertFalse($column->required);
        $this->assertTrue($column->unique);
        $this->assertEquals('hint text', $column->hint);
        $this->assertFalse($column->sortBy);

        // 新しいプロパティのデフォルト値を確認
        $this->assertEquals(3, $column->display_level);
        $this->assertNull($column->group);
    }

    #[Test]
    public function test_value_initialization_with_display_level_and_group(): void
    {
        // display_levelとgroupを指定した初期化のテスト
        $column = new ColumnDefine(
            3, 'column3', 'text', 2, [], true, false, false, 'test hint', [],
            1, // display_level
            '基本情報' // group
        );

        $this->assertEquals(3, $column->id);
        $this->assertEquals('column3', $column->name);
        $this->assertEquals(1, $column->display_level);
        $this->assertEquals('基本情報', $column->group);
    }

    /**
     * 'text'→'textarea'、'textarea'→'chk'、無効な列の種類を設定しようとする場合をテスト
     */
    #[Test]
    public function test_set_type(): void
    {
        // 列の種類を変更するテスト
        $column = new ColumnDefine(3, 'column3', 'text');

        $column->setType('textarea');
        $this->assertEquals('textarea', $column->getType());
        $this->assertFalse($column->useOptions);

        $column->setType('chk');
        $this->assertEquals('chk', $column->getType());
        $this->assertTrue($column->useOptions);

        // 無効な列の種類を設定しようとする場合のテスト
        $this->expectException(InvalidArgumentException::class);
        $column->setType('invalid_type');
    }

    /**
     * ColumnDefine::typeLabels()で取得できるラベルに期待する値があるかテスト
     */
    #[Test]
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
     * setOptions()に空の配列を渡すと、$optionsが空の配列になることをテスト
     */
    #[Test]
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
     * 'files'タイプの列に配列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    #[Test]
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
     * 'chk'タイプの列に配列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    #[Test]
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
     * 'YMD'タイプの列に日付文字列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    #[Test]
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
     * 'number'タイプの列に数値を設定し、convertColumnValue2Text()を実行することで、
     * 期待する文字列が生成されるかテスト
     */
    #[Test]
    public function test_column_type_conversion_for_number_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'number', 1);
        $numberValue = 12345;

        // Act
        $convertedValue = $column->convertColumnValue2Text($numberValue);

        // Assert
        $this->assertEquals('12345', (string) $convertedValue);
    }

    /**
     * 'text'タイプの列に文字列を設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    #[Test]
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
     * 'textarea'タイプの列に複数行テキストを設定し、convertColumnValue2Text()を実行することで、期待する文字列が生成されるかテスト
     */
    #[Test]
    public function test_column_type_conversion_for_text_area_type()
    {
        // Arrange
        $column = new ColumnDefine(1, 'column1', 'textarea', 1);
        $textareaValue = "Hello, World!\nThis is a multi-line text.";

        // Act
        $convertedValue = $column->convertColumnValue2Text($textareaValue);

        // Assert
        $this->assertEquals("Hello, World!\nThis is a multi-line text.", $convertedValue);
    }

    /**
     * 'select'タイプの列に選択肢を設定し、convertColumnValue2Text()を実行することで、
     * 選択された値が文字列に変換されるかテスト
     */
    #[Test]
    public function test_column_type_conversion_for_select_type()
    {
        // Arrange
        $column = new ColumnDefine(0, 'test', 'select');
        $column->setOptions(['Option 1', 'Option 2', 'Option 3']);
        $selectedValue = 'Option 2';

        // Act
        $convertedValue = $column->convertColumnValue2Text($selectedValue);

        // Assert
        $this->assertEquals('Option 2', $convertedValue);
    }

    // test_column_type_conversion_for_invalid_type is removed as direct type setting is invalid.
    // Type validation happens at instantiation/setType via InputTypeFactory.

    /**
     * 'textarea'タイプの列に複数行テキストを設定し、convertColumnValue2Text()を実行することで、
     * 期待する文字列が生成されるかテスト
     */
    #[Test]
    public function test_column_type_conversion_for_text_area_type_with_multiple_lines()
    {
        // Arrange
        $column = new ColumnDefine(0, 'test', 'textarea');
        $textareaValue = "Hello, World!\nThis is a multi-line text.";

        // Act
        $convertedValue = $column->convertColumnValue2Text($textareaValue);

        // Assert
        $this->assertEquals("Hello, World!\nThis is a multi-line text.", $convertedValue);
    }

    /**
     * setOptions()に空の配列を渡すと、$optionsが空の配列になることをテスト
     */
    #[Test]
    public function test_set_options_with_empty_array()
    {
        // Arrange
        $column = new ColumnDefine(0, 'test', 'select');
        $column->options = [];

        // Act
        $column->setOptions([]);

        // Assert
        $this->assertEquals([], $column->options);
    }

    /**
     * required/uniqueフラグをON/OFFすることで、ColumnDefineのプロパティが
     * 正しく更新されるかテスト
     */
    #[Test]
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
     * 'chk'タイプの列に重複する値がある場合、convertColumnValue2Text()を実行することで、
     * そのままの文字列が生成されるかテスト
     */
    #[Test]
    public function test_column_type_conversion_for_chk_type_with_duplicate_options()
    {
        // Arrange
        $column = new ColumnDefine(0, 'test', 'chk');
        $chkValue = ['option1', 'option2', 'option1'];

        // Act
        $convertedValue = $column->convertColumnValue2Text($chkValue);

        // Assert
        $expectedConvertedValue = json_encode(['option1', 'option2', 'option1'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertEquals($expectedConvertedValue, $convertedValue);
    }

    // New tests for restoreColumnValueFromText

    #[Test]
    public function test_restore_column_value_for_text_type()
    {
        $column = new ColumnDefine(1, 'test_text', 'text');
        $textValue = 'Hello World';
        $restoredValue = $column->restoreColumnValueFromText($textValue);
        $this->assertEquals('Hello World', $restoredValue);
    }

    #[Test]
    public function test_restore_column_value_for_textarea_type()
    {
        $column = new ColumnDefine(1, 'test_textarea', 'textarea');
        $textValue = "Hello World\nNew Line";
        $restoredValue = $column->restoreColumnValueFromText($textValue);
        $this->assertEquals("Hello World\nNew Line", $restoredValue);
    }

    #[Test]
    public function test_restore_column_value_for_number_type()
    {
        $column = new ColumnDefine(1, 'test_number', 'number');
        $textValue = '12345';
        $restoredValue = $column->restoreColumnValueFromText($textValue);
        $this->assertEquals(12345, $restoredValue);

        $textValueFloat = '123.45';
        $restoredValueFloat = $column->restoreColumnValueFromText($textValueFloat);
        $this->assertEquals(123.45, $restoredValueFloat);

        $textValueNonNumeric = 'abc';
        $restoredValueNonNumeric = $column->restoreColumnValueFromText($textValueNonNumeric);
        $this->assertEquals('abc', $restoredValueNonNumeric);
    }

    #[Test]
    public function test_restore_column_value_for_chk_type()
    {
        $column = new ColumnDefine(1, 'test_chk', 'chk');
        $jsonValue = '["option1","option2"]';
        $restoredValue = $column->restoreColumnValueFromText($jsonValue);
        $this->assertEquals(['option1', 'option2'], $restoredValue);

        // Test invalid JSON
        $invalidJsonValue = '["option1","option2"';
        $restoredInvalid = $column->restoreColumnValueFromText($invalidJsonValue);
        $this->assertNull($restoredInvalid);
    }

    #[Test]
    public function test_restore_column_value_for_select_type()
    {
        $column = new ColumnDefine(1, 'test_select', 'select');
        $textValue = 'selected_option';
        $restoredValue = $column->restoreColumnValueFromText($textValue);
        $this->assertEquals('selected_option', $restoredValue);
    }

    #[Test]
    public function test_restore_column_value_for_ymd_type()
    {
        $column = new ColumnDefine(1, 'test_ymd', 'YMD');
        $textValue = '2023-10-26';
        $restoredValue = $column->restoreColumnValueFromText($textValue);
        $this->assertEquals(strtotime('2023-10-26'), $restoredValue);

        $emptyValue = '';
        $restoredEmpty = $column->restoreColumnValueFromText($emptyValue);
        $this->assertNull($restoredEmpty);

        $invalidDate = 'not-a-date';
        $restoredInvalidDate = $column->restoreColumnValueFromText($invalidDate);
        $this->assertNull($restoredInvalidDate);
    }

    #[Test]
    public function test_ymd_type_with_default_offset()
    {
        // default_offsetをoptionsで指定
        $column = new ColumnDefine(1, 'test_ymd', 'YMD', 1, ['default_offset' => '0d']);

        // DateTypeインスタンスにdefault_offsetが渡されることを確認
        $dateType = $column->getInputType();
        $this->assertInstanceOf(\App\Models\ColumnTypes\DateType::class, $dateType);

        // デフォルト日付が取得できることを確認
        $defaultDate = $dateType->getDefaultDate();
        $this->assertNotNull($defaultDate);
        $this->assertEquals(date('Y-m-d'), $defaultDate);
    }

    #[Test]
    public function test_restore_column_value_for_files_type()
    {
        $column = new ColumnDefine(1, 'test_files', 'files');
        $jsonValue = '[{"name":"file1.jpg","path":"path/to/file1.jpg"},{"name":"file2.png","path":"path/to/file2.png"}]';
        $expectedArray = [
            ['name' => 'file1.jpg', 'path' => 'path/to/file1.jpg'],
            ['name' => 'file2.png', 'path' => 'path/to/file2.png'],
        ];
        $restoredValue = $column->restoreColumnValueFromText($jsonValue);
        $this->assertEquals($expectedArray, $restoredValue);

        // Test invalid JSON
        $invalidJsonValue = '[{"name":"file1.jpg"';
        $restoredInvalid = $column->restoreColumnValueFromText($invalidJsonValue);
        $this->assertNull($restoredInvalid);

        // Test already array (should pass through)
        $arrayValue = [['name' => 'file3.txt']];
        $restoredArray = $column->restoreColumnValueFromText($arrayValue);
        $this->assertEquals($arrayValue, $restoredArray);
    }

    #[Test]
    public function test_use_options_behavior_across_types()
    {
        $allTypes = InputTypeFactory::getAllTypes();
        foreach ($allTypes as $typeIdentifier => $inputTypeInstance) {
            $column = new ColumnDefine(1, 'test_col', $typeIdentifier);
            $this->assertEquals($inputTypeInstance->hasOptions(), $column->useOptions, "Failed for type: {$typeIdentifier}");
        }
    }

    #[Test]
    public function test_column_define_initializes_with_default_display_level_and_group()
    {
        $data = (object) [
            'id' => 1,
            'name' => 'Test Column',
            'type' => 'text',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sortBy' => false,
            'hint' => '',
            'file' => [],
        ];

        $column = new ColumnDefine($data);

        $this->assertEquals(3, $column->display_level);
        $this->assertNull($column->group);
    }

    #[Test]
    public function test_column_define_initializes_with_specified_display_level_and_group()
    {
        $data = (object) [
            'id' => 2,
            'name' => 'Another Column',
            'type' => 'number',
            'order' => 2,
            'options' => [],
            'required' => true,
            'unique' => false,
            'sortBy' => false,
            'hint' => '',
            'file' => [],
            'display_level' => 1,
            'group' => 'Basic Info',
        ];

        $column = new ColumnDefine($data);

        $this->assertEquals(1, $column->display_level);
        $this->assertEquals('Basic Info', $column->group);
    }

    #[Test]
    public function test_column_define_handles_existing_data_without_new_properties()
    {
        // Simulate old data structure (array without display_level or group)
        $oldData = [
            'id' => 3,
            'name' => 'Old Column',
            'type' => 'textarea',
            'order' => 3,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sortBy' => false,
            'hint' => '',
            'file' => [],
        ];

        // Test with object conversion
        $columnObject = new ColumnDefine((object) $oldData);
        $this->assertEquals(3, $columnObject->display_level);
        $this->assertNull($columnObject->group);

        // Test with array conversion (via normalizeArrayOrCollection)
        $normalizedData = ColumnDefine::normalizeArrayOrCollection([$oldData]);
        $this->assertArrayHasKey(3, $normalizedData);
        $this->assertEquals(3, $normalizedData[3]['display_level']);
        $this->assertNull($normalizedData[3]['group']);
    }
}

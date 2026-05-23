<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\AutoNumberType;
use App\Models\ColumnTypes\CheckboxType;
use App\Models\ColumnTypes\DateType;
use App\Models\ColumnTypes\FilesType;
use App\Models\ColumnTypes\InputType;
use App\Models\ColumnTypes\InputTypeFactory;
use App\Models\ColumnTypes\NumberType;
use App\Models\ColumnTypes\PhoneNumberType;
use App\Models\ColumnTypes\SelectType;
use App\Models\ColumnTypes\TextareaType;
use App\Models\ColumnTypes\TextType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * InputTypeFactory のテスト
 *
 * make() の各タイプ生成、DateType の typeIdentifier 渡し、
 * 不正タイプでの例外、getAllTypes()、getTypeIdentifiers() を検証。
 */
#[CoversClass(InputTypeFactory::class)]
class InputTypeFactoryTest extends TestCase
{
    // ================================================================
    // make() — 各タイプ生成
    // ================================================================

    #[Test]
    public function make_creates_text_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'text']);
        $this->assertInstanceOf(TextType::class, $type);
    }

    #[Test]
    public function make_creates_textarea_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'textarea']);
        $this->assertInstanceOf(TextareaType::class, $type);
    }

    #[Test]
    public function make_creates_number_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'number', 'options' => ['min' => 0]]);
        $this->assertInstanceOf(NumberType::class, $type);
        $this->assertEquals(0.0, $type->min);
    }

    #[Test]
    public function make_creates_auto_number_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'auto_number', 'options' => ['prefix' => 'INV-']]);
        $this->assertInstanceOf(AutoNumberType::class, $type);
        $this->assertEquals('INV-', $type->prefix);
    }

    #[Test]
    public function make_creates_checkbox_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'chk']);
        $this->assertInstanceOf(CheckboxType::class, $type);
    }

    #[Test]
    public function make_creates_select_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'select', 'options' => ['a', 'b']]);
        $this->assertInstanceOf(SelectType::class, $type);
    }

    #[Test]
    public function make_creates_ymd_date_type_with_correct_identifier(): void
    {
        $type = InputTypeFactory::make(['type' => 'YMD']);
        $this->assertInstanceOf(DateType::class, $type);
        $this->assertEquals('YMD', $type->getName());
    }

    #[Test]
    public function make_creates_ymdhm_date_type_with_correct_identifier(): void
    {
        $type = InputTypeFactory::make(['type' => 'YMDHM']);
        $this->assertInstanceOf(DateType::class, $type);
        $this->assertEquals('YMDHM', $type->getName());
    }

    #[Test]
    public function make_creates_files_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'files']);
        $this->assertInstanceOf(FilesType::class, $type);
    }

    #[Test]
    public function make_creates_phone_type(): void
    {
        $type = InputTypeFactory::make(['type' => 'phone']);
        $this->assertInstanceOf(PhoneNumberType::class, $type);
    }

    #[Test]
    public function make_defaults_to_text_when_type_missing(): void
    {
        $type = InputTypeFactory::make([]);
        $this->assertInstanceOf(TextType::class, $type);
    }

    #[Test]
    public function make_throws_exception_for_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputTypeFactory::make(['type' => 'unknown_type']);
    }

    // ================================================================
    // getAllTypes()
    // ================================================================

    #[Test]
    public function get_all_types_returns_all_registered_types(): void
    {
        $types = InputTypeFactory::getAllTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('text', $types);
        $this->assertArrayHasKey('textarea', $types);
        $this->assertArrayHasKey('number', $types);
        $this->assertArrayHasKey('auto_number', $types);
        $this->assertArrayHasKey('chk', $types);
        $this->assertArrayHasKey('select', $types);
        $this->assertArrayHasKey('YMD', $types);
        $this->assertArrayHasKey('YMDHM', $types);
        $this->assertArrayHasKey('files', $types);
        $this->assertArrayHasKey('phone', $types);
    }

    #[Test]
    public function get_all_types_returns_input_type_instances(): void
    {
        $types = InputTypeFactory::getAllTypes();
        foreach ($types as $identifier => $type) {
            $this->assertInstanceOf(InputType::class, $type,
                "Type '{$identifier}' should implement InputType"
            );
        }
    }

    // ================================================================
    // getTypeIdentifiers()
    // ================================================================

    #[Test]
    public function get_type_identifiers_returns_array_of_strings(): void
    {
        $identifiers = InputTypeFactory::getTypeIdentifiers();

        $this->assertIsArray($identifiers);
        $this->assertContains('text', $identifiers);
        $this->assertContains('YMD', $identifiers);
        $this->assertContains('YMDHM', $identifiers);
    }
}

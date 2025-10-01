<?php

namespace Tests\Feature;

use App\Models\ColumnDefine;
use App\Models\LedgerDefine;
use App\Models\User; // Required by LedgerDefineFactory
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerDefineWithColumnDefinesTest extends TestCase
{
    use RefreshDatabase; // Ensures a clean database for each test

    protected bool $tenancy = true;

    /**
     * Test LedgerDefine creation, serialization, and deserialization of ColumnDefine objects.
     */
    #[Test]
    public function test_ledger_define_with_various_column_types_serialization_and_deserialization()
    {
        // Create a user for the factory
        User::factory()->create();

        // Use LedgerDefine::factory() to create an instance.
        // The factory should already include a variety of ColumnDefine types.
        $createdLedgerDefine = LedgerDefine::factory()->create();

        // Retrieve the LedgerDefine instance from the database.
        $retrievedLedgerDefine = LedgerDefine::find($createdLedgerDefine->id);

        $this->assertInstanceOf(LedgerDefine::class, $retrievedLedgerDefine);
        $this->assertInstanceOf(Collection::class, $retrievedLedgerDefine->column_define);
        $this->assertNotEmpty($retrievedLedgerDefine->column_define);

        // The factory creates ColumnDefine objects, which are then keyed by ID by the cast.
        $originalColumnDefines = collect($createdLedgerDefine->column_define)->keyBy('id');

        foreach ($retrievedLedgerDefine->column_define as $columnDefine) {
            $this->assertInstanceOf(ColumnDefine::class, $columnDefine);

            // Find the original column define for comparison using the ID as the key.
            $originalColumn = $originalColumnDefines->get($columnDefine->id);

            $this->assertNotNull($originalColumn, "Original column with ID {$columnDefine->id} not found for comparison.");

            $this->assertEquals($originalColumn->id, $columnDefine->id);
            $this->assertEquals($originalColumn->name, $columnDefine->name);
            $this->assertEquals($originalColumn->getType(), $columnDefine->getType()); // Compare type string
            $this->assertEquals($originalColumn->order, $columnDefine->order);
            $this->assertEquals($originalColumn->options, $columnDefine->options);
            $this->assertEquals($originalColumn->required, $columnDefine->required);
            $this->assertEquals($originalColumn->unique, $columnDefine->unique);
            $this->assertEquals($originalColumn->sortBy, $columnDefine->sortBy);
            $this->assertEquals($originalColumn->hint, $columnDefine->hint);
            $this->assertEquals($originalColumn->file, $columnDefine->file); // file property
            $this->assertEquals($originalColumn->useOptions, $columnDefine->useOptions);
            $this->assertEquals($originalColumn->getInputType()->hasOptions(), $columnDefine->useOptions);
        }
    }

    /**
     * Test data integrity round-trip for ColumnDefine values.
     */
    #[Test]
    public function test_column_value_conversion_round_trip_via_ledger_define()
    {
        // Create a user for the factory
        User::factory()->create();

        // Define a set of ColumnDefine objects with diverse types
        $columnDefines = [
            new ColumnDefine(0, 'Text Column', 'text', 1, [], false, false, false, 'Hint1', []),
            new ColumnDefine(1, 'Checkbox Column', 'chk', 2, ['opt1', 'opt2'], true, false, true, 'Hint2', []),
            new ColumnDefine(2, 'Files Column', 'files', 3, [], false, true, false, 'Hint3', []),
            new ColumnDefine(3, 'Number Column', 'number', 4, [], true, true, true, 'Hint4', []),
            new ColumnDefine(4, 'Select Column', 'select', 5, ['s1', 's2', 's3'], false, false, false, 'Hint5', []),
            new ColumnDefine(5, 'Date Column', 'YMD', 6, [], true, false, true, 'Hint6', []),
            new ColumnDefine(6, 'Textarea Column', 'textarea', 7, [], false, false, false, 'Hint7', []),
        ];

        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines]);
        $retrievedLedgerDefine = LedgerDefine::find($ledgerDefine->id);

        $testData = [
            'text' => ['hello world', '', null],
            'chk' => [['opt1'], [], ['opt1', 'opt2'], null],
            'files' => [[['name' => 'f1.jpg'], ['name' => 'f2.png']], [], null],
            'number' => [123, 0, 123.45, null, 'not-a-number-string'], // NumberType restores non-numeric strings as is
            'select' => ['s1', '', null],
            'YMD' => [strtotime('2023-01-01'), null, 'invalid-date-string'], // YMD stores timestamp
            'textarea' => ["multi\nline", '', null],
        ];

        foreach ($retrievedLedgerDefine->column_define as $column) {
            $this->assertInstanceOf(ColumnDefine::class, $column);
            $type = $column->getType();

            if (! isset($testData[$type])) {
                $this->fail("Test data for type '{$type}' not defined.");
            }

            foreach ($testData[$type] as $originalData) {
                // Special handling for YMD as it expects a string date for conversion,
                // but restoreFromString returns a timestamp which is then used as originalData here.
                $dataToConvert = $originalData;
                if ($type === 'YMD' && is_numeric($originalData)) {
                    // Convert timestamp back to 'Y-m-d' string for convertColumnValue2Text
                    $dataToConvert = date('Y-m-d', $originalData);
                } elseif ($type === 'YMD' && $originalData === 'invalid-date-string') {
                    // DateType::convertToText will try strtotime, then return original string if invalid
                    // DateType::restoreFromString will return null for 'invalid-date-string'
                    // So, the round trip for 'invalid-date-string' will be 'invalid-date-string' -> null
                    // We need to adjust the assertion for this specific case.
                }

                $textValue = $column->convertColumnValue2Text($dataToConvert);
                $restoredData = $column->restoreColumnValueFromText($textValue);

                if ($type === 'YMD' && $originalData === 'invalid-date-string') {
                    $this->assertNull($restoredData, "Round trip failed for type {$type} with data: ".print_r($originalData, true));
                } elseif ($type === 'number' && $originalData === 'not-a-number-string') {
                    $this->assertEquals($originalData, $restoredData, "Round trip failed for type {$type} with data: ".print_r($originalData, true));
                } else {
                    $this->assertEquals($originalData, $restoredData, "Round trip failed for type {$type} with data: ".print_r($originalData, true));
                }
            }
        }
    }

    /**
     * Test the custom PhoneNumberType for extensibility.
     */
    #[Test]
    public function test_custom_phone_number_type_extensibility()
    {
        // 1. Create a ColumnDefine instance with the new 'phone' type
        $phoneColumn = new \App\Models\ColumnDefine(10, 'Contact Phone', 'phone', 1);

        // 2. Verify its properties
        $this->assertEquals('phone', $phoneColumn->getType());
        $this->assertInstanceOf(\App\Models\ColumnTypes\PhoneNumberType::class, $phoneColumn->getInputType());
        $this->assertEquals('Phone Number', $phoneColumn->getInputType()->getLabel());
        $this->assertFalse($phoneColumn->useOptions);
        $this->assertFalse($phoneColumn->getInputType()->hasOptions()); // Double check via inputType

        // 3. Test its convertToText and restoreFromString methods
        $originalPhone = '(123) 456-7890 ext. 123';
        $converted = $phoneColumn->convertColumnValue2Text($originalPhone);
        // PhoneNumberType's convertToText removes non-numeric characters
        $this->assertEquals('1234567890123', $converted);

        $restored = $phoneColumn->restoreColumnValueFromText($converted);
        // PhoneNumberType's restoreFromString currently returns the string as is
        $this->assertEquals('1234567890123', $restored);

        // Test with a phone number that is already just digits
        $originalCleanPhone = '0987654321';
        $convertedClean = $phoneColumn->convertColumnValue2Text($originalCleanPhone);
        $this->assertEquals('0987654321', $convertedClean);
        $restoredClean = $phoneColumn->restoreColumnValueFromText($convertedClean);
        $this->assertEquals('0987654321', $restoredClean);

        // 4. Create a LedgerDefine that includes this custom column type
        // Ensure a User exists for the factory
        if (User::count() === 0) {
            User::factory()->create();
        }

        $columnDefines = [
            new \App\Models\ColumnDefine(1, 'Text Column', 'text', 1), // Existing type
            $phoneColumn, // Our new phone type
        ];

        /** @var LedgerDefine $ledgerDefine */
        $ledgerDefine = LedgerDefine::factory()->create(['column_define' => $columnDefines]);

        // 5. Retrieve the LedgerDefine and verify the custom column
        /** @var LedgerDefine $retrievedLedgerDefine */
        $retrievedLedgerDefine = LedgerDefine::find($ledgerDefine->id);
        $this->assertInstanceOf(LedgerDefine::class, $retrievedLedgerDefine);
        $this->assertInstanceOf(Collection::class, $retrievedLedgerDefine->column_define);

        $retrievedPhoneColumn = null;
        foreach ($retrievedLedgerDefine->column_define as $col) {
            $this->assertInstanceOf(\App\Models\ColumnDefine::class, $col);
            if ($col->getType() === 'phone') {
                $retrievedPhoneColumn = $col;
                break;
            }
        }

        $this->assertNotNull($retrievedPhoneColumn, "The 'phone' type column was not found in the retrieved LedgerDefine.");
        $this->assertEquals('Contact Phone', $retrievedPhoneColumn->name);
        $this->assertEquals(10, $retrievedPhoneColumn->id); // Ensure ID is preserved
        $this->assertEquals(1, $retrievedPhoneColumn->order); // Ensure order is preserved
        $this->assertInstanceOf(\App\Models\ColumnTypes\PhoneNumberType::class, $retrievedPhoneColumn->getInputType());

        // Test the conversion methods on the retrieved column as well
        $retrievedConverted = $retrievedPhoneColumn->convertColumnValue2Text('(987) 654-3210');
        $this->assertEquals('9876543210', $retrievedConverted);
        $retrievedRestored = $retrievedPhoneColumn->restoreColumnValueFromText($retrievedConverted);
        $this->assertEquals('9876543210', $retrievedRestored);
    }
}

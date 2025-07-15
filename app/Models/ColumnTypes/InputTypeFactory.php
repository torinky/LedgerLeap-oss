<?php

namespace App\Models\ColumnTypes;

use InvalidArgumentException;

class InputTypeFactory
{
    private static $typeMap = [
        'text' => TextType::class,
        'textarea' => TextareaType::class,
        'number' => NumberType::class,
        'auto_number' => AutoNumberType::class,
        'chk' => CheckboxType::class,
        'select' => SelectType::class,
        'YMD' => DateType::class,
        'files' => FilesType::class,
        'phone' => PhoneNumberType::class, // New type
    ];

    /**
     * @throws InvalidArgumentException
     */
    public static function make(array $columnDefineArray): InputType
    {
        $typeIdentifier = $columnDefineArray['type'] ?? 'text';
        $options = $columnDefineArray['options'] ?? [];

        if (!isset(self::$typeMap[$typeIdentifier])) {
            throw new InvalidArgumentException("Invalid input type: {$typeIdentifier}");
        }

        $className = self::$typeMap[$typeIdentifier];
        return new $className($options);
    }

    public static function getAllTypes(): array
    {
        $types = [];
        foreach (self::$typeMap as $identifier => $className) {
            $types[$identifier] = new $className();
        }
        return $types;
    }

    public static function getTypeIdentifiers(): array
    {
        return array_keys(self::$typeMap);
    }
}

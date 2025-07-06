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
    public static function make(string $typeIdentifier): InputType
    {
        if (!isset(self::$typeMap[$typeIdentifier])) {
            throw new InvalidArgumentException("Unknown input type identifier: {$typeIdentifier}");
        }
        $className = self::$typeMap[$typeIdentifier];
        return new $className();
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

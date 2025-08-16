<?php

namespace App\Models;

use App\Models\ColumnTypes\InputType;
use App\Models\ColumnTypes\InputTypeFactory;
use RuntimeException; // Keep for potential other runtime exceptions, though type validation is now in factory

class ColumnDefine
{
    // プロパティの定義
        public int $id;                 // ID

    public $name;               // 列名

    public $type;               // 列の種類 (string representation, e.g., 'text')

    private InputType $inputType; // The actual InputType object

    public $order;              // 列の表示順序

    public $useOptions;         // オプション使用フラグ

    public $options;            // オプション値の配列

    public $required;           // 必須項目フラグ

    public $unique;     // 重複不可フラグ

    public $sortBy;             // ソート対象フラグ

    public $hint;

    public $file = [];

    public int $display_level; // 追加: 表示レベル

    public ?string $group;      // 追加: グループ名

    /**
     * コンストラクタ
     *
     * @param object|array $inObject
     *
     * 引数の数に応じてオブジェクトによる初期化か値による初期化かを振り分ける
     */
    public function __construct($inObject = null)
    {
        if (is_object($inObject)) {
            $this->constructByObject($inObject);
        } elseif (is_array($inObject)) {
            $this->constructByObject((object) $inObject);
        } elseif (func_num_args() > 1) {
            $this->constructByArgs(...func_get_args());
        }
    }

    /**
     * オブジェクトによる初期化
     *
     * @param object $inObject
     * @return void
     */
    public function constructByObject($inObject)
    {
        $this->id = (int)$inObject->id;
        $this->setName($inObject->name);
        $this->setOrder($inObject->order);
        $this->setOptions((array)($inObject->options ?? []));
        $this->setRequired($inObject->required ?? false);
        $this->setUnique($inObject->unique ?? false);
        $this->setSortBy($inObject->sortBy ?? false);
        $this->setHint($inObject->hint ?? '');
        $this->setFile($inObject->file ?? []);

        // 新しいプロパティの初期化
        $this->display_level = (int)($inObject->display_level ?? 3); // デフォルト値は3
        $this->group = $inObject->group ?? null; // デフォルト値はnull

        $this->initializeType((array) $inObject);
    }

    /**
     * 値による初期化
     *
     * @return void
     */
    public function constructByArgs(
        int    $id,
        string $name = '',
        string $typeIdentifier = 'text',
        int    $order = 1,
        array $options = [],
        bool $required = false,
        bool $unique = false,
        bool   $sortBy = false,
        string $hint = '',
        array $file = []
    )
    {
        $this->id = (int)$id;
        $this->setName($name);
        $this->setOrder($order);
        $this->setOptions($options);
        $this->setRequired($required);
        $this->setUnique($unique);
        $this->setSortBy($sortBy);
        $this->setHint($hint);
        $this->setFile($file);

        // 新しいプロパティの初期化 (constructByArgsではデフォルト値のみ)
        $this->display_level = 3;
        $this->group = null;

        $this->initializeType(['type' => $typeIdentifier, 'options' => $options]);
    }

    /**
     * Initializes the input type strategy object.
     * @param array $columnDefineArray
     * @throws \InvalidArgumentException
     */
    private function initializeType(array $columnDefineArray): void
    {
        $this->inputType = InputTypeFactory::make($columnDefineArray);
        $this->type = $this->inputType->getName(); // Update public type property
        $this->useOptions = $this->inputType->hasOptions(); // Update useOptions based on type
    }

    /**
     * 列の種類を設定する
     */
    public function setType(string $typeIdentifier): void
    {
        $this->initializeType(['type' => $typeIdentifier, 'options' => $this->options]);
    }

    /**
     * Get the string identifier of the column type.
     */
    public function getType(): string
    {
        return $this->inputType->getName();
    }

    /**
     * 列の種類のラベルを取得する
     *
     * @return array
     */
    public static function typeLabels()
    {
        $labels = [];
        $allTypes = InputTypeFactory::getAllTypes();
        foreach ($allTypes as $typeInstance) {
            $labels[$typeInstance->getName()] = $typeInstance->getLabel();
        }
        return $labels;
    }

    /**
     * カラム値を適切な形式に変換する
     *
     * @param mixed $columnValue
     * @return mixed
     */
    public function convertColumnValue2Text($columnValue)
    {
        return $this->inputType->convertToText($columnValue);
    }

    /**
     * カラム値を適切な形式に戻す
     *
     * @param string $convertedValue
     * @return mixed
     */
    public function restoreColumnValueFromText($convertedValue)
    {
        return $this->inputType->restoreFromString($convertedValue);
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options): void
    {
        $this->options = (array)$options;
    }

    /**
     * @param mixed $required
     */
    public function setRequired($required): void
    {
        $this->required = $required;
    }

    /**
     * @param mixed $unique
     */
    public function setUnique($unique): void
    {
        $this->unique = $unique;
    }

    /**
     * @param mixed $sortBy
     */
    public function setSortBy($sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    /**
     * @param mixed $hint
     */
    public function setHint($hint): void
    {
        $this->hint = $hint;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file): void
    {
        $this->file = $file;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    // Getter for the InputType object, if needed for advanced operations outside ColumnDefine
    public function getInputType(): InputType
    {
        return $this->inputType;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'order' => $this->order,
            'useOptions' => $this->useOptions,
            'options' => $this->options,
            'required' => $this->required,
            'unique' => $this->unique,
            'sortBy' => $this->sortBy,
            'hint' => $this->hint,
            'file' => $this->file,
            'display_level' => $this->display_level,
            'group' => $this->group,
        ];
    }

    public static function normalizeArrayOrCollection( $columnDefinesSource): array
    {
        $result = [];
        foreach ($columnDefinesSource as $colDef) {
            if (is_object($colDef)) {
                $result[$colDef->id] = [
                    'id' => $colDef->id,
                    'name' => $colDef->name,
                    'type' => $colDef->type,
                    'order' => $colDef->order ?? null,
                    'useOptions' => $colDef->useOptions ?? false,
                    'options' => isset($colDef->options) && is_array($colDef->options) ? $colDef->options : [],
                    'required' => $colDef->required ?? false,
                    'unique' => $colDef->unique ?? false,
                    'sortBy' => $colDef->sortBy ?? false,
                    'hint' => $colDef->hint ?? '',
                    'file' => isset($colDef->file) && is_array($colDef->file) ? $colDef->file : [],
                    'display_level' => $colDef->display_level ?? 3, // デフォルト値を追加
                    'group' => $colDef->group ?? null, // デフォルト値を追加
                ];
            } elseif (is_array($colDef) && isset($colDef['id'])) {
                // 配列の場合も同様にデフォルト値を適用
                $colDef['display_level'] = $colDef['display_level'] ?? 3;
                $colDef['group'] = $colDef['group'] ?? null;
                $result[$colDef['id']] = $colDef;
            }
        }
        return $result;
    }

}

<?php

namespace App\Models;

use App\Models\ColumnTypes\InputType;
use App\Models\ColumnTypes\InputTypeFactory;
use RuntimeException; // Keep for potential other runtime exceptions, though type validation is now in factory

class ColumnDefine
{
    // プロパティの定義
    public $id;                 // ID

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
        $this->initializeType($inObject->type ?? 'text'); // Default to 'text' if type is not set
        $this->setOrder($inObject->order);
        $this->setOptions($inObject->options ?? []);
        $this->setRequired($inObject->required);
        $this->setUnique($inObject->unique);
        $this->setSortBy($inObject->sortBy);
        $this->setHint($inObject->hint);
        $this->setFile($inObject->file);
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
        $this->initializeType($typeIdentifier);
        $this->setOrder($order);
        $this->setOptions($options);
        $this->setRequired($required);
        $this->setUnique($unique);
        $this->setSortBy($sortBy);
        $this->setHint($hint);
        $this->setFile($file);
    }

    /**
     * Initializes the input type strategy object.
     * @param string $typeIdentifier
     * @throws \InvalidArgumentException
     */
    private function initializeType(string $typeIdentifier): void
    {
        $this->inputType = InputTypeFactory::make($typeIdentifier);
        $this->type = $this->inputType->getName(); // Update public type property
        $this->useOptions = $this->inputType->hasOptions(); // Update useOptions based on type
    }

    /**
     * 列の種類を設定する
     */
    public function setType(string $typeIdentifier): void
    {
        $this->initializeType($typeIdentifier);
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
        // The original 'number' label was 'ledger.form.auto_numbering'
        // and 'YMD' was 'ledger.form.datetime', 'files' was 'ledger.form.upload'
        // The new types use 'ledger.form.number', 'ledger.form.date', 'ledger.form.files' respectively.
        // We need to ensure these specific labels are preserved if they are different.
        // Current InputType implementations use the new labels. If specific overrides are needed:
        // $labels['number'] = __('ledger.form.auto_numbering');
        // $labels['YMD'] = __('ledger.form.datetime');
        // $labels['files'] = __('ledger.form.upload');
        // For now, we assume the labels defined in each InputType are the desired ones.
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
        $this->options = $options;
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
}

<?php

namespace App\Models;

use RuntimeException;

class ColumnDefine
{
    // 列の種類に関する設定
    public static $useOptionsTypes = ['select', 'chk'];

    public static $types = [
        'number',       // 数値型
        'text',         // テキストボックス
        'textarea',     // テキストエリア
        'chk',          // チェックボックス
        'select',       // セレクトボックス
        'YMD',          // 日付（年月日）
        'files',        // ファイル選択
    ];

    public static $shouldConvert2JsonTypes = [
        'files',        // ファイル選択
        'chk',          // チェックボックス
    ];

    // プロパティの定義
    public $id;                 // ID

    public $name;               // 列名

    public $type;               // 列の種類

    public $order;              // 列の表示順序

    public $useOptions;         // オプション使用フラグ

    public $options;            // オプション値の配列

    public $required;           // 必須項目フラグ

    public $unique;     // 重複不可フラグ

    public $sortBy;             // ソート対象フラグ

    public $hint;

    public $file;

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
        $this->setType($inObject->type);
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
        string $type = 'text',
        int    $order = 1,
        array $options = [],
        bool $required = false,
        bool $unique = false,
        bool   $sortBy = false,
        string $hint = '',
        string $file = ''
    )
    {
        $this->id = (int)$id;
        $this->setName($name);
        $this->setType($type);
        $this->setOrder($order);
        $this->setOptions($options);
        $this->setRequired($required);
        $this->setUnique($unique);
        $this->setSortBy($sortBy);
        $this->setHint($hint);
        $this->setFile($file);
    }

    /**
     * 列の種類を設定する
     */
    public function setType(string $type): void
    {
        if (!in_array($type, self::$types)) {
            throw new RuntimeException('無効な列の種類');
        }
        $this->type = $type;
        $this->initUseOptions();
    }

    /**
     * オプション使用フラグを初期化する
     */
    private function initUseOptions(): void
    {
        $this->useOptions = in_array($this->type, self::$useOptionsTypes);
    }

    /**
     * 列の種類のラベルを取得する
     *
     * @return array
     */
    public static function typeLabels()
    {
        return [
            'number' => __('ledger.form.auto_numbering'), // 自動採番
            'text' => __('ledger.form.text'), // テキストボックス
            'textarea' => __('ledger.form.textarea'), // テキストエリア
            'chk' => __('ledger.form.check'), // チェックボックス
            'select' => __('ledger.form.select'), // セレクトボックス
            'YMD' => __('ledger.form.datetime'), // 日付（年月日）
            'files' => __('ledger.form.upload'), // ファイル選択
        ];
    }

    /**
     * カラム値を適切な形式に変換する
     *
     * @param mixed $columnValue
     * @return mixed
     */
    public function convertColumnValue2Text($columnValue)
    {
        if (!empty($columnValue) && in_array($this->type, self::$shouldConvert2JsonTypes)) {
            return json_encode($columnValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $columnValue;
    }

    /**
     * カラム値を適切な形式に戻す
     *
     * @param string $convertedValue
     * @return mixed
     */
    public function restoreColumnValueFromText($convertedValue)
    {
        if (in_array($this->type, self::$shouldConvert2JsonTypes)) {
            $decodedValue = json_decode($convertedValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedValue;
            }
        }

        return $convertedValue;
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
}

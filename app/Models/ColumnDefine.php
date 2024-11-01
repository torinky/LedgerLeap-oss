<?php

namespace App\Models;

use Exception;
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

    public $doNotDuplicate;     // 重複不可フラグ

    public $sortBy;             // ソート対象フラグ

    /**
     * コンストラクタ
     *
     * 引数の数に応じてオブジェクトによる初期化か値による初期化かを振り分ける
     */
    public function __construct()
    {
        $a = func_get_args();
        $i = func_num_args();
        if ($i == 1) {
            call_user_func_array([$this, 'constructByObject'], $a);

            return;
        }
        if ($i > 1) {
            call_user_func_array([$this, 'constructByArgs'], $a);

            return;
        }
        throw new Exception('無効な引数');
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
        $this->name = $inObject->name;
        $this->type = $inObject->type;
        $this->order = $inObject->order;
        $this->initUseOptions();
        $this->options = $inObject->options ?? [];
        $this->required = $inObject->required;
        $this->doNotDuplicate = $inObject->doNotDuplicate;
        $this->sortBy = $inObject->sortBy;
    }

    /**
     * 値による初期化
     *
     * @return void
     */
    public function constructByArgs(
        int   $id,
        string $name,
        string $type = 'text',
        int   $order = 1,
        array $options = [],
        bool  $required = false,
        bool  $doNotDuplicate = false,
        bool  $sortBy = false
    )
    {
        $this->id = (int)$id;
        $this->name = $name;
        $this->type = $type;
        $this->order = $order;
        $this->initUseOptions();
        $this->options = $options;
        $this->required = $required;
        $this->doNotDuplicate = $doNotDuplicate;
        $this->sortBy = $sortBy;
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
}

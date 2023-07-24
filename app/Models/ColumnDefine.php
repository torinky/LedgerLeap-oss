<?php

namespace App\Models;

use Exception;
use RuntimeException;

class ColumnDefine
{
    // 列の種類に関する設定
    static $useOptionsTypes = ['select', 'chk'];
    static $types = [
        "number",       // 数値型
        "text",         // テキストボックス
        "textarea",     // テキストエリア
        "chk",          // チェックボックス
        "select",       // セレクトボックス
        "YMD",          // 日付（年月日）
        'files',        // ファイル選択
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
    function __construct()
    {
        $a = func_get_args();
        $i = func_num_args();
        if ($i == 1) {
            call_user_func_array(array($this, '__constructByObject'), $a);
            return;
        }
        if ($i > 1) {
            call_user_func_array(array($this, '__constructByArgs'), $a);
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
    public function __constructByObject($inObject)
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
     * @param int $id
     * @param string $name
     * @param string $type
     * @param int $order
     * @param array $options
     * @param bool $required
     * @param bool $doNotDuplicate
     * @param bool $sortBy
     * @return void
     */
    public function __constructByArgs(
        int    $id,
        string $name,
        string $type = 'text',
        int    $order = 1,
        array  $options = [],
        bool   $required = false,
        bool   $doNotDuplicate = false,
        bool   $sortBy = false
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
     *
     * @param string $type
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
     *
     * @return void
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
    static function typeLabels()
    {
        return [
            "number" => __('auto numbering'), // 自動採番
            "text" => __('text box'), // テキストボックス
            "textarea" => __('textarea'), // テキストエリア
            "chk" => __('check box'), // チェックボックス
            "select" => __('select box'), // セレクトボックス
            "YMD" => __('date Y-M-D'), // 日付（年月日）
            "files" => __('select file'), // ファイル選択
        ];
    }
}

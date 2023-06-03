<?php

namespace App\Models;

use Exception;
use RuntimeException;

class ColumnDefine
{
    static $useOptionsTypes = ['select', 'chk'];
    static $types = [
        "number",
        "text",
        "chk",
        "select",
        "YMD",
        'files',
    ];
    public $id;
    public $name;
    public $type;
    public $order;
    public $useOptions;
    public $options;
    public $required;
    public $sortBy;
    public $doNotDuplicate;


    function __construct()
    {
        $a = func_get_args();
        $i = func_num_args();
        if ($i == 1) {
            call_user_func_array(array($this, '_constructByObject'), $a);
            return;
        }
        if ($i > 1) {
            call_user_func_array(array($this, '_constructByArgs'), $a);
            return;
        }
        throw new Exception('invalid args');
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $type
     * @param int $order
     * @param array $options
     * @param bool $required
     */
    public function _constructByArgs(
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
     * @param object $inObject
     * @return void
     */
    public function _constructByObject($inObject)
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
     * @param string $type
     */
    public function setType(string $type): void
    {
        if (!in_array($type, self::$types)) {
            throw new RuntimeException('invalid type');
        }
        $this->type = $type;
        $this->initUseOptions();
    }

    /*    public function getRawObject(): object
        {
            return (object)[
                'id' => $this->id,
                'name' => $this->name,
                'type' => $this->type,
                'order' => $this->order,
                'useOptions' => $this->useOptions,
                'options' => $this->options,
                'required' => $this->required
            ];
        }*/

    /**
     * @return void
     */
    private function initUseOptions(): void
    {
        $this->useOptions = in_array($this->type, self::$useOptionsTypes);
    }


    /*    public function __get($name)
        {
            return $this->$name ?? null;
        }

        public function __set($name, $value)
        {
            if (isset($this->$name)) {
                $this->$name = $value;
            }
        }*/

    static function typeLabels()
    {
        return [
            "number" => __('auto numbering'),
            "text" => __('text box'),
            "chk" => __('check box'),
            "select" => __('select box'),
            "YMD" => __('date Y-M-D'),
            "files" => __('select file'),
        ];
    }
}

<?php

namespace App\Services\Ledger;

use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ColumnHtmlService
{
    private $attrs = [];
    private $columnDefine;

    private $valueNameBase = '';

    private $initialValue;

    private $asCreate = false;

    private $id = '';
    private $nameBase = '';

    private const BADGE_CLASS_NAME = 'badge badge-outline';
//    private string $orderNameBase;
//    private string $idNameBase;


    /**
     * @param ColumnDefine $columnDefine
     * @param $initialValue
     * @param $attrs
     * @param $idPrefix
     * @param $asCreate
     * @return HtmlString
     */
    public function show(ColumnDefine $columnDefine, $initialValue, $attrs = [], $idPrefix = '', $asCreate = false)
    {
//        $this->__construct($columnDefine, $initialValue, $attrs, $asCreate, $idPrefix);
        $this->mount($columnDefine, $initialValue, $attrs, $asCreate, $idPrefix);

        $html = '';
        if ($columnDefine->type == 'files' && is_array($this->initialValue)) {
            foreach ($this->initialValue as $originalFilename => $hashedFilename) {
                $url = Storage::url($hashedFilename);
//                画像ファイルか確認
                if (Storage::exists('public/Ledger/thumbs/' . basename($hashedFilename))) {
                    $thumbnailUrl = Storage::url('Ledger/thumbs/' . basename($hashedFilename));
                    $html .= <<<HTML
<a href="{$url}"><img class="m-1 rounded-lg shadow-xl" src="{$thumbnailUrl}" alt="{$originalFilename}"></a>
HTML;
                } else {
                    $html .= <<<HTML
<a href="{$url}">{$originalFilename}</a>
HTML;

                }
            }
//            dd($this->initialValue);
        } elseif ($this->columnDefine->useOptions && is_array($this->initialValue)) {
            $displayValues = array_filter($this->initialValue, 'strlen');
            if (!empty($displayValues)) {
                $html = '<span class="m-1 ' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="m-1 ' . self::BADGE_CLASS_NAME . '">', $displayValues) . '</span>' ?? '';
            }
        } else {
            $html = $this->initialValue;
        }

        return new HtmlString($html);
    }


    /**
     * @param ColumnDefine $columnDefine
     * @param $initialValue
     * @param array $attrs
     * @param bool $asCreate
     * @param string $idPrefix
     * @return void
     */
    public
    function mount(ColumnDefine $columnDefine, $initialValue, array $attrs = [], bool $asCreate = false, string $idPrefix = '')
    {
        $this->attrs = $attrs;
        $this->columnDefine = $columnDefine;

        $this->nameBase = "content[" . $columnDefine->id . "]";
//        $this->valueNameBase = $this->nameBase. "[value]";
        $this->valueNameBase = $this->nameBase;
//        $this->idNameBase = $this->nameBase. "[id]";
//        $this->orderNameBase = $this->nameBase. "[order]";
        $this->initialValue = $initialValue;
        $this->asCreate = $asCreate;
        $this->id = $idPrefix . $this->valueNameBase;
    }


}

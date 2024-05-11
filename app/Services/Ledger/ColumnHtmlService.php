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

    private const BADGE_CLASS_NAME = 'badge badge-secondary py-4 mx-1 my-1';

    private const HIGHLIGHT_CLASS_NAME = 'text-error font-bold text-lg';

    //    private string $orderNameBase;
    //    private string $idNameBase;
    private array $keywords = [];

    /**
     * @return HtmlString
     */
    public function show(ColumnDefine $columnDefine, $initialValue, $attrs = [], $idPrefix = '', $asCreate = false)
    {
        if ($columnDefine !== null) {
            $this->mount($columnDefine, $initialValue, $attrs, $asCreate, $idPrefix);
        }

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
                $html = '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', $displayValues) . '</span>' ?? '';
            }
        } else {
            $html = $this->initialValue;
        }

        $html = $this->highlightKeywords($html);

        return new HtmlString($html);
    }

    /**
     * @return string
     */
    private function highlightKeywords($html)
    {
        if (empty($this->keywords)) {
            return $html;
        }
        // HTMLタグを一時的に置換する
        $htmlTags = [];
        $html = preg_replace_callback('/<([^>]+)>/', function ($matches) use (&$htmlTags) {
            $htmlTags[] = $matches[0];

            return '<#_#' . (count($htmlTags) - 1) . '#_#>';
        }, $html);

        // HTMLタグを除去してキーワードをハイライト
        $text = strip_tags($html);
        foreach ($this->keywords as $keyword) {
            $text = preg_replace('/' . ($keyword) . '/ui', '<span class="' . self::HIGHLIGHT_CLASS_NAME . '">$0</span>', $text);
        }

        // ハイライトされたテキストをHTMLに戻す
        $html = '';
        $parts = preg_split('/<#_#\d+#_#>/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if (preg_match('/<#_#(\d+)#_#>/', $part, $matches)) {
                $html .= $htmlTags[$matches[1]];
            } else {
                $html .= $part;
            }
        }

        return $html;
    }

    /**
     * @return $this
     */
    public function setHighlightKeywords(array $keywords)
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @return void
     */
    public function mount(ColumnDefine $columnDefine, $initialValue, array $attrs = [], bool $asCreate = false, string $idPrefix = '')
    {
        $this->attrs = $attrs;
        $this->columnDefine = $columnDefine;

        $this->nameBase = 'content[' . $columnDefine->id . ']';
        //        $this->valueNameBase = $this->nameBase. "[value]";
        $this->valueNameBase = $this->nameBase;
        //        $this->idNameBase = $this->nameBase. "[id]";
        //        $this->orderNameBase = $this->nameBase. "[order]";
        $this->initialValue = $initialValue;
        $this->asCreate = $asCreate;
        $this->id = $idPrefix . $this->valueNameBase;
    }
}

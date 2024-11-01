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

    private $attachments = [];

    private $asCreate = false;

    private $id = '';

    private $nameBase = '';

    private const BADGE_CLASS_NAME = 'badge badge-secondary bg-secondary/50 py-4 mx-1 my-1';

    private const HIGHLIGHT_CLASS_NAME = 'text-error font-bold text-lg';

    //    private string $orderNameBase;
    //    private string $idNameBase;
    private array $keywords = [];

    /**
     * @var array|mixed
     */
    private array $attachmentContents;

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
            $html = $this->getFileHtml();
            //            dd($this->initialValue);
        } elseif ($this->columnDefine->useOptions && is_array($this->initialValue)) {
            $displayValues = array_filter($this->initialValue, 'strlen');
            $displayValues = array_keys($displayValues);
            if (!empty($displayValues)) {
                $html = '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', $displayValues) . '</span>' ?? '';
            }
            //            var_dump($this->initialValue);
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

        // Use a regular expression to match HTML tags and text nodes
        $pattern = '/<([^>]+)>|([^<>]+)/';
        $result = preg_replace_callback($pattern, function ($matches) {
            if (!empty($matches[1])) { // HTML tag
                return $matches[0];
            } else { // Text node
                $text = $matches[2];
                foreach ($this->keywords as $keyword) {
                    $text = preg_replace('/' . ($keyword) . '/ui', '<span class="' . self::HIGHLIGHT_CLASS_NAME . '">$0</span>', $text);
                }

                return $text;
            }
        }, $html);

        return $result;
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

    public function setAttachments(array|string $attachments)
    {
        if (empty($attachments)) {
            $attachments = [];
        }
        $this->attachments = $attachments;

        return $this;
    }

    public function setAttachmentContents($contents)
    {
        if (empty($contents)) {
            $contents = [];
        }
        $this->attachmentContents = $contents;
        if (!empty($contents)) {

            //            dd($this->setAttachmentContents);
        }

        return $this;
    }

    /**
     * @param string $html
     */
    public function getFileHtml(): string
    {
        $html = '';
        //            dd($this->initialValue);
        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $hit = isset($this->attachments[$hashedFilename]->hit) && $this->attachments[$hashedFilename]->hit == true;
            if ($hit) {
                $hitClass = 'badge-error';
            } else {
                $hitClass = 'badge-accent';
            }

            $url = Storage::url('public/Ledger/Attachments' . DIRECTORY_SEPARATOR . $hashedFilename);
            //                画像ファイルか確認
            if (Storage::exists('public/Ledger/thumbs/' . basename($hashedFilename))) {
                $thumbnailUrl = Storage::url('Ledger/thumbs/' . basename($hashedFilename));
                $html .= <<<HTML
<a href="{$url}"><img class="m-1 rounded-lg shadow-xl {$hitClass}" src="{$thumbnailUrl}" alt="{$originalFilename}"></a>
HTML;
            } else {
                if (empty($this->attachmentContents[$hashedFilename]) || !isset($this->attachmentContents[$hashedFilename]->meta->content)) {
                    $html .= <<<HTML
<a href="{$url}" class="badge {$hitClass} opacity-70 hover:opacity-100 mx-1 my-1 py-4"><i class="fas fa-file mr-2"></i> {$originalFilename}</a>
HTML;
                } else {
                    $content = htmlspecialchars(mb_strimwidth($this->attachmentContents[$hashedFilename]->meta->content, 0, 300, '...'));

                    $html .= <<<HTML
<div class="tooltip" data-tip="{$content}">
<a href="{$url}" class="badge {$hitClass} opacity-70 hover:opacity-100 mx-1 my-1 py-4 "
   ><i class="fas fa-file mr-2"></i> {$originalFilename}</a>
</div>
HTML;

                }

            }
            //                dd($hitClass);
        }

        return $html;
    }
}

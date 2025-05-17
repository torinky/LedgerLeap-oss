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
    private $columnDefineData; // カラム定義データを保持 (配列 or オブジェクト)
    public const BADGE_CLASS_NAME = 'badge badge-secondary bg-secondary/50 py-4 mx-1 my-1';

    private const HIGHLIGHT_CLASS_NAME = 'text-error font-bold text-lg';

    //    private string $orderNameBase;
    //    private string $idNameBase;
    private array $keywords = [];

    /**
     * @var array|mixed
     */
    private array $attachmentContents;

    /**
     * @param object|array $columnDefineData ColumnDefineのオブジェクトまたはカラム定義情報を持つ配列
     * @param mixed $initialValue
     * @param bool $canView
     * @param array $attrs
     * @param string $idPrefix
     * @param bool $asCreate
     * @return HtmlString
     */
    public function show(object|array $columnDefineData, $initialValue, $canView = true, $attrs = [], $idPrefix = '', $asCreate = false): HtmlString
    {
        if ($columnDefineData) { // null でないことを確認
            $this->mount($columnDefineData, $initialValue, $attrs, $asCreate, $idPrefix);
        } else {
            // カラム定義がない場合は、値をそのまま表示するか、エラー表示
            return new HtmlString($canView ? e((string)$initialValue) : '<div class="text-gray-400 text-center">***</div>');
        }

        $html = '';
        if (!$canView) {
            $html = '<div class="text-gray-400 text-center">***</div>';
        } elseif ($this->getColumnDefineProperty('type') == 'files' && is_array($this->initialValue)) {
            $html = $this->getFileHtml();
        } elseif (is_array($this->initialValue)) {
            $displayValues = array_filter($this->initialValue, 'strlen');
            // チェックボックスや複数選択の場合、キーではなく値（ラベル）を表示したい場合がある
            // ここではキーを表示する前提
            $options = $this->getColumnDefineProperty('options', []);
            $displayLabels = [];
            foreach ($displayValues as $key => $value) {
                // $value が true のような boolean の場合、$key が実際の選択肢
                if (is_bool($value) && $value === true && isset($options[$key])) {
                    $displayLabels[] = $options[$key];
                } elseif (!is_bool($value) && isset($options[$value])) { // select の場合
                    $displayLabels[] = $options[$value];
                } elseif (!is_numeric($key) && isset($options[$key])) { // chk でキーが文字列の場合
                    $displayLabels[] = $options[$key];
                } elseif (is_string($key) && !is_numeric($key)) { // オプションにないキーだが表示する場合
                    $displayLabels[] = $key;
                }
            }
            if(empty($displayLabels)) $displayLabels = array_keys($displayValues);


            if (!empty($displayLabels)) {
                $html = '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', array_map('e', $displayLabels)) . '</span>';
            } else {
                $html = '<span class="text-gray-400">---</span>'; // 何も選択されていない場合
            }
        } else {
            // HTMLエンティティをエスケープして表示
            $html = nl2br(e((string)$this->initialValue)); // 改行も反映
            if (empty(trim((string)$this->initialValue))) {
                $html = '<span class="text-gray-400">---</span>';
            }
        }

        $html = $this->highlightKeywords($html);

        return new HtmlString($html);
    }

    /**
     * @param object|array $columnDefineData
     * @param mixed $initialValue
     * @param array $attrs
     * @param bool $asCreate
     * @param string $idPrefix
     * @return void
     */
    public function mount(object|array $columnDefineData, $initialValue, array $attrs = [], bool $asCreate = false, string $idPrefix = ''): void
    {
        $this->attrs = $attrs;
        $this->columnDefineData = $columnDefineData; // 配列またはオブジェクトをそのまま保持

        $id = $this->getColumnDefineProperty('id'); // ヘルパー経由で取得
        $this->nameBase = 'content[' . $id . ']';
        $this->valueNameBase = $this->nameBase;
        $this->initialValue = $initialValue;
        $this->asCreate = $asCreate;
        $this->id = $idPrefix . $this->valueNameBase;
    }

    /**
     * カラム定義データからプロパティを取得するヘルパー
     */
    private function getColumnDefineProperty(string $key, $default = null)
    {
        if (is_object($this->columnDefineData) && isset($this->columnDefineData->{$key})) {
            return $this->columnDefineData->{$key};
        }
        if (is_array($this->columnDefineData) && isset($this->columnDefineData[$key])) {
            return $this->columnDefineData[$key];
        }
        return $default;
    }

    /**
     * @return HtmlString
     */
//    public function show(ColumnDefine $columnDefine, $initialValue, $canView = true, $attrs = [], $idPrefix = '', $asCreate = false)
//    {
//        if ($columnDefine !== null) {
//            $this->mount($columnDefine, $initialValue, $attrs, $asCreate, $idPrefix);
//        }
//
//        $html = '';
//        if (!$canView) {
//            // 権限がない場合は伏せ字にする
//            $html = '<div class="text-gray-400 text-center">***</div>'; // または「閲覧権限なし」などのメッセージ
//        } elseif ($columnDefine->type == 'files' && is_array($this->initialValue)) {
//            $html = $this->getFileHtml();
//        } elseif (is_array($this->initialValue)) {
//            $displayValues = array_filter($this->initialValue, 'strlen');
//            $displayValues = array_keys($displayValues);
//            if (!empty($displayValues)) {
//                $html = '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', $displayValues) . '</span>' ?? '';
//            }
//        } else {
//            $html = $this->initialValue;
//        }
//
//        $html = $this->highlightKeywords($html);
//
//        return new HtmlString($html);
//    }

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
    public function setHighlightKeywords($keywords)
    {
        if (empty($keywords)) {
            return $this;
        }
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * @return void
     */
//    public function mount(ColumnDefine $columnDefine, $initialValue, array $attrs = [], bool $asCreate = false, string $idPrefix = '')
//    {
//        $this->attrs = $attrs;
//        $this->columnDefine = $columnDefine;
//
//        $this->nameBase = 'content[' . $columnDefine->id . ']';
//        //        $this->valueNameBase = $this->nameBase. "[value]";
//        $this->valueNameBase = $this->nameBase;
//        //        $this->idNameBase = $this->nameBase. "[id]";
//        //        $this->orderNameBase = $this->nameBase. "[order]";
//        $this->initialValue = $initialValue;
//        $this->asCreate = $asCreate;
//        $this->id = $idPrefix . $this->valueNameBase;
//    }

    public function setAttachments(array|string $attachments)
    {
        if (empty($attachments)) {
            $attachments = [];
        }
        if (is_string($attachments)) {
            dd($attachments);
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
        if (!is_array($this->initialValue)) return $html; // 値が配列でない場合は空
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

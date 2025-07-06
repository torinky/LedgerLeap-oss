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
     * カラム定義データをもとに値をHTMLとして表示する
     *
     * @param object|array $columnDefineData ColumnDefineのオブジェクト、またはカラム定義情報を持つ配列
     * @param mixed $initialValue 初期値（カラムの値）
     * @param bool $canView 閲覧権限があるかどうか（falseの場合は空文字を返す）
     * @param array $attrs 追加属性（HTML属性など）
     * @param string $idPrefix id属性のプレフィックス
     * @param bool $asCreate 新規作成モードかどうか
     * @return HtmlString 生成されたHTML文字列
     */
    public function show(object|array $columnDefineData, $initialValue, $canView = true, $attrs = [], $idPrefix = '', $asCreate = false): HtmlString
    {
        if (!$this->columnDefineData && !$columnDefineData) {
            return new HtmlString($canView ? e((string)$initialValue) : '');
        }
        if (!$canView) {
            return new HtmlString('');
        }

        $this->mount($columnDefineData, $initialValue, $attrs, $asCreate, $idPrefix);


        $type = $this->getColumnDefineProperty('type');
        $html = '';

        if ($type === 'files' && is_array($this->initialValue)) {
            $html = $this->getFileHtml();
        } elseif ($type === 'number') {
            $unit = $this->getColumnDefineProperty('unit');
            $html = $this->initialValue . $unit;
        } elseif (is_array($this->initialValue)) {
            $options = $this->getColumnDefineProperty('options', []);
            $html = $this->renderArrayValue($type, $this->initialValue, $options);
        } else {
            $html = $this->initialValue;
        }
        return new HtmlString($this->highlightKeywords($html)??'');
    }

    /**
     * 配列値をHTMLとしてレンダリングする
     *
     * @param string $type カラムのタイプ（例: chk, files, select など）
     * @param array $values カラムの値（配列形式）
     * @param array $options 選択肢のラベルなどのオプション配列
     * @return string レンダリングされたHTML文字列
     *
     * chk型の場合はチェックされた項目のみラベル表示。
     * files型以外の配列は値をバッジで表示。
     * files型で値が空の場合は空文字を返す。
     */
    private function renderArrayValue($type, $values, $options): string
    {
        if ($type === 'chk' && !empty($options)) {
            $displayLabels = [];
            foreach ($values as $key => $value) {
                if ($value === true && isset($options[$key])) {
                    $displayLabels[] = $options[$key];
                } elseif ($value === true) {
                    $displayLabels[] = $key;
                }
            }
            return !empty($displayLabels)
                ? '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', array_map('e', $displayLabels)) . '</span>'
                : '';
        }

        // files以外で配列だがchkでもない場合（selectのmultiple等）
        if ($type !== 'files') {
            $displayValues = array_filter($values);
            return '<span class="' . self::BADGE_CLASS_NAME . '">' . implode('</span><span class="' . self::BADGE_CLASS_NAME . '">', array_map('e', $displayValues)) . '</span>';
        }

        // filesで値が空の場合
        return empty($values) ? '' : '';
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
    public
    function setHighlightKeywords($keywords)
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
     * @return ColumnHtmlService
     */
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

    public function setAttachmentContents($contents): static
    {
        if (empty($contents)) {
            $contents = [];
        }
        $this->attachmentContents = $contents;

        return $this;
    }

    /**
     * @param string $html
     */
    public function getFileHtml(): string
    {
        $html = '';
        if (!is_array($this->initialValue)) return $html; // 値が配列でない場合は空

        $thumbnails = [];
        $files = [];

        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $hit = isset($this->attachments[$hashedFilename]->hit) && $this->attachments[$hashedFilename]->hit == true;
            $hitClass = $hit ? 'badge-error' : 'badge-accent';

            $url = Storage::url('public/Ledger/Attachments' . DIRECTORY_SEPARATOR . $hashedFilename);

            if (Storage::exists('public/Ledger/thumbs/' . basename($hashedFilename))) {
                $thumbnailUrl = Storage::url('Ledger/thumbs/' . basename($hashedFilename));
                $thumbnails[] = <<<HTML
    <a href="{$url}"><img class="m-1 rounded-lg shadow-xl {$hitClass}" src="{$thumbnailUrl}" alt="{$originalFilename}"></a>
    HTML;
            } else {
                if (empty($this->attachmentContents[$hashedFilename]) || !isset($this->attachmentContents[$hashedFilename]->meta->content)) {
                    $files[] = <<<HTML
    <a href="{$url}" class="badge {$hitClass} opacity-70 hover:opacity-100 mx-1 my-1 py-4"><i class="fas fa-file mr-2"></i> {$originalFilename}</a>
    HTML;
                } else {
                    $content = htmlspecialchars(mb_strimwidth($this->attachmentContents[$hashedFilename]->meta->content, 0, 300, '...'));
                    $files[] = <<<HTML
    <div class="tooltip" data-tip="{$content}">
    <a href="{$url}" class="badge {$hitClass} opacity-70 hover:opacity-100 mx-1 my-1 py-4 "
       ><i class="fas fa-file mr-2"></i> {$originalFilename}</a>
    </div>
    HTML;
                }
            }
        }

        if (!empty($thumbnails)) {
            $html .= '<div style="display: flex; flex-wrap: wrap; align-items: center;">' . implode('', $thumbnails) . '</div>';
        }
        if (!empty($files)) {
            $html .= implode('', $files);
        }

        return $html;
    }
}

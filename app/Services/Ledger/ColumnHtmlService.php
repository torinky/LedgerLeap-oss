<?php

namespace App\Services\Ledger;

use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;

class ColumnHtmlService
{
    private $attrs = [];

    private $columnDefine;

    private $valueNameBase = '';

    private $initialValue;

    private Collection $attachments;

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
        return new HtmlString($this->highlightKeywords($html) ?? '');
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

    public function setAttachmentCollection(Collection $attachments): static
    {
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
     * @return string
     */
    public function getFileHtml(): string
    {
        $html = '';
        if (!is_array($this->initialValue) || !isset($this->attachments)) {
            return $html;
        }

        $thumbnails = [];
        $files = [];

        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $attachment = $this->attachments->get($hashedFilename);

            // 添付ファイル情報が見つからない場合はスキップ
            if (!$attachment) {
                continue;
            }

            $statusIconHtml = '';
            $retryIconHtml = '';

            // Check if $attachment->status is an instance of AttachedFileStatus enum
            if ($attachment->status instanceof \App\Enums\AttachedFileStatus) {
                $statusIconHtml = <<<HTML
<div class="tooltip" data-tip="{$attachment->status->tooltip()}">
    <x-mary-icon name="{$attachment->status->icon()}" class="{$attachment->status->colorClass()} mr-1" />
</div>
HTML;

                // Add retry icon if status is FAILED
                if ($attachment->status === \App\Enums\AttachedFileStatus::TIKA_FAILED || $attachment->status === \App\Enums\AttachedFileStatus::OCR_FAILED) {
                    $retryIconHtml = <<<HTML
<div class="tooltip" data-tip="再試行">
    <x-mary-icon name="o-arrow-path" class="cursor-pointer text-blue-500 ml-1" wire:click="retryProcessing({$attachment->id})" />
</div>
HTML;
                }
            }

            $hit = isset($attachment->hit) && $attachment->hit == true;
            $hitClass = $hit ? 'badge-error' : 'badge-accent';

            // メインのダウンロードURL (OCR処理後のファイルまたはオリジナルファイル)
            $mainDownloadUrl = route('file.download', ['attachedFile' => $attachment->id]);
            // サムネイルURL
            $thumbnailUrl = route('file.download', ['attachedFile' => $attachment->id, 'thumbnail' => 'true']);

            // ダウンロードリンクの出し分けに必要なURLを事前に定義
            $originalDownloadUrl = route('file.download', ['attachedFile' => $attachment->id, 'original' => true]);
            $optimizedPdfDownloadUrl = route('file.download', ['attachedFile' => $attachment->id]); // OCR処理後のPDF

            $auxiliaryLinksHtml = '';

            // ダウンロードリンクの出し分けロジック
            if (str_starts_with($attachment->original_mime_type, 'image/')) {
                // オリジナルが画像の場合：メインはオリジナル画像、補助はOCR後PDF
                $mainDownloadUrl = $originalDownloadUrl; // Main link is original image
                $auxiliaryLinksHtml = <<<HTML
<div class="flex items-center text-xs text-gray-500 mt-1">
    <a href="{$optimizedPdfDownloadUrl}" target="_blank" class="btn btn-xs btn-ghost tooltip" data-tip="テキスト付きPDFをダウンロード">
        <x-mary-icon name="o-file-pdf" class="w-4 h-4" /><i class="fas fa-file-pdf ml-1"></i>
    </a>
</div>
HTML;
            } elseif ($attachment->original_mime_type === 'application/pdf' && $attachment->optimized) {
                // オリジナルがPDFで最適化済みの場合：メインはOCR後PDF、補助はオリジナルPDF
                $mainDownloadUrl = $optimizedPdfDownloadUrl; // Main link is OCR'd PDF
                $auxiliaryLinksHtml = <<<HTML
<div class="flex items-center text-xs text-gray-500 mt-1">
    <a href="{$originalDownloadUrl}" target="_blank" class="btn btn-xs btn-ghost tooltip" data-tip="オリジナルPDFをダウンロード">
        <x-mary-icon name="o-file" class="w-4 h-4" />
    </a>
</div>
HTML;
            }

            Log::info('$thumbnailUrl:' . $thumbnailUrl . '$auxiliaryLinksHtml:' . $auxiliaryLinksHtml); // Debug output

//            if (Storage::disk('public')->exists('Ledger/thumbs/' . basename($hashedFilename))) {
                if (Storage::disk('public')->exists('Ledger/thumbs/' . basename($hashedFilename))) {
                    $thumbnails[] = <<<HTML
    <div class="flex flex-col items-center mx-1 my-1">
        <a href="{$mainDownloadUrl}" target="_blank"><img class="m-1 rounded-lg shadow-xl {$hitClass}" src="{$thumbnailUrl}" alt="{$originalFilename}"></a>
        {$auxiliaryLinksHtml}
    </div>
HTML;
                } else {
                    $contentHtml = '';
                    if (!empty($this->attachmentContents[$hashedFilename]) && isset($this->attachmentContents[$hashedFilename]->meta->content)) {
                        $content = htmlspecialchars(mb_strimwidth($this->attachmentContents[$hashedFilename]->meta->content, 0, 300, '...'));
                        $contentHtml = <<<HTML
<div class="tooltip" data-tip="{$content}">
HTML;
                    }

                    $files[] = <<<HTML
<div class="flex items-center mx-1 my-1 py-2">
    {$statusIconHtml}
    <a href="{$mainDownloadUrl}" target="_blank" class="badge {$hitClass} opacity-70 hover:opacity-100 py-4">
        <i class="fas fa-file mr-2"></i> {$originalFilename}
    </a>
    {$retryIconHtml}
</div>
{$auxiliaryLinksHtml}
HTML;

                    if (!empty($contentHtml)) {
                        $files[count($files) - 1] = $contentHtml . $files[count($files) - 1] . '</div>'; // Tooltip div close
                    }
                }
//            }
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

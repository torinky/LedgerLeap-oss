<?php

namespace App\Services\Ledger;

use App\Helpers\AttachedFilePathHelper;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class ColumnHtmlService
{
    private AutoLinkService $autoLinkService;

    private MarkdownRenderer $markdownRenderer;

    private HtmlProcessorService $htmlProcessorService;

    private ?string $tenantId = null;

    private $attrs = [];

    private $columnDefine;

    private $valueNameBase = '';

    private $initialValue;

    private Collection $attachments;

    private $asCreate = false;

    private $id = '';

    private $nameBase = '';

    private $columnDefineData; // カラム定義データを保持 (配列 or オブジェクト)

    public const BADGE_CLASS_NAME = 'badge py-4 mx-1 my-1';

    public const SELECT_BADGE_CLASS_NAME = 'badge badge-outline py-4 mx-1 my-1';

    private const HIGHLIGHT_CLASS_NAME = 'text-error font-bold text-lg';

    /**
     * @var array|mixed
     */
    private array $attachmentContents;

    public function __construct(
        AutoLinkService $autoLinkService,
        MarkdownRenderer $markdownRenderer,
        HtmlProcessorService $htmlProcessorService
    ) {
        $this->autoLinkService = $autoLinkService;
        $this->markdownRenderer = $markdownRenderer;
        $this->htmlProcessorService = $htmlProcessorService;
    }

    /**
     * カラム定義データをもとに値をHTMLとして表示する
     *
     * @param  object|array  $columnDefineData  ColumnDefineのオブジェクト、またはカラム定義情報を持つ配列
     * @param  mixed  $initialValue  初期値（カラムの値）
     * @param  bool  $canView  閲覧権限があるかどうか（falseの場合は空文字を返す）
     * @param  array  $attrs  追加属性（HTML属性など）
     * @param  string  $idPrefix  id属性のプレフィックス
     * @param  bool  $asCreate  新規作成モードかどうか
     * @param  Ledger|null  $record  現在の台帳レコード（AutoLinkServiceのコンテキストとして使用）
     * @param  string|null  $highlight  ハイライトするキーワード
     * @return HtmlString 生成されたHTML文字列
     */
    public function show(
        object|array $columnDefineData,
        $initialValue,
        bool $canView = true,
        array $attrs = [],
        string $idPrefix = '',
        bool $asCreate = false,
        ?Ledger $record = null,
        ?string $highlight = null,
        ?string $tenantId = null
    ): HtmlString {
        if (! $canView) {
            return new HtmlString('');
        }

        // ★ 配列で渡された場合は、ColumnDefine オブジェクトに変換する
        if (is_array($columnDefineData)) {
            $columnDefineData = new ColumnDefine($columnDefineData);
        }

        $this->mount($columnDefineData, $initialValue, $attrs, $asCreate, $idPrefix);
        $this->tenantId = $tenantId ?? $record?->define?->tenant_id ?? null;

        $type = $this->getColumnDefineProperty('type');
        $html = '';

        if ($type === 'files' && is_array($this->initialValue)) {
            $html = $this->getFileHtml();
        } elseif (is_array($this->initialValue)) {
            $options = $this->getColumnDefineProperty('options', []);
            $html = $this->renderArrayValue($type, $this->initialValue, $options);
        } elseif ($type === 'select') {
            $html = '<span class="'.self::SELECT_BADGE_CLASS_NAME.'">'.e($this->initialValue).'</span>';
        } elseif ($type === 'textarea') {
            // 1. MarkdownをHTMLに変換
            $convertedHtml = $this->markdownRenderer->toHtml((string) $this->initialValue);

            // 2. 自動リンクを適用
            $processedHtml = $this->autoLinkService->convert($convertedHtml, $this->columnDefineData, $record);

            // 3. 展開可能なコンテンツ用のマーカーを追加
            $html = '<div class="expandable-textarea-content">'.$processedHtml.'</div>';

        } elseif ($type === 'number') {
            $unit = $this->columnDefineData->getInputType()->unit ?? '';
            $html = $this->initialValue.' '.$unit;
            $html = $this->autoLinkService->convert(htmlspecialchars((string) $html, ENT_QUOTES, 'UTF-8'), $this->columnDefineData, $record);
        } else {
            // auto_number, text, url など、他のテキストベースのカラムも自動リンクの対象とする
            $html = $this->autoLinkService->convert(htmlspecialchars((string) $this->initialValue, ENT_QUOTES, 'UTF-8'), $this->columnDefineData, $record);

        }

        // ハイライト処理
        if ($highlight) {
            $html = $this->htmlProcessorService->processTextNodes(
                $html,
                function (\DOMText $textNode, \DOMDocument $dom) use ($highlight) {
                    $fragment = $dom->createDocumentFragment();
                    $parts = preg_split('/('.preg_quote($highlight, '/').')/i', $textNode->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);

                    foreach ($parts as $part) {
                        if (strcasecmp($part, $highlight) === 0) {
                            $mark = $dom->createElement('mark');
                            $mark->setAttribute('class', self::HIGHLIGHT_CLASS_NAME);
                            $mark->appendChild($dom->createTextNode($part));
                            $fragment->appendChild($mark);
                        } else {
                            $fragment->appendChild($dom->createTextNode($part));
                        }
                    }

                    if ($fragment->hasChildNodes()) {
                        $textNode->parentNode->replaceChild($fragment, $textNode);
                    }
                }
            );
        }

        // 最終的なHTMLをラップ
        if ($type === 'textarea') {
            $html = '<div class="prose dark:prose-invert max-w-none">'.$html.'</div>';
        }

        return new HtmlString($html ?? '');
    }

    /**
     * 配列値をHTMLとしてレンダリングする
     */
    private function renderArrayValue($type, $values, $options): string
    {
        if ($type === 'chk' && ! empty($options)) {
            $displayLabels = [];
            foreach ($values as $key => $value) {
                if ($value === true && isset($options[$key])) {
                    $displayLabels[] = $options[$key];
                } elseif ($value === true) {
                    $displayLabels[] = $key;
                }
            }

            return ! empty($displayLabels)
                ? '<span class="'.self::BADGE_CLASS_NAME.'">'.implode('</span><span class="'.self::BADGE_CLASS_NAME.'">', array_map('e', $displayLabels)).'</span>'
                : '';
        }

        if ($type !== 'files') {
            $displayValues = array_filter($values);

            return '<span class="'.self::BADGE_CLASS_NAME.'">'.implode('</span><span class="'.self::BADGE_CLASS_NAME.'">', array_map('e', $displayValues)).'</span>';
        }

        return empty($values) ? '' : '';
    }

    public function mount(object|array $columnDefineData, $initialValue, array $attrs = [], bool $asCreate = false, string $idPrefix = ''): void
    {
        $this->attrs = $attrs;
        $this->columnDefineData = $columnDefineData;
        $id = $this->getColumnDefineProperty('id');
        $this->nameBase = 'content['.$id.']';
        $this->valueNameBase = $this->nameBase;
        $this->initialValue = $initialValue;
        $this->asCreate = $asCreate;
        $this->id = $idPrefix.$this->valueNameBase;
    }

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

    public function setAttachmentCollection(Collection $attachments): static
    {
        // テキストプレビュー機能のため、ledgerリレーションをEager Loadingする
        // EloquentCollectionの場合のみloadMissingを実行
        if ($attachments instanceof \Illuminate\Database\Eloquent\Collection) {
            $attachments->loadMissing('ledger');
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

    private function getFileIconClass(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return match (strtolower($extension)) {
            'pdf' => 'fa-solid fa-file-pdf',
            'doc', 'docx' => 'fa-solid fa-file-word',
            'xls', 'xlsx' => 'fa-solid fa-file-excel',
            'ppt', 'pptx' => 'fa-solid fa-file-powerpoint',
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz' => 'fa-solid fa-file-archive',
            'txt', 'log', 'md', 'csv' => 'fa-solid fa-file-lines',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'tiff' => 'fa-solid fa-file-image',
            'mp3', 'wav', 'ogg', 'flac' => 'fa-solid fa-file-audio',
            'mp4', 'avi', 'mov', 'wmv', 'flv' => 'fa-solid fa-file-video',
            'html', 'htm', 'xml', 'json', 'js', 'ts', 'php', 'py', 'java', 'c', 'cpp', 'h', 'hpp' => 'fa-solid fa-file-code',
            default => 'fa-solid fa-file',
        };
    }

    public function getFileHtml(): string
    {
        $html = '';
        if (! is_array($this->initialValue) || ! isset($this->attachments)) {
            return $html;
        }

        $thumbnails = [];
        $files = [];

        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $attachment = $this->attachments->get($hashedFilename);

            if (! $attachment) {
                continue;
            }

            $statusIconHtml = '';
            $retryIconHtml = '';

            if ($attachment->status instanceof \App\Enums\AttachedFileStatus) {
                // Phase5: 最終化前は処理中ステータスを表示
                $displayStatus = $attachment->getDisplayStatus();
                $tooltip = $displayStatus->getDetailedTooltip($attachment);
                $statusIconHtml = <<<HTML
    <div class="tooltip tooltip-bottom" data-tip="{$tooltip}">
        <i class="{$displayStatus->icon()} {$displayStatus->colorClass()} text-lg"></i>
    </div>
HTML;

                if ($attachment->canUserRequestRetry() ||
                    $attachment->status === \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED ||
                    $attachment->isVlmFailed()
                ) {
                    $isVlmRetry = $attachment->isVlmFailed();

                    $retryTooltipText = match (true) {
                        $isVlmRetry => __('ledger.uploadedFile.retry_vlm'),
                        $attachment->hasExtractionError() => __('ledger.uploadedFile.retry_extraction'),
                        $attachment->status === \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED => __('ledger.uploadedFile.retry_thumbnail'),
                        default => __('ledger.uploadedFile.retry'),
                    };

                    $eventName = $isVlmRetry ? 'retryVlmProcessingEvent' : 'retryProcessingEvent';

                    $retryIconHtml = <<<HTML
<div class="tooltip btn btn-square btn-ghost btn-sm" data-tip="{$retryTooltipText}">
    <i class="fa-solid fa-arrow-rotate-right cursor-pointer" 
    wire:click="\$dispatch('{$eventName}', { attachedFileId: {$attachment->id} })"></i>
</div>
HTML;
                }

                if ($attachment->status === \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED) {
                    \Illuminate\Support\Facades\Bus::dispatch(new \App\Jobs\Ledger\GenerateThumbnail($attachment->id));
                    Log::info('[ColumnHtmlService] Re-dispatched GenerateThumbnail job for ID: '.$attachment->id);
                }

                // 抽出テキストプレビューボタンの生成
                $textPreviewButtonHtml = '';
                if ($attachment->hasPreviewableText()) {
                    $textPreviewTooltip = __('ledger.text_preview.button_tooltip');
                    $textPreviewButtonHtml = <<<HTML
<div x-data="{ isLoading: false }" @text-preview-shown.window="isLoading = false" class="tooltip" data-tip="{$textPreviewTooltip}">
    <button @click="isLoading = true; \$dispatch('showTextPreview', { attachedFileId: {$attachment->id} }); setTimeout('isLoading = false', 5000)" :disabled="isLoading" class="btn btn-square btn-ghost btn-sm">
        <i class="fa-solid fa-eye cursor-pointer" x-show="!isLoading"></i>
        <span class="loading loading-spinner loading-xs" x-show="isLoading" style="display: none;"></span>
    </button>
</div>
HTML;
                }
            }

            $hit = isset($attachment->hit) && $attachment->hit == true;
            $hitClass = $hit ? 'badge-error' : 'badge-accent';

            if (! $this->tenantId) {
                Log::error('Tenant ID is not provided to ColumnHtmlService.');
                $mainDownloadUrl = '#';
                $thumbnailUrl = '#';
                $originalDownloadUrl = '#';
                $optimizedPdfDownloadUrl = '#';
            } else {
                $mainDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);
                $thumbnailUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id, 'thumbnail' => 'true']);
                $originalDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id, 'original' => true]);
                $optimizedPdfDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);
            }

            $auxiliaryLinksHtml = '';

            if (str_starts_with($attachment->original_mime_type, 'image/')) {
                $mainDownloadUrl = $originalDownloadUrl;
                $downloadPdfTooltip = __('ledger.uploadedFile.download_pdf_with_text');
                $auxiliaryLinksHtml = <<<HTML
     <a href="{$optimizedPdfDownloadUrl}" target="_blank" class="btn btn-square btn-ghost tooltip" 
 data-tip="{$downloadPdfTooltip}">
         <i class="fa-solid fa-file-pdf w-4 h-4"></i>
     </a>
HTML;
            } elseif ($attachment->original_mime_type === 'application/pdf' && $attachment->optimized) {
                $mainDownloadUrl = $optimizedPdfDownloadUrl;
                $downloadPdfTooltip = __('ledger.uploadedFile.download_original_pdf');
                $auxiliaryLinksHtml = <<<HTML
 <div class="flex items-center text-xs text-gray-500 mt-1">
     <a href="{$originalDownloadUrl}" target="_blank" 
     class="btn btn-square btn-ghost tooltip" 
     data-tip="{$downloadPdfTooltip}">
         <i class="fa-solid fa-file w-4 h-4"></i>
     </a>
 </div>
HTML;
            }

            $contentHtmlStart = '';
            $contentHtmlEnd = '';
/*            if (! empty($this->attachmentContents[$hashedFilename]) && isset($this->attachmentContents[$hashedFilename]['meta']['content'])) {
                $rawContent = $this->attachmentContents[$hashedFilename]['meta']['content'];
                $plainTextContent = strip_tags($rawContent);
                $sanitizedContent = str_replace(["\r", "\n"], ' ', $plainTextContent);
                $content = htmlspecialchars(mb_strimwidth($sanitizedContent, 0, 300, '...'));
                if (! empty($content)) {
                    $contentHtmlStart = <<<HTML
 <div class="tooltip" data-tip="{$content}">
 HTML;
                    $contentHtmlEnd = '</div>';
                }
            }*/

            if (str_starts_with($attachment->original_mime_type, 'image/') && Storage::disk('public')->exists(AttachedFilePathHelper::getThumbnailStoragePath(basename($hashedFilename)))) {
                $thumbnails[] = <<<HTML
<div class="indicator my-5"> 
<span class="indicator-item">
    {$statusIconHtml}
 {$retryIconHtml}
 {$textPreviewButtonHtml}
    {$auxiliaryLinksHtml}
</span>
{$contentHtmlStart}
         <a href="{$mainDownloadUrl}" target="_blank"><img class="m-1 rounded-lg shadow-xl {
 $hitClass}" src="{$thumbnailUrl}" alt="{$originalFilename}"></a>
{$contentHtmlEnd}
</div>
HTML;
            } else {
                if (str_starts_with($attachment->original_mime_type, 'image/')) {
                    Log::warning('Thumbnail not found for image file: '.$hashedFilename.' at expected path: '.AttachedFilePathHelper::getThumbnailStoragePath(basename($hashedFilename), $this->tenantId));
                }
                $files[] = <<<HTML
 {$contentHtmlStart}
<div class="flex items-center mx-1 my-5 py-2">
    <div class="indicator">
        <span class="indicator-item">
            {$statusIconHtml}
            {$retryIconHtml}
            {$textPreviewButtonHtml}
            {$auxiliaryLinksHtml}
        </span>
        <a href="{$mainDownloadUrl}" target="_blank" class="btn btn-ghost {$hitClass}
     opacity-70 hover:opacity-100 flex flex-col items-center py-10 px-2 m-0">
            <i class="{$this->getFileIconClass($originalFilename)} fa-3x "></i>
            <span>{$originalFilename}</span>
        </a>
    </div>
</div>
 {$contentHtmlEnd}
HTML;
            }
        }

        $html .= '<div class="flex flex-wrap items-center gap-4">'
            .implode('', $thumbnails)
            .implode('', $files)
            .'</div>';

        return $html;
    }
}

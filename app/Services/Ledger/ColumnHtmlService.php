<?php

namespace App\Services\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

        // Mock Attachment Column Support
        $colId = $this->getColumnDefineProperty('id');
        if (\App\Services\Ledger\MockAttachmentService::isMockColumn($colId) && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
            $mockFiles = \App\Services\Ledger\MockAttachmentService::getMockFiles();
            $mode = $this->attrs['mode'] ?? 'full';

            // ヒット判定を追加
            $keywords = \App\Helpers\SearchHelper::extractKeywords($highlight);
            foreach ($mockFiles as &$mf) {
                $mf['is_hit'] = \App\Helpers\SearchHelper::isFileDataHit($mf, $keywords);
            }

            $html = view('components.ledger.attachment-list', [
                'files' => $mockFiles,
                'mode' => $mode,
                'tenantId' => $this->tenantId,
                'search' => $highlight, // 検索キーワードを渡す
            ])->render();

            return new HtmlString($html);
        }

        $type = $this->getColumnDefineProperty('type');
        $html = '';

        if ($type === 'files' && is_array($this->initialValue)) {
            $mode = $this->attrs['mode'] ?? 'full';
            $html = $this->getFileHtml($mode, $highlight);
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
            $keywords = \App\Helpers\SearchHelper::extractKeywords($highlight);
            if (! empty($keywords)) {
                $html = $this->htmlProcessorService->processTextNodes(
                    $html,
                    function (\DOMText $textNode, \DOMDocument $dom) use ($keywords) {
                        $fragment = $dom->createDocumentFragment();
                        // 既にエスケープされている可能性を考慮せず、textNodeの内容をそのままSearchHelper::highlightに渡す。
                        // SearchHelper::highlightの内部で e() を呼ぶようにしているので、
                        // ここでは TextNode の生の値を渡し、生成されたHTMLをフラグメントとして追加する。
                        $highlightedHtml = \App\Helpers\SearchHelper::highlight($textNode->nodeValue, $keywords, self::HIGHLIGHT_CLASS_NAME);

                        // HTMLを含む文字列をDOMノードに変換
                        $tempDom = new \DOMDocument;
                        // UTF-8エンコーディングを明示
                        @$tempDom->loadHTML('<?xml encoding="UTF-8"><div>'.$highlightedHtml.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $container = $tempDom->getElementsByTagName('div')->item(0);

                        if ($container) {
                            foreach ($container->childNodes as $child) {
                                $importedNode = $dom->importNode($child, true);
                                $fragment->appendChild($importedNode);
                            }
                        }

                        if ($fragment->hasChildNodes()) {
                            $textNode->parentNode->replaceChild($fragment, $textNode);
                        }
                    }
                );
            }
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

    /**
     * 「files」タイプのカラムに対して、添付ファイルリストのHTMLを生成する
     *
     * @param  string  $mode  表示モード (full | compact | icon-only)
     * @param  string|null  $highlight  検索ハイライト用のキーワード
     * @return string 生成されたHTML
     */
    public function getFileHtml(string $mode = 'full', ?string $highlight = null): string
    {
        if (! is_array($this->initialValue) || ! isset($this->attachments)) {
            return '';
        }

        $files = $this->prepareFilesData($highlight);

        return view('components.ledger.attachment-list', [
            'files' => $files,
            'mode' => $mode,
            'tenantId' => $this->tenantId,
            'search' => $highlight,
        ])->render();
    }

    /**
     * 添付ファイルのデータを準備する
     *
     * @param  string|null  $highlight  検索ハイライト用のキーワード
     * @return array ファイルデータの配列
     */
    private function prepareFilesData(?string $highlight = null): array
    {
        $files = [];

        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $attachment = $this->attachments->get($hashedFilename);

            if (! $attachment) {
                continue;
            }

            // ダウンロードURLの構築
            if (! $this->tenantId) {
                Log::error('Tenant ID is not provided to ColumnHtmlService.');
                $mainDownloadUrl = '#';
                $thumbnailUrl = null;
                $originalDownloadUrl = '#';
                $optimizedPdfDownloadUrl = '#';
            } else {
                $mainDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);
                // サムネイルURL (画像かつサムネイルファイルが存在する場合)
                $thumbnailUrl = null;
                if (str_starts_with($attachment->original_mime_type, 'image/') && $attachment->hashedbasename) {
                    $thumbnailUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id, 'thumbnail' => true]);
                }
                $originalDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id, 'original' => true]);
                $optimizedPdfDownloadUrl = route('file.download', ['tenant' => $this->tenantId, 'attachedFile' => $attachment->id]);
            }

            // ダウンロードリンクの整理
            $primaryDownload = null;
            $secondaryDownload = null;

            if (str_starts_with($attachment->original_mime_type, 'image/')) {
                // 画像ファイル
                // メイン: 元画像 (original=true)
                $primaryDownload = [
                    'url' => $originalDownloadUrl,
                    'label' => __('ledger.uploadedFile.download_image'),
                    'icon' => 'fa-download',
                ];
                // 補助: OCR後PDF（もしあれば）- URLはoptimizedPdfDownloadUrl (optimized版 or 通常ダウンロード)
                $secondaryDownload = [
                    'url' => $optimizedPdfDownloadUrl,
                    'label' => 'PDF',
                    'icon' => 'fa-file-pdf',
                    'tooltip' => __('ledger.uploadedFile.download_pdf_with_text'),
                ];

            } elseif ($attachment->original_mime_type === 'application/pdf' && $attachment->optimized) {
                // 最適化済みPDF
                // メイン: 最適化済みPDF (通常ルート)
                $primaryDownload = [
                    'url' => $optimizedPdfDownloadUrl,
                    'label' => __('ledger.uploadedFile.download_optimized_pdf'),
                    'icon' => 'fa-file-pdf',
                ];
                // 補助: 元PDF (original=true)
                $secondaryDownload = [
                    'url' => $originalDownloadUrl,
                    'label' => 'Original',
                    'icon' => 'fa-file',
                    'tooltip' => __('ledger.uploadedFile.download_original_pdf'),
                ];
            } else {
                // その他
                $primaryDownload = [
                    'url' => $mainDownloadUrl,
                    'label' => __('ledger.download'),
                    'icon' => 'fa-download',
                ];
            }

            $fileData = [
                'id' => $attachment->id,
                'column_id' => $attachment->column_id,
                'filename' => $originalFilename,
                'mime' => $attachment->original_mime_type ?? $attachment->mime,
                'status' => $attachment->status instanceof \App\Enums\AttachedFileStatus ? $attachment->status->value : $attachment->status, // Enum値を取得
                'size' => $attachment->size,
                'thumbnailUrl' => $thumbnailUrl,
                'primary_download' => $primaryDownload,
                'secondary_download' => $secondaryDownload,
                'created_at' => $attachment->created_at,
                // エラーメッセージなどが必要ならここに追加
                'error_message' => $attachment->error_message ?? null,
            ];

            // 検索ヒット判定を追加
            if ($highlight) {
                $keywords = \App\Helpers\SearchHelper::extractKeywords($highlight);
                // ファイル名、VLMテキスト、OCR/Tikaテキストで検索ヒット判定
                $ocrText = $attachment->getOcrTikaFormattedText('ocr');
                $tikaText = $attachment->getOcrTikaFormattedText('tika');
                $fileData['is_hit'] = \App\Helpers\SearchHelper::hasHit($originalFilename, $keywords)
                    || \App\Helpers\SearchHelper::hasHit($attachment->vlm_markdown, $keywords)
                    || \App\Helpers\SearchHelper::hasHit($ocrText, $keywords)
                    || \App\Helpers\SearchHelper::hasHit($tikaText, $keywords);
            } else {
                $fileData['is_hit'] = false;
            }

            $files[] = $fileData;
        }

        return $files;
    }
}

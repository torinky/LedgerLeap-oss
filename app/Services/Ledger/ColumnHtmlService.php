<?php

namespace App\Services\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\SearchHelper;
use App\Livewire\Traits\LogPerformance;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Services\AutoLinkService;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\HtmlString;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class ColumnHtmlService
{
    use LogPerformance;

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

    private ?Ledger $record = null;

    private string $source = 'unknown';

    public const BADGE_CLASS_NAME = 'badge badge-outline badge-secondary text-base md:text-lg md:badge-lg gap-1';

    public const SELECT_BADGE_CLASS_NAME = 'badge badge-outline badge-primary text-base md:text-lg md:badge-lg gap-1';

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
        $startedAt = microtime(true);

        if (! $canView) {
            return new HtmlString('');
        }

        // ★ 配列で渡された場合は、ColumnDefine オブジェクトに変換する
        if (is_array($columnDefineData)) {
            $columnDefineData = new ColumnDefine($columnDefineData);
        }


        $this->mount($columnDefineData, $initialValue, $attrs, $asCreate, $idPrefix);
        $this->record = $record;
        $this->tenantId = $tenantId ?? $record?->define?->tenant_id ?? null;

        // Mock Attachment Column Support
        $colId = $this->getColumnDefineProperty('id');
        if (MockAttachmentService::isMockColumn($colId) && MockAttachmentService::isEnabled()) {
            $mockFiles = MockAttachmentService::getMockFiles();
            $mode = $this->attrs['mode'] ?? 'full';

            // ヒット判定を追加
            $keywords = SearchHelper::extractKeywords($highlight);
            foreach ($mockFiles as &$mf) {
                $mf['is_hit'] = SearchHelper::isFileDataHit($mf, $keywords);
            }
            unset($mf);

            $html = view('components.ledger.attachment-list', [
                'files' => $mockFiles,
                'mode' => $mode,
                'tenantId' => $this->tenantId,
                'search' => $highlight, // 検索キーワードを渡す
            ])->render();

            $this->logPerformance('column_html_show_ms', (microtime(true) - $startedAt) * 1000, [
                'render_kind' => 'mock_attachment',
            ]);

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
            $currentTenantId = $this->tenantId ?? tenant()?->id ?? $record?->define?->tenant_id ?? 'global';
            $colId = $this->getColumnDefineProperty('id');

            // ハイライトあり / レコード未設定時はキャッシュをバイパス
            if ($highlight || ! $record) {
                $cacheHit = false;
                $convertedHtml = $this->markdownRenderer->toHtml((string) $this->initialValue);
                $processedHtml = $this->autoLinkService->convert($convertedHtml, $this->columnDefineData, $record, $highlight);
                $html = '<div class="expandable-textarea-content">'.$processedHtml.'</div>';
            } else {
                $updatedAtTs = $record->updated_at?->timestamp ?? 0;
                $cacheKey = "column_html:textarea:{$currentTenantId}:{$record->id}:{$colId}:{$updatedAtTs}";
                $ttl = config('ledgerleap.cache.column_html_ttl', 86400);

                $cached = Cache::memo()->get($cacheKey);
                if ($cached !== null) {
                    $cacheHit = true;
                    $html = $cached;
                } else {
                    $cacheHit = false;
                    $convertedHtml = $this->markdownRenderer->toHtml((string) $this->initialValue);
                    $processedHtml = $this->autoLinkService->convert($convertedHtml, $this->columnDefineData, $record, $highlight);
                    $html = '<div class="expandable-textarea-content">'.$processedHtml.'</div>';
                    Cache::memo()->put($cacheKey, $html, $ttl);
                }
            }

            $this->logPerformance('textarea_cache_hit', $cacheHit ? 1 : 0, [
                'tenant_id' => $currentTenantId,
                'ledger_id' => $record?->id,
                'column_id' => $colId,
            ]);

        } elseif ($type === 'number') {
            $unit = $this->columnDefineData->getInputType()->unit ?? '';
            $value = $this->initialValue;
            $html = '<span>'.e($value).'</span>'.($unit ? '<span class="opacity-50 ml-1">'.e($unit).'</span>' : '');
            $html = $this->getCachedColumnHtml($type, $record, $html, $highlight);
        } else {
            // auto_number, text, url など、他のテキストベースのカラムも自動リンクの対象とする
            $rawHtml = htmlspecialchars((string) $this->initialValue, ENT_QUOTES, 'UTF-8');
            $html = $this->getCachedColumnHtml($type, $record, $rawHtml, $highlight);

        }

        // ハイライト処理
        if ($highlight) {
            $keywords = SearchHelper::extractKeywords($highlight);
            if (! empty($keywords)) {
                $html = $this->htmlProcessorService->processTextNodes(
                    $html,
                    function (\DOMText $textNode, \DOMDocument $dom) use ($keywords) {
                        $fragment = $dom->createDocumentFragment();
                        // 既にエスケープされている可能性を考慮せず、textNodeの内容をそのままSearchHelper::highlightに渡す。
                        // SearchHelper::highlightの内部で e() を呼ぶようにしているので、
                        // ここでは TextNode の生の値を渡し、生成されたHTMLをフラグメントとして追加する。
                        $highlightedHtml = SearchHelper::highlight($textNode->nodeValue, $keywords, self::HIGHLIGHT_CLASS_NAME);

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

        $this->logPerformance('column_html_show_ms', (microtime(true) - $startedAt) * 1000, [
            'render_kind' => $type === 'files' ? 'files' : ($type ?? 'unknown'),
            'has_highlight' => (bool) $highlight,
            'as_create' => $asCreate,
        ]);

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
                ? '<div class="flex flex-wrap gap-1">'.implode('', array_map(fn ($label) => '<span class="'.self::BADGE_CLASS_NAME.'">'.e($label).'</span>', $displayLabels)).'</div>'
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

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
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

    protected function getPerformanceContext(): array
    {
        return [
            'source' => $this->source,
            'ledger_id' => $this->record?->id,
            'column_id' => $this->getColumnDefineProperty('id'),
            'column_type' => $this->getColumnDefineProperty('type'),
            'mode' => $this->attrs['mode'] ?? null,
            'tenant_id' => $this->tenantId,
        ];
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

        $startedAt = microtime(true);
        $debugAttachmentHtmlLogs = config('ledgerleap.performance.attachment_html_debug_logs', false);

        $prepareStartedAt = microtime(true);
        $files = $this->prepareFilesData($highlight);
        $prepareFilesDurationMs = (microtime(true) - $prepareStartedAt) * 1000;

        $bladeRenderStartedAt = microtime(true);
        $html = view('components.ledger.attachment-list', [
            'files' => $files,
            'mode' => $mode,
            'tenantId' => $this->tenantId,
            'search' => $highlight,
        ])->render();
        $bladeRenderDurationMs = (microtime(true) - $bladeRenderStartedAt) * 1000;

        $this->logPerformance('column_html_prepare_files_ms', $prepareFilesDurationMs, [
            'mode' => $mode,
            'file_count' => count($files),
        ]);
        $this->logPerformance('column_html_blade_render_ms', $bladeRenderDurationMs, [
            'mode' => $mode,
            'file_count' => count($files),
        ]);

        if ($debugAttachmentHtmlLogs) {
            Log::info('[AttachmentHtml] getFileHtml', [
                'source' => $this->source,
                'ledger_id' => $this->record?->id,
                'column_id' => $this->getColumnDefineProperty('id'),
                'mode' => $mode,
                'file_count' => count($files),
                'attachment_count' => $this->attachments->count(),
                'prepare_files_ms' => round($prepareFilesDurationMs, 2),
                'blade_render_ms' => round($bladeRenderDurationMs, 2),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
        }

        return $html;
    }

    /**
     * 添付ファイルのデータを準備する
     *
     * @param  string|null  $highlight  検索ハイライト用のキーワード
     * @return array ファイルデータの配列
     */
    private function prepareFilesData(?string $highlight = null): array
    {
        $startedAt = microtime(true);
        $debugAttachmentHtmlLogs = config('ledgerleap.performance.attachment_html_debug_logs', false);
        $files = [];
        $filenameMap = [];
        $lookupDurationMs = 0.0;
        $urlBuildDurationMs = 0.0;
        $hitDetectionDurationMs = 0.0;
        $missingAttachmentCount = 0;

        $filenameMapStartedAt = microtime(true);
        foreach ($this->initialValue as $hashedFilename => $originalFilename) {
            $filenameMap[$hashedFilename] = $originalFilename;
        }
        $filenameMapBuildDurationMs = (microtime(true) - $filenameMapStartedAt) * 1000;

        $filenameOriginalDurationMs = 0.0;
        $filenameAttachedLookupDurationMs = 0.0;
        $filenameBasenameDurationMs = 0.0;
        $arrayAssemblyDurationMs = 0.0;
        $payloadBuildDurationMs = 0.0;
        $fileBuildDurationMs = 0.0;
        $fallbackBuildDurationMs = 0.0;
        $routeBuildDurationMs = 0.0;
        $downloadBundleDurationMs = 0.0;
        $scalarFieldDurationMs = 0.0;
        $hitFlagDurationMs = 0.0;
        $filenameResolveDurationMs = 0.0;

        foreach ($filenameMap as $hashedFilename => $originalFilename) {
            $fileStartedAt = microtime(true);

            $phaseStartedAt = microtime(true);
            $attachment = $this->attachments->get($hashedFilename);
            $lookupDurationMs += (microtime(true) - $phaseStartedAt) * 1000;
            $filenameAttachedLookupDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            if (! $attachment) {
                $missingAttachmentCount++;

                $fallbackBuildDurationMs += (microtime(true) - $fileStartedAt) * 1000;

                continue;
            }

            $resolveStartedAt = microtime(true);
            $phaseStartedAt = microtime(true);
            $originalFilenameResolved = $originalFilename;
            if (! is_string($originalFilenameResolved) || $originalFilenameResolved === '') {
                $originalFilenameResolved = $attachment->original_filename ?? $attachment->filename ?? '';
            }
            $filenameOriginalDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            $phaseStartedAt = microtime(true);
            $basename = pathinfo($originalFilenameResolved, PATHINFO_FILENAME);
            $filenameBasenameDurationMs += (microtime(true) - $phaseStartedAt) * 1000;
            $filenameResolveDurationMs += (microtime(true) - $resolveStartedAt) * 1000;

            // ダウンロードURLの構築
            $phaseStartedAt = microtime(true);
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
            $routeBuildDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            // ダウンロードリンクの整理
            $primaryDownload = null;
            $secondaryDownload = null;

            $phaseStartedAt = microtime(true);
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
            $downloadBundleDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            $phaseStartedAt = microtime(true);
            $fileData = [
                'id' => $attachment->id,
                'column_id' => $attachment->column_id,
                'filename' => $originalFilenameResolved,
                'mime' => $attachment->original_mime_type ?? $attachment->mime,
                'status' => $attachment->status instanceof AttachedFileStatus ? $attachment->status->value : $attachment->status, // Enum値を取得
                'size' => $attachment->size,
                'thumbnailUrl' => $thumbnailUrl,
                'primary_download' => $primaryDownload,
                'secondary_download' => $secondaryDownload,
                'created_at' => $attachment->created_at,
                // エラーメッセージなどが必要ならここに追加
                'error_message' => $attachment->error_message ?? null,
            ];
            $payloadBuildDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            // 検索ヒット判定を追加
            $phaseStartedAt = microtime(true);
            if ($highlight) {
                $keywords = SearchHelper::extractKeywords($highlight);
                // ファイル名、VLMテキスト、OCR/Tikaテキストで検索ヒット判定
                $ocrText = $attachment->getOcrTikaFormattedText('ocr');
                $tikaText = $attachment->getOcrTikaFormattedText('tika');
                $fileData['is_hit'] = SearchHelper::hasHit($originalFilenameResolved, $keywords)
                    || SearchHelper::hasHit($attachment->vlm_markdown, $keywords)
                    || SearchHelper::hasHit($ocrText, $keywords)
                    || SearchHelper::hasHit($tikaText, $keywords);
            } else {
                $fileData['is_hit'] = false;
            }
            $hitDetectionDurationMs += (microtime(true) - $phaseStartedAt) * 1000;
            $hitFlagDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            $phaseStartedAt = microtime(true);
            $files[] = $fileData;
            $arrayAssemblyDurationMs += (microtime(true) - $phaseStartedAt) * 1000;

            $fileBuildDurationMs += (microtime(true) - $fileStartedAt) * 1000;
        }

        if ($debugAttachmentHtmlLogs) {
            Log::info('[AttachmentHtml] prepareFilesData', [
                'source' => $this->source,
                'ledger_id' => $this->record?->id,
                'column_id' => $this->getColumnDefineProperty('id'),
                'file_count' => count($files),
                'attachment_count' => $this->attachments->count(),
                'missing_attachment_count' => $missingAttachmentCount,
                'lookup_ms' => round($lookupDurationMs, 2),
                'route_build_ms' => round($routeBuildDurationMs, 2),
                'download_bundle_ms' => round($downloadBundleDurationMs, 2),
                'scalar_field_ms' => round($scalarFieldDurationMs, 2),
                'hit_flag_ms' => round($hitFlagDurationMs, 2),
                'filename_resolve_ms' => round($filenameResolveDurationMs, 2),
                'filename_map_build_ms' => round($filenameMapBuildDurationMs, 2),
                'filename_original_ms' => round($filenameOriginalDurationMs, 2),
                'filename_attached_lookup_ms' => round($filenameAttachedLookupDurationMs, 2),
                'filename_basename_ms' => round($filenameBasenameDurationMs, 2),
                'array_assembly_ms' => round($arrayAssemblyDurationMs, 2),
                'payload_build_ms' => round($payloadBuildDurationMs, 2),
                'file_build_ms' => round($fileBuildDurationMs, 2),
                'fallback_build_ms' => round($fallbackBuildDurationMs, 2),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
        }

        return $files;
    }

    /**
     * カラムHTMLをキャッシュから取得または生成する
     */
    private function getCachedColumnHtml(string $type, ?Ledger $record, string $rawHtml, ?string $highlight): string
    {
        if ($highlight || ! $record) {
            return $this->autoLinkService->convert($rawHtml, $this->columnDefineData, $record, $highlight);
        }

        $currentTenantId = $this->tenantId ?? tenant()?->id ?? $record?->define?->tenant_id ?? 'global';
        $colId = $this->getColumnDefineProperty('id');
        // 同一レコード・同一カラムでも diff の current/old で rawHtml が異なるため、
        // rawHtml のハッシュを含めてキャッシュを分離する。
        $rawHtmlHash = md5($rawHtml);
        $updatedAtTs = $record->updated_at?->timestamp ?? 0;
        $cacheKey = "column_html:{$type}:{$currentTenantId}:{$record->id}:{$colId}:{$updatedAtTs}:{$rawHtmlHash}";
        $ttl = config('ledgerleap.cache.column_html_ttl', 3600);

        $cached = Cache::memo()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $html = $this->autoLinkService->convert($rawHtml, $this->columnDefineData, $record, $highlight);
        Cache::memo()->put($cacheKey, $html, $ttl);

        return $html;
    }

    /**
     * 指定された台帳レコードのカラムHTMLキャッシュをクリアする
     */
    public function clearCacheForLedger(Ledger $ledger): void
    {
        $tenantId = $ledger->define?->tenant_id ?? tenant()?->id ?? 'global';

        // MemoizedStore のメモリキャッシュもクリア（同一リクエスト内での古い値参照を防ぐ）
        Cache::memo()->flush();

        if (Cache::getStore() instanceof RedisStore) {
            $pattern = '*column_html:*:'.$tenantId.':'.$ledger->id.':*';

            try {
                $keys = Redis::keys($pattern);
                if (! empty($keys)) {
                    Redis::del($keys);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clear column html cache', [
                    'ledger_id' => $ledger->id,
                    'pattern' => $pattern,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
